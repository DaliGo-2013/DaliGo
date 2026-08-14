<?php

namespace Tests\Feature\Admin;

use App\Models\ServicioTerreno;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EL TÉCNICO INDUSTRIAL VE EL TARIFARIO, SIN PODER EDITARLO.
 *
 * Pedido del dueño (14-08-2026): «servicios en terreno: para Carlos crear el permiso de vista,
 * actualmente no le aparece, y el manejo de la información, precio y todo el detalle de cada
 * apartado».
 *
 * POR QUÉ UN PERMISO NUEVO Y NO REGALARLE EL QUE HABÍA: `agendar servicio terreno` no solo abre
 * el tarifario, también lo EDITA y además habilita agendar trabajos — y por decisión de gerencia
 * el técnico industrial no agenda (solo ve su agenda y marca lo realizado). Darle ese permiso
 * para que pueda mirar precios le habría abierto dos puertas que nadie pidió.
 *
 * LO QUE ESTOS CANDADOS CUIDAN, además de que entre:
 *   · que vea el PRECIO y el detalle, que es para lo que lo pidió (en la planta del cliente le
 *     preguntan cuánto sale y qué incluye);
 *   · que NO se le ofrezcan botones que terminan en 403 — una función que no puede usar, ofrecida,
 *     es peor que ninguna;
 *   · que no pueda editar ni por la URL directa.
 */
class TarifarioTerrenoVistaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function carlos(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    private function servicio(): ServicioTerreno
    {
        return ServicioTerreno::create([
            'nombre' => 'Full planta 1T',
            'valor_uf' => 12.5,
            'duracion' => '1 día hábil',
            'incluye' => 'Cambio de filtros, sanitización y prueba de presión',
            'observaciones' => 'La soldadura se cobra aparte',
            'activo' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────── entra y ve todo

    public function test_el_tecnico_industrial_entra_al_tarifario(): void
    {
        $this->servicio();

        $this->actingAs($this->carlos())
            ->get(route('admin.servicios-terreno.index'))
            ->assertOk()
            ->assertSee('Catálogo de servicios de terreno');
    }

    /** Lo que vino a buscar: el precio y el detalle de cada servicio. */
    public function test_ve_el_precio_y_el_detalle_de_cada_servicio(): void
    {
        $this->servicio();

        $respuesta = $this->actingAs($this->carlos())
            ->get(route('admin.servicios-terreno.index'))
            ->assertOk();

        $respuesta->assertSee('Full planta 1T');
        $respuesta->assertSee('UF');                       // el valor en UF
        $respuesta->assertSee('1 día hábil');              // duración
        $respuesta->assertSee('Cambio de filtros', false); // qué incluye
        $respuesta->assertSee('La soldadura se cobra aparte');
        // Y las condiciones generales del impreso, que son parte del «todo el detalle».
        $respuesta->assertSee('Garantía de servicio');
    }

    /** Y llega desde su agenda: si no hay por dónde entrar, el permiso no sirve de nada. */
    public function test_le_aparece_la_pestana_en_la_agenda(): void
    {
        $this->actingAs($this->carlos())
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertSee(route('admin.servicios-terreno.index'));
    }

    // ─────────────────────────────────────────────────────── pero no edita

    public function test_no_se_le_ofrecen_los_botones_de_editar(): void
    {
        $this->servicio();

        $respuesta = $this->actingAs($this->carlos())
            ->get(route('admin.servicios-terreno.index'))
            ->assertOk();

        $respuesta->assertDontSee('Nuevo servicio');
        $respuesta->assertDontSee('Editar');
        // Ni el subtítulo le promete que puede: decirle «editable» y no darle ningún botón hace
        // dudar de la pantalla en vez del permiso.
        $respuesta->assertDontSee('(editable)');
    }

    public function test_no_puede_crear_ni_editar_aunque_vaya_por_la_url(): void
    {
        $servicio = $this->servicio();
        $carlos = $this->carlos();

        // Esta app manda al Inicio con un aviso cuando alguien NAVEGA a algo que no le
        // corresponde (ver bootstrap/app.php), así que se comprueba con el redirect y con la
        // base: lo que importa es que no entró y no cambió nada.
        $this->actingAs($carlos)->get(route('admin.servicios-terreno.create'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($carlos)->get(route('admin.servicios-terreno.edit', $servicio))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($carlos)->put(route('admin.servicios-terreno.update', $servicio), [
            'nombre' => 'Precio cambiado a mano', 'valor_uf' => 1,
        ]);

        $this->assertSame('Full planta 1T', $servicio->refresh()->nombre,
            'El técnico industrial cambió el tarifario: solo tiene permiso de vista.');
    }

    // ─────────────────────────────────────────────────── las dos llaves, separadas

    /**
     * EL PUNTO DE HABER SEPARADO LOS PERMISOS (dueño, 14-08-2026): «que puedan elegir dar el
     * permiso o no al perfil… separar el permiso de edición del de agendar».
     *
     * Antes, para que alguien pudiera corregir un precio había que dejarlo agendar trabajos —y
     * al revés, quien agendaba podía cambiar la lista de precios sin que nadie lo decidiera—.
     * Estos dos candados fijan que ahora cada llave abre SOLO su puerta; si algún día vuelven a
     * quedar pegadas, se ponen rojos.
     */
    public function test_agendar_trabajos_ya_no_alcanza_para_editar_el_tarifario(): void
    {
        $servicio = $this->servicio();

        // Un perfil que solo agenda: el caso de un vendedor al que gerencia le quita el
        // tarifario desde la pantalla de Roles.
        $soloAgenda = tap(User::factory()->create())->assignRole('vendedor');
        $soloAgenda->revokePermissionTo('gestionar servicios terreno');
        $soloAgenda->roles->first()->revokePermissionTo('gestionar servicios terreno');

        $this->actingAs($soloAgenda)->put(route('admin.servicios-terreno.update', $servicio), [
            'nombre' => 'Precio cambiado sin permiso', 'valor_uf' => 1,
        ]);

        $this->assertSame('Full planta 1T', $servicio->refresh()->nombre,
            'Agendar trabajos volvió a alcanzar para cambiar precios: las dos llaves quedaron pegadas otra vez.');
    }

    /** Y al revés: quien edita el tarifario no queda habilitado para agendar trabajos. */
    public function test_editar_el_tarifario_no_habilita_agendar_trabajos(): void
    {
        // El perfil que gerencia podría querer: cambia precios, no compromete al técnico.
        $soloTarifario = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $soloTarifario->givePermissionTo('gestionar servicios terreno');

        // Edita el tarifario…
        $this->actingAs($soloTarifario)
            ->get(route('admin.servicios-terreno.create'))
            ->assertOk();

        // …y NO puede agendar (esta app manda al Inicio con un aviso cuando alguien navega a
        // algo que no le corresponde).
        $this->actingAs($soloTarifario)
            ->get(route('admin.agenda-terreno.create'))
            ->assertRedirect(route('dashboard'));
    }

    // ─────────────────────────────────────────────────────── nadie más cambió

    public function test_quien_ya_lo_editaba_sigue_editandolo(): void
    {
        $this->servicio();

        foreach (['jefe_ventas', 'vendedor'] as $rol) {
            $respuesta = $this->actingAs(tap(User::factory()->create())->assignRole($rol))
                ->get(route('admin.servicios-terreno.index'))
                ->assertOk();

            $respuesta->assertSee('Nuevo servicio');
            $respuesta->assertSee('Editar');
        }
    }

    /** Y a quien no tiene ninguno de los dos permisos, sigue sin aparecerle. */
    public function test_el_tecnico_de_taller_sigue_sin_ver_el_tarifario(): void
    {
        // El de taller no va a terreno: el tarifario de terreno no es asunto suyo.
        $this->actingAs(tap(User::factory()->create())->assignRole('tecnico'))
            ->get(route('admin.servicios-terreno.index'))
            ->assertRedirect(route('dashboard'));
    }
}
