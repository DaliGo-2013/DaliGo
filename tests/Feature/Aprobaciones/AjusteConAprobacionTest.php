<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-M14-05: ProduccionController::ajustar cableado al motor. Con la regla y
 * el umbral sembrados (50 unidades): el dedazo chico del jefe fluye igual
 * que siempre (auto-aprobado con registro), la reescritura grande espera al
 * admin con el reporte INTACTO. (Los tests historicos de ajustar en
 * ProduccionTest no siembran la regla → auto-aprueban → siguen cubriendo la
 * semantica original sin cambios.)
 */
class AjusteConAprobacionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        Queue::fake();
    }

    public function test_ajuste_bajo_el_umbral_aplica_en_el_mismo_request(): void
    {
        [$jefe, $reporte] = $this->escenario(); // 100 asignadas, resto 0

        // Σ|Δ| = 10+10+0+0+0 = 20 < 50 → auto-aprobado, UX de siempre.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 110, 'primera' => 10, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Dedazo corregido',
        ])->assertRedirect(route('admin.produccion.reporte.show', $reporte))
            ->assertSessionHas('status', 'Reporte actualizado.');

        $reporte->refresh();
        $this->assertSame(110, $reporte->asignadas);
        $this->assertSame(10, $reporte->primera);
        $this->assertSame('Dedazo corregido', $reporte->motivo_ajuste);
        $this->assertSame(110, $reporte->asignacion->fresh()->asignadas); // snapshot

        // Registro historico aunque no hubo humano aprobando.
        $auto = Aprobacion::where('estado', Aprobacion::ESTADO_AUTO_APROBADA)->sole();
        $this->assertSame(20, $auto->monto);
        $this->assertSame($jefe->id, $auto->resuelto_por);
    }

    public function test_ajuste_sobre_el_umbral_no_muta_y_queda_pendiente_para_admin(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        // Σ|Δ| = 50+80+0+0+0 = 130 >= 50 → pendiente, reporte INTACTO.
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 150, 'primera' => 80, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Recuento físico completo',
        ])->assertRedirect(route('admin.produccion.reporte.show', $reporte));

        $reporte->refresh();
        $this->assertSame(100, $reporte->asignadas); // intacto
        $this->assertSame(0, $reporte->primera);
        $this->assertNull($reporte->motivo_ajuste);
        $this->assertSame(100, $reporte->asignacion->fresh()->asignadas);

        $pendiente = Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)->sole();
        $this->assertSame('admin', $pendiente->rol_aprobador);
        $this->assertSame(130, $pendiente->monto);
        // El admin fue notificado (campanita).
        $this->assertTrue(
            \App\Models\Notificacion::where('evento', 'aprobacion.solicitada')
                ->where('user_id', $admin->id)->exists(),
        );
    }

    public function test_al_aprobar_se_aplica_reporte_y_snapshot(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 150, 'primera' => 80, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Recuento físico completo',
        ]);
        $pendiente = Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)->sole();

        $this->actingAs($admin)->post(route('aprobaciones.aprobar', $pendiente))
            ->assertSessionHas('status', 'Solicitud aprobada y aplicada.');

        $reporte->refresh();
        $this->assertSame(150, $reporte->asignadas);
        $this->assertSame(80, $reporte->primera);
        $this->assertSame('Recuento físico completo', $reporte->motivo_ajuste);
        $this->assertSame(150, $reporte->asignacion->fresh()->asignadas);
    }

    public function test_conflicto_el_reporte_cambio_entremedio_rechaza_y_no_pisa(): void
    {
        [$jefe, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 150, 'primera' => 80, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Recuento físico completo',
        ]);
        $pendiente = Aprobacion::where('estado', Aprobacion::ESTADO_PENDIENTE)->sole();

        // El reporte cambia ENTRE la solicitud y la aprobacion (p.ej. otro
        // ajuste chico auto-aprobado del propio jefe).
        $this->travel(1)->minutes();
        $this->actingAs($jefe)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 105, 'primera' => 0, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Ajuste chico posterior',
        ]);
        $this->assertSame(105, $reporte->fresh()->asignadas);

        // Aprobar el pendiente viejo: conflicto → rechazo automatico, el
        // estado interino sobrevive (jamas payload obsoleto).
        $this->actingAs($admin)->post(route('aprobaciones.aprobar', $pendiente));

        $this->assertSame(Aprobacion::ESTADO_RECHAZADA, $pendiente->fresh()->estado);
        $this->assertStringContainsString('Conflicto', $pendiente->fresh()->resultado_motivo);
        $this->assertSame(105, $reporte->fresh()->asignadas); // NO 150
    }

    public function test_el_admin_ajusta_grande_y_fluye_sin_friccion(): void
    {
        // El propio aprobador (admin) no se auto-solicita: aplica al tiro
        // con registro historico (la respuesta a "¿quien aprueba al admin?").
        [, $reporte] = $this->escenario();
        $admin = tap(User::factory()->create())->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.produccion.reporte.ajustar', $reporte), [
            'asignadas' => 300, 'primera' => 250, 'segunda' => 0, 'malo' => 0, 'danada' => 0,
            'motivo_ajuste' => 'Reescritura del admin',
        ])->assertSessionHas('status', 'Reporte actualizado.');

        $this->assertSame(300, $reporte->fresh()->asignadas);
        $this->assertSame(1, Aprobacion::where('estado', Aprobacion::ESTADO_AUTO_APROBADA)->count());
    }

    /** @return array{0: User, 1: ProduccionReporte} jefe_bodega + reporte (100 asignadas, resto 0) */
    private function escenario(): array
    {
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $asignacion->fecha,
            'turno' => 'dia',
            'asignadas' => 100,
            'estado' => ProduccionReporte::ENVIADO,
        ]);

        return [$jefe, $reporte];
    }
}
