<?php

namespace Tests\Feature\Admin;

use App\Models\Molde;
use App\Models\MoldeMantencion;
use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionParada;
use App\Models\ProduccionReporte;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Models\TipoBotellon;
use App\Models\User;
use App\Support\AvisosError;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El molde como entidad (P-M11-12, dictado v43). Los candados:
 *  1. El contador suma exacto al aprobar (con y sin cavidades), devolver no
 *     resta y re-aprobar no duplica (MUTADO: contador fuera del guard → rojo).
 *  2. Umbral cruzado → UN aviso; la mantención resetea y RE-ARMA.
 *  3. Parada «Molde dañado» aprobada → correctiva pendiente UNA vez.
 *  4. Un molde retirado no aparece en selectores ni recibe ciclos.
 *  5. 403 sin permiso.
 *  6. Con 2+ moldes activos del mismo tipo, aprobar exige elegir.
 */
class ProduccionMoldeTest extends TestCase
{
    use RefreshDatabase;

    private TipoBotellon $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);   // plantillas M15 de los avisos
        Sucursal::firstOrCreate(['codigo' => 'MIRADOR'], ['activa' => true, 'nombre' => 'Mirador', 'es_central' => true]);
        $producto = Producto::create(['sku' => 'BOT-M', 'nombre' => 'Botellón M', 'categoria' => 'Botellones', 'activo' => true]);
        $this->tipo = TipoBotellon::create(['codigo' => 'TM', 'nombre' => 'Tipo M', 'producto_id' => $producto->id, 'activo' => true]);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function molde(array $extra = []): Molde
    {
        return Molde::create($extra + [
            'nombre' => 'Molde '.fake()->unique()->numerify('###'),
            'tipo_botellon_id' => $this->tipo->id,
            'estado' => Molde::ESTADO_ACTIVO,
        ]);
    }

    private function reporteEnviado(int $primera = 100, ?int $cavidades = null, ?string $motivoParada = null): ProduccionReporte
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $primera,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $primera,
            'estado' => ProduccionReporte::ENVIADO,
            'cavidades_activas' => $cavidades,
        ]);
        $reporte->registros()->create([
            'tipo_botellon_id' => $this->tipo->id,
            'primera' => $primera, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
        ]);

        if ($motivoParada !== null) {
            ProduccionParada::create([
                'reporte_id' => $reporte->id,
                'motivo' => $motivoParada,
                'clase' => ProduccionParada::claseDe($motivoParada),
                'origen' => 'maquina',
                'inicio' => '10:00',
                'fin' => '10:30',
            ]);
        }

        return $reporte;
    }

    private function aprobar(ProduccionReporte $reporte, array $payload = []): void
    {
        $this->actingAs($this->jefe())
            ->post(route('admin.produccion.reporte.aprobar', $reporte), $payload);
    }

    // --- Candado 1: el contador suma exacto, idempotente (MUTADO) ---

    public function test_aprobar_suma_ciclos_con_y_sin_cavidades(): void
    {
        $molde = $this->molde();

        // 100 unidades sin cavidades declaradas → factor 1 → +100 ciclos.
        $this->aprobar($this->reporteEnviado(100));
        $this->assertSame(100, $molde->fresh()->ciclos_acumulados);

        // 100 unidades con 2 cavidades → cada ciclo saca 2 → +50.
        $this->aprobar($this->reporteEnviado(100, cavidades: 2));
        $this->assertSame(150, $molde->fresh()->ciclos_acumulados);
    }

    public function test_devolver_no_resta_y_reaprobar_no_duplica(): void
    {
        $molde = $this->molde();
        $reporte = $this->reporteEnviado(100);
        $jefe = $this->jefe();

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $this->assertSame(100, $molde->fresh()->ciclos_acumulados);

        // Doble submit de aprobar: el guard de estado corta.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $this->assertSame(100, $molde->fresh()->ciclos_acumulados);

        // Devolver → el contador NO baja; re-enviar y re-aprobar → el guard
        // del backflush (movimientos ya existen) impide re-sumar.
        $reporte->fresh()->update(['estado' => ProduccionReporte::DEVUELTO]);
        $this->assertSame(100, $molde->fresh()->ciclos_acumulados);
        $reporte->fresh()->update(['estado' => ProduccionReporte::ENVIADO]);
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $this->assertSame(100, $molde->fresh()->ciclos_acumulados);
    }

    // --- Candado 2: umbral → UN aviso; la mantención re-arma ---

    public function test_umbral_cruzado_avisa_una_vez_y_la_mantencion_re_arma(): void
    {
        $molde = $this->molde(['umbral_mantencion' => 80]);

        $this->aprobar($this->reporteEnviado(100));
        $primeros = Notificacion::where('evento', 'molde.umbral_mantencion')->count();
        $this->assertGreaterThan(0, $primeros);
        $this->assertNotNull($molde->fresh()->aviso_umbral_at);

        // Otro reporte con el umbral ya cruzado: NINGÚN aviso nuevo.
        $this->aprobar($this->reporteEnviado(50));
        $this->assertSame($primeros, Notificacion::where('evento', 'molde.umbral_mantencion')->count());
        $this->assertSame(150, $molde->fresh()->ciclos_acumulados);

        // Registrar la mantención: contador a cero, historial, aviso re-armado.
        $this->actingAs($this->jefe())->post(route('admin.moldes.mantencion.store', $molde), [
            'tipo' => MoldeMantencion::TIPO_PREVENTIVA,
            'nota' => 'Cambio de sellos',
        ])->assertRedirect(route('admin.moldes.show', $molde));

        $fresco = $molde->fresh();
        $this->assertSame(0, $fresco->ciclos_acumulados);
        $this->assertNull($fresco->aviso_umbral_at);
        $this->assertDatabaseHas('molde_mantenciones', [
            'molde_id' => $molde->id,
            'tipo' => MoldeMantencion::TIPO_PREVENTIVA,
            'ciclos_al_momento' => 150,
            'nota' => 'Cambio de sellos',
        ]);

        // Nuevo cruce → nuevo aviso (el re-arme funciona).
        $this->aprobar($this->reporteEnviado(100));
        $this->assertGreaterThan($primeros, Notificacion::where('evento', 'molde.umbral_mantencion')->count());
    }

    // --- Candado 3: «Molde dañado» → correctiva pendiente UNA vez ---

    public function test_molde_danado_crea_correctiva_pendiente_una_vez(): void
    {
        $molde = $this->molde();
        $reporte = $this->reporteEnviado(50, motivoParada: 'Molde dañado');
        $jefe = $this->jefe();

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));

        $this->assertSame(1, MoldeMantencion::where('molde_id', $molde->id)
            ->where('tipo', MoldeMantencion::TIPO_CORRECTIVA)->whereNull('realizada_at')->count());
        $this->assertGreaterThan(0, Notificacion::where('evento', 'molde.correctiva_pendiente')->count());

        // Devolver → re-enviar → re-aprobar: SIGUE una sola.
        $reporte->fresh()->update(['estado' => ProduccionReporte::DEVUELTO]);
        $reporte->fresh()->update(['estado' => ProduccionReporte::ENVIADO]);
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte));
        $this->assertSame(1, MoldeMantencion::where('molde_id', $molde->id)
            ->where('tipo', MoldeMantencion::TIPO_CORRECTIVA)->count());
    }

    public function test_falla_de_maquina_no_crea_correctiva(): void
    {
        $this->molde();
        $this->aprobar($this->reporteEnviado(50, motivoParada: 'Falla de máquina'));

        // Eso es de la MÁQUINA, no del molde (dictado v43).
        $this->assertSame(0, MoldeMantencion::count());
    }

    public function test_registrar_la_correctiva_completa_la_pendiente_sin_duplicar(): void
    {
        $molde = $this->molde();
        $this->aprobar($this->reporteEnviado(50, motivoParada: 'Molde dañado'));
        $pendiente = MoldeMantencion::whereNull('realizada_at')->sole();

        $this->actingAs($this->jefe())->post(route('admin.moldes.mantencion.store', $molde), [
            'tipo' => MoldeMantencion::TIPO_CORRECTIVA,
            'nota' => 'Soldadura del canal',
        ]);

        // La MISMA fila queda realizada — sin historial duplicado.
        $this->assertSame(1, MoldeMantencion::count());
        $this->assertNotNull($pendiente->fresh()->realizada_at);
        $this->assertSame('Soldadura del canal', $pendiente->fresh()->nota);
        $this->assertSame(0, $molde->fresh()->ciclos_acumulados);
    }

    // --- Candado 4: retirado fuera de selectores e inferencia ---

    public function test_molde_retirado_no_recibe_ciclos_ni_aparece_en_el_selector(): void
    {
        $retirado = $this->molde(['nombre' => 'Molde Retirado X', 'estado' => Molde::ESTADO_RETIRADO]);
        $activo = $this->molde(['nombre' => 'Molde Activo Y']);

        // Con el retirado fuera, el activo es candidato ÚNICO: sin ambigüedad.
        $reporte = $this->reporteEnviado(60);
        $html = $this->actingAs($this->jefe())
            ->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString('¿Qué molde trabajó el turno?', $html);

        $this->aprobar($reporte);
        $this->assertSame(60, $activo->fresh()->ciclos_acumulados);
        $this->assertSame(0, $retirado->fresh()->ciclos_acumulados);
    }

    // --- Candado 6 (ambigüedad): con 2+ activos, aprobar exige elegir ---

    public function test_con_dos_moldes_activos_aprobar_exige_elegir(): void
    {
        $a = $this->molde(['nombre' => 'Molde A']);
        $b = $this->molde(['nombre' => 'Molde B']);
        $reporte = $this->reporteEnviado(80);
        $jefe = $this->jefe();

        // El show ofrece el selector con ambos.
        $this->actingAs($jefe)->get(route('admin.produccion.reporte.show', $reporte))
            ->assertOk()
            ->assertSee('¿Qué molde trabajó el turno?')
            ->assertSee('Molde A')
            ->assertSee('Molde B');

        // Sin molde: rechazado, el reporte sigue enviado y nadie suma.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte))
            ->assertSessionHasErrors('molde_id');
        $this->assertSame(ProduccionReporte::ENVIADO, $reporte->fresh()->estado);
        $this->assertSame(0, $a->fresh()->ciclos_acumulados + $b->fresh()->ciclos_acumulados);

        // Con molde: aprueba y los ciclos van SOLO al elegido.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.aprobar', $reporte), ['molde_id' => $b->id])
            ->assertRedirect(route('admin.produccion.index'));
        $this->assertSame(0, $a->fresh()->ciclos_acumulados);
        $this->assertSame(80, $b->fresh()->ciclos_acumulados);
        $this->assertSame($b->id, $reporte->fresh()->molde_id);
    }

    // --- Candado 5: permisos ---

    public function test_moldes_requieren_permiso_de_produccion(): void
    {
        $molde = $this->molde();
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($soplador)->get(route('admin.moldes.index'))
            ->assertRedirect(route('dashboard'));
        $this->assertSame(AvisosError::SIN_PERMISO, session('aviso'));

        $this->actingAs($soplador)->get(route('admin.moldes.show', $molde))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($soplador)->post(route('admin.moldes.store'), [
            'nombre' => 'X', 'tipo_botellon_id' => $this->tipo->id, 'estado' => 'activo',
        ])->assertForbidden();

        $this->actingAs($soplador)->post(route('admin.moldes.mantencion.store', $molde), [
            'tipo' => MoldeMantencion::TIPO_PREVENTIVA,
        ])->assertForbidden();
    }

    // --- La ficha muestra el ciclo de la RECETA (única portadora) ---

    public function test_la_ficha_muestra_el_ciclo_de_la_receta(): void
    {
        Receta::create(['producto_id' => $this->tipo->producto_id, 'rol' => Receta::ROL_PREFORMA, 'cantidad' => 1, 'ciclo_ideal_seg' => 24]);
        $molde = $this->molde();

        $this->actingAs($this->jefe())
            ->get(route('admin.moldes.show', $molde))
            ->assertOk()
            ->assertSee('24 s')
            ->assertSee('editar receta');
    }

    // --- CRUD básico ---

    public function test_crear_y_editar_molde(): void
    {
        $jefe = $this->jefe();

        $this->actingAs($jefe)->post(route('admin.moldes.store'), [
            'nombre' => 'Molde 20L A',
            'tipo_botellon_id' => $this->tipo->id,
            'cavidades' => 2,
            'umbral_mantencion' => 50000,
            'estado' => Molde::ESTADO_ACTIVO,
        ])->assertRedirect();
        $this->assertDatabaseHas('moldes', ['nombre' => 'Molde 20L A', 'cavidades' => 2, 'umbral_mantencion' => 50000]);

        $molde = Molde::where('nombre', 'Molde 20L A')->sole();
        $this->actingAs($jefe)->put(route('admin.moldes.update', $molde), [
            'nombre' => 'Molde 20L A',
            'tipo_botellon_id' => $this->tipo->id,
            'estado' => Molde::ESTADO_RETIRADO,
        ])->assertRedirect(route('admin.moldes.show', $molde));
        $this->assertSame(Molde::ESTADO_RETIRADO, $molde->fresh()->estado);

        // Nombre duplicado para el MISMO tipo → rechazado.
        $this->actingAs($jefe)->post(route('admin.moldes.store'), [
            'nombre' => 'Molde 20L A',
            'tipo_botellon_id' => $this->tipo->id,
            'estado' => Molde::ESTADO_ACTIVO,
        ])->assertSessionHasErrors('nombre');
    }
}
