<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\Notificacion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\ReglaAprobacion;
use App\Models\User;
use App\Services\Aprobaciones\Aprobaciones;
use App\Services\Aprobaciones\AprobacionYaResueltaException;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-M14-02: el corazon del motor — solicitar() con auto-aprobacion,
 * aprobar()/rechazar() con lock+re-check, handler del ajuste con deteccion
 * de conflicto. Bateria dictada en PLAN-M14 §2.
 */
class AprobacionesServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake(); // las filas Notificacion se crean antes del dispatch
    }

    // --- solicitar(): caminos de auto-aprobacion ---------------------------

    public function test_auto_aprueba_sin_regla_activa(): void
    {
        ReglaAprobacion::query()->update(['activa' => false]);
        [$jefe, $reporte] = $this->escenario();

        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 500);

        $this->assertSame(Aprobacion::ESTADO_AUTO_APROBADA, $aprobacion->estado);
        $this->assertSame($jefe->id, $aprobacion->resuelto_por);
        $this->assertNotNull($aprobacion->resuelta_at);
        $this->assertSame(500, $reporte->fresh()->asignadas); // aplicado inline
        $this->assertSame(500, $reporte->asignacion->fresh()->asignadas);
        $this->assertSame(0, Notificacion::count()); // cero ruido
    }

    public function test_auto_aprueba_bajo_el_umbral(): void
    {
        [$jefe, $reporte] = $this->escenario();

        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 49); // umbral = 50

        $this->assertSame(Aprobacion::ESTADO_AUTO_APROBADA, $aprobacion->estado);
        $this->assertSame(500, $reporte->fresh()->asignadas);
        $this->assertNotNull($aprobacion->regla_id); // traza: la regla existia pero no matcheo
    }

    public function test_auto_aprueba_si_el_solicitante_porta_el_rol_aprobador(): void
    {
        [, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $aprobacion = $this->solicitarAjuste($admin, $reporte, monto: 9999);

        $this->assertSame(Aprobacion::ESTADO_AUTO_APROBADA, $aprobacion->estado);
        $this->assertSame(500, $reporte->fresh()->asignadas);
    }

    // --- solicitar(): caminos pendientes -----------------------------------

    public function test_queda_pendiente_sobre_el_umbral_y_notifica_al_rol(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 50); // >= umbral

        $this->assertSame(Aprobacion::ESTADO_PENDIENTE, $aprobacion->estado);
        $this->assertSame('admin', $aprobacion->rol_aprobador);
        $this->assertSame(450, $reporte->fresh()->asignadas); // objetivo INTACTO
        // Notificacion 'aprobacion.solicitada' al usuario del rol (canal database siempre).
        $this->assertTrue(
            Notificacion::where('evento', 'aprobacion.solicitada')
                ->where('user_id', $admin->id)
                ->where('canal', Notificacion::CANAL_DATABASE)
                ->exists(),
        );
    }

    public function test_queda_pendiente_con_monto_null_bajo_regla_con_umbral(): void
    {
        // Contrato conservador (PLAN-M14 §1.3): sin magnitud no se puede
        // probar que esta bajo el umbral → pendiente.
        [$jefe, $reporte] = $this->escenario();

        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: null);

        $this->assertSame(Aprobacion::ESTADO_PENDIENTE, $aprobacion->estado);
        $this->assertSame(450, $reporte->fresh()->asignadas);
    }

    // --- aprobar() / rechazar() --------------------------------------------

    public function test_aprobar_aplica_el_ajuste_y_notifica_al_solicitante(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');
        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 100);

        $resuelta = app(Aprobaciones::class)->aprobar($aprobacion, $admin);

        $this->assertSame(Aprobacion::ESTADO_APROBADA, $resuelta->estado);
        $this->assertSame($admin->id, $resuelta->resuelto_por);
        $this->assertSame(500, $reporte->fresh()->asignadas); // aplicado
        $this->assertSame(500, $reporte->asignacion->fresh()->asignadas); // snapshot
        $this->assertTrue(
            Notificacion::where('evento', 'aprobacion.resuelta')
                ->where('user_id', $jefe->id)
                ->exists(),
        );
    }

    public function test_doble_aprobar_lanza_ya_resuelta_sin_doble_aplicacion(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');
        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 100);

        app(Aprobaciones::class)->aprobar($aprobacion, $admin);

        $this->expectException(AprobacionYaResueltaException::class);
        app(Aprobaciones::class)->aprobar($aprobacion, $admin);
    }

    public function test_conflicto_de_updated_at_rechaza_automatico_y_deja_el_objetivo_intacto(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');
        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 100);

        // El reporte cambia ENTRE la solicitud y la aprobacion.
        $this->travel(1)->minutes();
        $reporte->fresh()->update(['asignadas' => 460]);

        $resuelta = app(Aprobaciones::class)->aprobar($aprobacion, $admin);

        $this->assertSame(Aprobacion::ESTADO_RECHAZADA, $resuelta->estado);
        $this->assertStringContainsString('Conflicto', $resuelta->resultado_motivo);
        $this->assertSame(460, $reporte->fresh()->asignadas); // JAMAS payload obsoleto
        $this->assertTrue(
            Notificacion::where('evento', 'aprobacion.resuelta')
                ->where('user_id', $jefe->id)
                ->exists(),
        );
    }

    public function test_rechazar_guarda_el_motivo_y_no_toca_el_objetivo(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');
        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 100);

        $resuelta = app(Aprobaciones::class)->rechazar($aprobacion, $admin, 'Las cantidades no cuadran con el kardex.');

        $this->assertSame(Aprobacion::ESTADO_RECHAZADA, $resuelta->estado);
        $this->assertSame('Las cantidades no cuadran con el kardex.', $resuelta->resultado_motivo);
        $this->assertSame(450, $reporte->fresh()->asignadas);
    }

    public function test_resolver_sin_el_rol_vigente_ni_admin_es_rechazado(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $ventas = tap(User::factory()->create())->assignRole('jefe_ventas');
        $aprobacion = $this->solicitarAjuste($jefe, $reporte, monto: 100);

        $this->expectException(AuthorizationException::class);
        app(Aprobaciones::class)->aprobar($aprobacion, $ventas);
    }

    // --- helpers -------------------------------------------------------------

    /** @return array{0: User, 1: ProduccionReporte} jefe_bodega solicitante + reporte 450 asignadas */
    private function escenario(): array
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 450,
        ]);

        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $asignacion->fecha,
            'turno' => 'dia',
            'asignadas' => 450,
            'estado' => ProduccionReporte::BORRADOR,
        ]);

        return [$jefe, $reporte];
    }

    /** Solicitud de ajuste asignadas 450 → 500 con snapshot fresco (idioma del futuro ajustar()). */
    private function solicitarAjuste(User $solicitante, ProduccionReporte $reporte, ?int $monto): Aprobacion
    {
        $reporte = $reporte->fresh();

        return app(Aprobaciones::class)->solicitar(
            tipoAccion: Aprobacion::ACCION_AJUSTE_REPORTE,
            aprobable: $reporte,
            solicitante: $solicitante,
            motivo: 'Conteo corregido tras revisar la merma',
            datos: [
                'nuevo' => ['asignadas' => 500],
                'anterior' => ['asignadas' => $reporte->asignadas],
                'objetivo_updated_at' => $reporte->updated_at?->toJSON(),
            ],
            monto: $monto,
            descripcion: "Ajuste reporte #{$reporte->id}",
        );
    }
}
