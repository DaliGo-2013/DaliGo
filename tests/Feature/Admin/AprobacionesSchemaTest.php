<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuditController;
use App\Models\Aprobacion;
use App\Models\Configuracion;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\ReglaAprobacion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\ReglasAprobacionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-M14-01: esquema del motor de aprobaciones (tablas, modelos, seeds).
 * El "hecho cuando" del paso: seeders idempotentes y modelo operativo.
 * El comportamiento del motor (solicitar/aprobar/escalar) se testea en
 * P-M14-02 (servicio).
 */
class AprobacionesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_seeders_son_idempotentes(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);
        // Segunda corrida: no debe duplicar ni pisar (patron del deploy).
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);

        $this->assertSame(1, ReglaAprobacion::where('tipo_accion', Aprobacion::ACCION_AJUSTE_REPORTE)->count());
        $this->assertSame(1, Configuracion::where('clave', 'umbral_ajuste_produccion_unidades')->count());
        $this->assertSame(1, Configuracion::where('clave', 'aprobacion_escala_minutos')->count());
    }

    public function test_las_claves_de_configuracion_tienen_sus_defaults(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->assertSame(50, Configuracion::get('umbral_ajuste_produccion_unidades'));
        $this->assertSame(30, Configuracion::get('aprobacion_escala_minutos'));
    }

    public function test_la_regla_sembrada_resuelve_su_umbral_desde_configuracion(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $this->seed(ReglasAprobacionSeeder::class);

        $regla = ReglaAprobacion::activas()
            ->where('tipo_accion', Aprobacion::ACCION_AJUSTE_REPORTE)
            ->firstOrFail();

        $this->assertSame('admin', $regla->rol_aprobador);
        $this->assertNull($regla->rol_escalamiento); // admin es el tope en v1
        $this->assertSame(50, $regla->umbral()); // via Configuracion::get
    }

    public function test_aprobacion_persiste_morph_y_payload(): void
    {
        $solicitante = User::factory()->create();
        $reporte = $this->reporteDe($solicitante);

        $datos = [
            'nuevo' => ['asignadas' => 500, 'primera' => 480],
            'anterior' => ['asignadas' => 450, 'primera' => 430],
            'objetivo_updated_at' => $reporte->updated_at?->toJSON(),
        ];

        $aprobacion = Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'aprobable_type' => $reporte->getMorphClass(),
            'aprobable_id' => $reporte->getKey(),
            'solicitante_id' => $solicitante->id,
            'monto' => 100,
            'motivo' => 'Conteo corregido tras revisar la merma',
            'descripcion' => "Ajuste reporte #{$reporte->id}",
            'datos' => $datos,
            'rol_aprobador' => 'admin',
        ]);

        $fresh = $aprobacion->fresh();
        $this->assertSame(Aprobacion::ESTADO_PENDIENTE, $fresh->estado); // default del esquema
        $this->assertTrue($fresh->esPendiente());
        $this->assertSame($datos, $fresh->datos); // roundtrip del cast array
        $this->assertTrue($fresh->aprobable->is($reporte)); // morph resuelve
        $this->assertSame('Ajuste de reporte de producción', $fresh->etiquetaTipo());
    }

    public function test_scope_para_rol_filtra_pendientes_del_rol_vigente(): void
    {
        $base = [
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'motivo' => 'm',
            'descripcion' => 'd',
        ];
        Aprobacion::create($base + ['rol_aprobador' => 'admin']);
        Aprobacion::create($base + ['rol_aprobador' => 'jefe_bodega']);
        Aprobacion::create($base + [
            'rol_aprobador' => 'admin',
            'estado' => Aprobacion::ESTADO_APROBADA,
            'resuelta_at' => now(),
        ]);

        $this->assertSame(1, Aprobacion::paraRol('admin')->count());
        $this->assertSame(1, Aprobacion::paraRol('jefe_bodega')->count());
    }

    public function test_los_modelos_del_motor_son_auditables_y_visibles_en_auditoria(): void
    {
        $this->assertArrayHasKey(Aprobacion::class, AuditController::MODELOS);
        $this->assertArrayHasKey(ReglaAprobacion::class, AuditController::MODELOS);
        $this->assertContains(
            \OwenIt\Auditing\Auditable::class,
            class_uses_recursive(Aprobacion::class),
        );
        $this->assertContains(
            \OwenIt\Auditing\Auditable::class,
            class_uses_recursive(ReglaAprobacion::class),
        );
    }

    /**
     * Reporte minimo para colgar el morph (mismo idioma que ProduccionTest).
     */
    private function reporteDe(User $soplador): ProduccionReporte
    {
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 450,
        ]);

        return ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => $asignacion->fecha,
            'turno' => $asignacion->turno,
            'asignadas' => 450,
            'estado' => ProduccionReporte::BORRADOR,
        ]);
    }
}
