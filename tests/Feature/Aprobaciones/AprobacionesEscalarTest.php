<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\Notificacion;
use App\Models\ReglaAprobacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P-M14-04: escalamiento por scheduler. La solicitud pendiente sin respuesta
 * tras N minutos pasa al rol_escalamiento de su regla (UNA vez) y se
 * re-notifica al rol nuevo. El comando corre en la grilla de 15 min (I-01).
 */
class AprobacionesEscalarTest extends TestCase
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

    public function test_escalar_agendado_en_la_grilla_de_15(): void
    {
        // I-01 (2026-07-07): el cron de cPanel es `*/15` — la tarea debe caer
        // EXACTO en :00/:15/:30/:45 (misma doctrina que ScheduleBsaleTest).
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'aprobaciones:escalar'));

        $this->assertNotNull($evento, 'Falta aprobaciones:escalar en el scheduler.');
        $this->assertSame('*/15 * * * *', $evento->expression, 'Fuera de la grilla */15 de I-01.');
        $this->assertTrue($evento->withoutOverlapping, 'Debe tener withoutOverlapping.');
    }

    public function test_pendiente_vieja_escala_una_vez_cambia_de_rol_y_renotifica(): void
    {
        $regla = $this->reglaEscalable(); // jefe_bodega → escala a admin
        $admin = tap(User::factory()->create())->assignRole('admin');
        $vieja = $this->pendiente($regla, minutosAtras: 31); // N = 30 (seed)

        $this->artisan('aprobaciones:escalar')
            ->expectsOutputToContain('Solicitudes escaladas: 1.')
            ->assertSuccessful();

        $fresh = $vieja->fresh();
        $this->assertSame(1, $fresh->nivel_escalamiento);
        $this->assertSame('admin', $fresh->rol_aprobador); // bandeja del rol NUEVO
        $this->assertNotNull($fresh->escalada_at);
        $this->assertTrue($fresh->esPendiente()); // escalada NO es estado
        $this->assertTrue(
            Notificacion::where('evento', 'aprobacion.escalada')
                ->where('user_id', $admin->id)
                ->exists(),
        );

        // Segunda corrida: NO re-escala (nivel 1 es el tope en v1).
        $this->artisan('aprobaciones:escalar')
            ->expectsOutputToContain('Solicitudes escaladas: 0.')
            ->assertSuccessful();
        $this->assertSame(1, $vieja->fresh()->nivel_escalamiento);
    }

    public function test_pendiente_joven_no_escala(): void
    {
        $regla = $this->reglaEscalable();
        $joven = $this->pendiente($regla, minutosAtras: 10);

        $this->artisan('aprobaciones:escalar')->assertSuccessful();

        $this->assertSame(0, $joven->fresh()->nivel_escalamiento);
        $this->assertSame('jefe_bodega', $joven->fresh()->rol_aprobador);
    }

    public function test_sin_rol_escalamiento_no_escala(): void
    {
        // La regla sembrada v1 (admin, sin cadena) es exactamente este caso.
        $regla = ReglaAprobacion::where('tipo_accion', Aprobacion::ACCION_AJUSTE_REPORTE)->firstOrFail();
        $vieja = $this->pendiente($regla, minutosAtras: 120);

        $this->artisan('aprobaciones:escalar')
            ->expectsOutputToContain('Solicitudes escaladas: 0.')
            ->assertSuccessful();

        $this->assertSame(0, $vieja->fresh()->nivel_escalamiento);
    }

    public function test_resuelta_no_escala_aunque_sea_vieja(): void
    {
        $regla = $this->reglaEscalable();
        $resuelta = $this->pendiente($regla, minutosAtras: 120);
        $resuelta->update(['estado' => Aprobacion::ESTADO_RECHAZADA, 'resuelta_at' => now()]);

        $this->artisan('aprobaciones:escalar')->assertSuccessful();

        $this->assertSame(0, $resuelta->fresh()->nivel_escalamiento);
    }

    // --- helpers -------------------------------------------------------------

    /** Regla con cadena de escalamiento real: aprueba jefe_bodega, escala a admin. */
    private function reglaEscalable(): ReglaAprobacion
    {
        $regla = ReglaAprobacion::where('tipo_accion', Aprobacion::ACCION_AJUSTE_REPORTE)->firstOrFail();
        $regla->update(['rol_aprobador' => 'jefe_bodega', 'rol_escalamiento' => 'admin']);

        return $regla->fresh();
    }

    private function pendiente(ReglaAprobacion $regla, int $minutosAtras): Aprobacion
    {
        $solicitante = tap(User::factory()->create())->assignRole('vendedor');

        $aprobacion = Aprobacion::create([
            'tipo_accion' => $regla->tipo_accion,
            'regla_id' => $regla->id,
            'solicitante_id' => $solicitante->id,
            'motivo' => 'm',
            'descripcion' => 'Pendiente de prueba',
            'rol_aprobador' => $regla->rol_aprobador,
        ]);

        // Envejecer la solicitud (created_at manda en el barrido).
        $aprobacion->created_at = now()->subMinutes($minutosAtras);
        $aprobacion->save();

        return $aprobacion;
    }
}
