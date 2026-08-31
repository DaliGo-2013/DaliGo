<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionRegistro;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Services\Produccion\Oee;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de MIPROD-1 (pedido DIRECTO del dueño, 21-08, pantalla en mano):
 * los motivos de defecto del soplador se parten POR CALIDAD y pasan a
 * Configuración. OJO — acá NO rige la regla de oro de byte-idéntico: el dueño
 * pidió conducta NUEVA (una segunda es por definición un defecto estético →
 * un solo chip; las malas pierden «Scrap de arranque» — decisión INFORMADA:
 * el desglose de scrap del OEE queda sin fuente hacia adelante). Lo que sí es
 * invariante: las tandas históricas conservan su motivo (persistido por fila)
 * y el desglose de scrap sigue leyendo el legado.
 */
class ParametrosMiProduccionTest extends TestCase
{
    use RefreshDatabase;

    private Maquina $maquina;

    private TipoBotellon $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SucursalSeeder::class);
        $this->maquina = Maquina::create(['nombre' => 'SOPLA-1', 'sucursal_id' => Sucursal::first()->id, 'activa' => true]);
        $this->tipo = TipoBotellon::create(['codigo' => 'B20', 'nombre' => 'Botellón 20L', 'activo' => true]);
    }

    private function soplador(): User
    {
        return tap(User::factory()->create(['sucursal_id' => $this->maquina->sucursal_id]))->assignRole('soplador');
    }

    private function reporteDe(User $soplador): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 200,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 200,
            'estado' => ProduccionReporte::BORRADOR,
        ]);
    }

    private function tanda(array $extra): array
    {
        return array_merge([
            'maquina_id' => $this->maquina->id,
            'tipo_botellon_id' => $this->tipo->id,
            'primera' => 0, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ], $extra);
    }

    public function test_la_conducta_nueva_del_dueno_rige_con_bd_virgen(): void
    {
        // Sin ConfiguracionSeeder: rigen los fallbacks NUEVOS (el pedido).
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        // La pantalla ofrece UN chip para segundas y las malas SIN scrap
        // (forma contigua del chip: los motivos tienen gemelos de texto en
        // otros forms de la misma pantalla — doctrina verde-engañoso).
        $pantalla = $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))->assertOk();
        $pantalla->assertSee('name="motivo_segunda" value="Detalles estéticos"', false)
            ->assertDontSee('name="motivo_segunda" value="Rebaba"', false)
            ->assertSee('name="motivo_malo" value="Material quemado"', false)
            ->assertDontSee('name="motivo_malo" value="Scrap de arranque"', false);

        // La validación va con la pantalla: segunda con motivo grave, rechazada…
        $this->actingAs($soplador)
            ->post(route('produccion.mi.registros.store', $reporte), $this->tanda(['segunda' => 5, 'motivo_segunda' => 'Material quemado']))
            ->assertSessionHasErrors('motivo_segunda');
        // …mala con scrap, rechazada; y el camino feliz de cada calidad pasa.
        $this->actingAs($soplador)
            ->post(route('produccion.mi.registros.store', $reporte), $this->tanda(['malo' => 3, 'motivo_malo' => 'Scrap de arranque']))
            ->assertSessionHasErrors('motivo_malo');
        $this->actingAs($soplador)
            ->post(route('produccion.mi.registros.store', $reporte), $this->tanda(['segunda' => 5, 'motivo_segunda' => 'Detalles estéticos', 'malo' => 3, 'motivo_malo' => 'Material quemado']))
            ->assertSessionHasNoErrors();
        $registro = $reporte->registros()->firstOrFail();
        $this->assertSame('Detalles estéticos', $registro->motivo_segunda);
        $this->assertSame('Material quemado', $registro->motivo_malo);
    }

    public function test_mover_una_lista_mueve_sus_chips_y_su_validacion_y_no_la_hermana(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        Configuracion::set('produccion_motivos_segunda', ['Detalles estéticos', 'Color desviado']);

        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('name="motivo_segunda" value="Color desviado"', false)
            ->assertDontSee('name="motivo_malo" value="Color desviado"', false); // la hermana quieta

        $this->actingAs($soplador)
            ->post(route('produccion.mi.registros.store', $reporte), $this->tanda(['segunda' => 2, 'motivo_segunda' => 'Color desviado']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Color desviado', $reporte->registros()->firstOrFail()->motivo_segunda);
    }

    public function test_las_tandas_historicas_conservan_su_motivo_y_el_scrap_del_oee_lee_el_legado(): void
    {
        // La decisión informada del dueño, como candado: el motivo se PERSISTE
        // por fila — quitarlo de la lista no reescribe ni esconde el pasado.
        $this->seed(ConfiguracionSeeder::class);
        $soplador = $this->soplador();
        $reporte = $this->reporteDe($soplador);

        // Tanda histórica insertada directo (como quedó en BD antes del cambio).
        ProduccionRegistro::create([
            'reporte_id' => $reporte->id, 'maquina_id' => $this->maquina->id,
            'tipo_botellon_id' => $this->tipo->id,
            'primera' => 50, 'segunda' => 4, 'malo' => 6, 'danada' => 0,
            'motivo_segunda' => 'Material quemado', 'motivo_malo' => 'Scrap de arranque',
        ]);

        // La pantalla del soplador muestra el motivo legado tal cual.
        $this->actingAs($soplador)->get(route('produccion.mi.show', $reporte))
            ->assertOk()
            ->assertSee('2ª: Material quemado')
            ->assertSee('Malas: Scrap de arranque');

        // Y el desglose de scrap del OEE sigue contando la tanda histórica.
        $hoy = now()->toDateString();
        $oee = app(Oee::class)->paraMaquina($this->maquina, $hoy, $hoy);
        $this->assertSame(6, $oee['scrap']);
    }

    public function test_la_ui_edita_las_dos_listas_como_lista_simple(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');

        foreach (['produccion_motivos_segunda', 'produccion_motivos_malas'] as $clave) {
            $config = Configuracion::where('clave', $clave)->firstOrFail();

            // Una por línea, y la lista vacía se rechaza (mecanismo LISTAS_SIMPLES).
            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => "Uno\nDos"])
                ->assertSessionHasNoErrors();
            $this->assertSame(['Uno', 'Dos'], Configuracion::get($clave));

            $this->actingAs($admin)
                ->put(route('admin.configuracion.update', $config), ['valor' => "  \n  "])
                ->assertSessionHasErrors('valor');
        }
    }
}
