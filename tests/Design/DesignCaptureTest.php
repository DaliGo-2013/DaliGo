<?php

namespace Tests\Design;

use App\Models\Notificacion;
use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\User;
use Database\Seeders\MaquinaSeeder;
use Database\Seeders\ProduccionTesteoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SucursalSeeder;
use Database\Seeders\TipoBotellonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Harness de CAPTURA para el bundle de diseño (design/) — NO es un test de CI:
 * phpunit.xml solo declara las suites Unit y Feature, así que este archivo corre
 * únicamente por path explícito:
 *
 *   php artisan test tests/Design/DesignCaptureTest.php
 *
 * Renderiza 3 pantallas reales con un admin y datos demo 100% sintéticos
 * (SQLite :memory:, estados SIEMPRE fijados — bitácora 2026-07-13) y guarda el
 * HTML en design/.capture/ para que design/tools/clean-capture.mjs lo convierta
 * en previews estáticos.
 */
class DesignCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SucursalSeeder::class);
        $this->seed(TipoBotellonSeeder::class);
        $this->seed(MaquinaSeeder::class);
        $this->seed(ProduccionTesteoSeeder::class);
    }

    public function test_captura_pantallas_para_el_bundle_de_diseno(): void
    {
        // Admin con identidad ficticia (el repo es público: nada de datos reales).
        $admin = User::factory()->create([
            'name' => 'María Demo',
            'email' => 'admin@daligo.test',
        ]);
        $admin->assignRole('admin');

        // Campanita con actividad (3 no-leídas del canal database).
        foreach (['Reporte de producción enviado', 'Nueva solicitud de aprobación', 'Equipo listo para entrega'] as $titulo) {
            Notificacion::create([
                'user_id' => $admin->id,
                'evento' => 'sistema.prueba',
                'canal' => Notificacion::CANAL_DATABASE,
                'titulo' => $titulo,
                'cuerpo' => 'Notificación de demostración para el bundle de diseño.',
                'estado' => Notificacion::ENVIADA,
            ]);
        }

        // Taller con mezcla realista de estados (badge del nav = 5 activos).
        $estadosOrdenes = [
            'recibido', 'recibido', 'en_revision', 'cotizacion',
            'esperando_repuesto', 'reparado', 'entregado', 'sin_solucion',
        ];
        foreach ($estadosOrdenes as $estado) {
            OrdenServicio::factory()->create([
                'estado' => $estado,
                'fecha_entrega' => $estado === 'entregado' ? now()->toDateString() : null,
            ]);
        }

        // Producción de hoy: un reporte aprobado, uno enviado (cola del jefe) y
        // uno devuelto — así el panel muestra métricas, cola y alertas a la vez.
        $this->produccion(['primera' => 150, 'segunda' => 30, 'malo' => 15, 'danada' => 5,
            'estado' => ProduccionReporte::APROBADO], 200);
        $this->produccion(['primera' => 90, 'malo' => 10,
            'estado' => ProduccionReporte::ENVIADO, 'enviado_at' => now()->subHours(2)], 100);
        $this->produccion(['primera' => 40, 'segunda' => 5,
            'estado' => ProduccionReporte::DEVUELTO], 120);

        @mkdir(base_path('design/.capture'), 0777, true);

        $pantallas = [
            'dashboard' => route('dashboard'),
            'produccion' => route('admin.produccion.index'),
            'servicio-tecnico' => route('admin.servicio-tecnico.index'),
        ];

        foreach ($pantallas as $nombre => $url) {
            $res = $this->actingAs($admin)->get($url);
            $res->assertOk();
            file_put_contents(base_path("design/.capture/{$nombre}.html"), $res->getContent());
        }

        $this->assertFileExists(base_path('design/.capture/servicio-tecnico.html'));
    }

    /** Asignación + reporte de hoy con estado FIJO (patrón de DashboardTest). */
    private function produccion(array $reporte, int $asignadas): ProduccionReporte
    {
        $soplador = User::factory()->create();
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $asignadas,
        ]);

        return ProduccionReporte::create($reporte + [
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => $asignadas,
        ]);
    }
}
