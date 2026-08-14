<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaCierre;
use App\Models\User;
use App\Support\FechaNegocio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LA PANTALLA DONDE EL JEFE DE VENTAS CIERRA DÍAS.
 *
 * Pedido del dueño (13-08-2026): «todo esto alimentado por el jefe de ventas, que va a ser el
 * que lleve adelante la agenda del técnico industrial».
 *
 * Lo que estos candados cuidan, además de que el CRUD ande:
 *   · que NO sea cualquiera quien cierra la agenda — un vendedor agenda trabajos, pero
 *     cerrarle los días a todos es otra responsabilidad;
 *   · que los feriados no se puedan borrar desde acá (vuelven en el próximo deploy, así que
 *     el botón sería una promesa que no se puede cumplir);
 *   · que el acceso a la pantalla EXISTA en la agenda: una función que no se ve, no existe.
 */
class CierresAgendaPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    private function manana(int $dias = 3): string
    {
        return Carbon::parse(FechaNegocio::hoy())->addDays($dias)->toDateString();
    }

    // ─────────────────────────────────────────────────────── quién entra

    public function test_el_jefe_de_ventas_entra(): void
    {
        $this->actingAs($this->jefe())
            ->get(route('admin.agenda-terreno.cierres.index'))
            ->assertOk()
            ->assertSee('Cuándo no se atiende')
            ->assertSee('Esto lo ve el cliente');
    }

    /**
     * Un vendedor agenda trabajos, pero no le cierra la agenda al técnico: son
     * responsabilidades distintas y el dueño puso esta en el jefe de ventas.
     */
    /**
     * Un vendedor agenda trabajos, pero no le cierra la agenda al técnico: son
     * responsabilidades distintas y el dueño puso esta en el jefe de ventas.
     *
     * Se comprueba con el REDIRECT y con la base, no con un 403: esta app manda al Inicio con
     * un aviso cuando alguien NAVEGA a algo que no le corresponde (ver `bootstrap/app.php`),
     * porque una pantalla de Symfony en inglés y sin menú deja al usuario sin salida. Lo que
     * importa igual es lo mismo: no entró y no cerró nada.
     */
    public function test_un_vendedor_no_puede_cerrar_la_agenda(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');

        $this->actingAs($vendedor)
            ->get(route('admin.agenda-terreno.cierres.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($vendedor)->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $this->manana(), 'tipo' => 'cerrado', 'motivo' => 'Prueba',
        ]);

        $this->assertSame(0, AgendaCierre::count(), 'Un vendedor cerró días de la agenda.');
    }

    public function test_el_tecnico_industrial_tampoco(): void
    {
        // Ve su agenda y marca lo realizado; no la administra (decisión de gerencia ya
        // vigente para el resto de la agenda).
        $this->actingAs(tap(User::factory()->create())->assignRole('tecnico_industrial'))
            ->get(route('admin.agenda-terreno.cierres.index'))
            ->assertRedirect(route('dashboard'));
    }

    // ─────────────────────────────────────────────────────── cargar

    public function test_cargar_unas_vacaciones_cierra_todo_el_rango(): void
    {
        $desde = $this->manana(10);
        $hasta = $this->manana(20);

        $this->actingAs($this->jefe())->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tipo' => 'cerrado',
            'motivo' => 'Vacaciones de Carlos',
        ])->assertRedirect();

        $c = AgendaCierre::sole();
        $this->assertSame($desde, $c->fecha_desde->toDateString());
        $this->assertSame($hasta, $c->fecha_hasta->toDateString());
        $this->assertSame(AgendaCierre::ORIGEN_MANUAL, $c->origen);
        $this->assertNotNull($c->creado_por, 'No quedó quién lo cargó: dentro de un mes nadie sabe de dónde salió.');
    }

    public function test_sin_fecha_hasta_es_un_solo_dia(): void
    {
        $dia = $this->manana(4);

        $this->actingAs($this->jefe())->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $dia, 'fecha_hasta' => '', 'tipo' => 'cerrado', 'motivo' => 'Capacitación',
        ])->assertRedirect();

        $this->assertSame($dia, AgendaCierre::sole()->fecha_hasta->toDateString());
    }

    /** Un rango al revés es un tipeo, no un rango: se ordena en vez de rechazarlo. */
    public function test_un_rango_al_reves_se_ordena_solo(): void
    {
        $this->actingAs($this->jefe())->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $this->manana(20),
            'fecha_hasta' => $this->manana(10),
            'tipo' => 'cerrado',
            'motivo' => 'Vacaciones',
        ])->assertRedirect();

        $c = AgendaCierre::sole();
        $this->assertTrue($c->fecha_desde->lessThan($c->fecha_hasta));
    }

    public function test_la_media_jornada_exige_la_hora(): void
    {
        $this->actingAs($this->jefe())->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $this->manana(), 'tipo' => 'media_jornada', 'motivo' => 'Sale temprano',
        ])->assertSessionHasErrors('hora_hasta');
    }

    /** Y en un día cerrado la hora no se guarda: sería un dato que contradice al tipo. */
    public function test_un_dia_cerrado_no_guarda_hora(): void
    {
        $this->actingAs($this->jefe())->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $this->manana(), 'tipo' => 'cerrado', 'hora_hasta' => '14:00', 'motivo' => 'Feriado interno',
        ])->assertRedirect();

        $this->assertNull(AgendaCierre::sole()->hora_hasta);
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        // Es interno, pero sin él nadie recuerda en un mes por qué se cerró ese día.
        $this->actingAs($this->jefe())->post(route('admin.agenda-terreno.cierres.store'), [
            'fecha_desde' => $this->manana(), 'tipo' => 'cerrado',
        ])->assertSessionHasErrors('motivo');
    }

    // ─────────────────────────────────────────────────────── borrar

    public function test_se_puede_quitar_un_cierre_cargado_a_mano(): void
    {
        $c = AgendaCierre::create([
            'fecha_desde' => $this->manana(), 'fecha_hasta' => $this->manana(),
            'tipo' => 'cerrado', 'motivo' => 'Vacaciones', 'origen' => AgendaCierre::ORIGEN_MANUAL,
        ]);

        $this->actingAs($this->jefe())
            ->delete(route('admin.agenda-terreno.cierres.destroy', $c))
            ->assertRedirect();

        $this->assertSame(0, AgendaCierre::count());
    }

    /**
     * Un feriado borrado vuelve en el próximo deploy: mejor no ofrecer el botón. Y si alguien
     * llega igual por la URL, el rechazo vuelve ATRÁS con el motivo escrito —no a una pantalla
     * de error— porque ese texto es copy de negocio que se lee junto a la lista.
     */
    public function test_un_feriado_no_se_puede_borrar(): void
    {
        $c = AgendaCierre::create([
            'fecha_desde' => '2026-12-25', 'fecha_hasta' => '2026-12-25',
            'tipo' => 'cerrado', 'motivo' => 'Feriado legal: Navidad', 'origen' => AgendaCierre::ORIGEN_FERIADO,
        ]);

        $this->actingAs($this->jefe())
            ->from(route('admin.agenda-terreno.cierres.index'))
            ->delete(route('admin.agenda-terreno.cierres.destroy', $c))
            ->assertRedirect(route('admin.agenda-terreno.cierres.index'));

        $this->assertSame(1, AgendaCierre::count(), 'Se borró un feriado: volvería en el próximo deploy igual.');
    }

    // ─────────────────────────────────────────────────────── el puente

    /** Una función que no se ve, no existe: la agenda tiene que llevar a esta pantalla. */
    public function test_la_agenda_ofrece_el_acceso_a_los_cierres(): void
    {
        $this->actingAs($this->jefe())
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertSee(route('admin.agenda-terreno.cierres.index'));
    }

    /** Y a quien no puede administrarlos, no se le ofrece. */
    public function test_al_vendedor_no_se_le_ofrece_el_acceso(): void
    {
        $this->actingAs(tap(User::factory()->create())->assignRole('vendedor'))
            ->get(route('admin.agenda-terreno.index'))
            ->assertOk()
            ->assertDontSee(route('admin.agenda-terreno.cierres.index'));
    }
}
