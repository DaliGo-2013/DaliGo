<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Separar por categoría (tipo de solicitud) las tres superficies de M14:
 * bandeja del aprobador, "Mis solicitudes" y el historial admin.
 *
 * Hoy el motor tiene UN solo tipo real (produccion.ajuste_reporte); para probar
 * la agrupación se crea además un tipo simulado ('zzz.otro_tipo', cuyo
 * etiquetaTipo() cae al id crudo). Las filas se crean INTERCALADAS por fecha:
 * en la lista plana anterior saldrían mezcladas, así que assertSeeInOrder solo
 * pasa si de verdad se agrupan (mutation-proof).
 */
class AprobacionCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private const OTRO_TIPO = 'zzz.otro_tipo';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
    }

    /** Pendiente directa con created_at controlado (edad en minutos hacia atrás). */
    private function pendiente(string $tipo, string $desc, int $edadMin, ?User $solicitante = null): Aprobacion
    {
        $a = Aprobacion::create([
            'tipo_accion' => $tipo,
            'descripcion' => $desc,
            'motivo' => 'motivo',
            'rol_aprobador' => 'admin',
            'solicitante_id' => $solicitante?->id,
        ]);
        $a->created_at = now()->subMinutes($edadMin);
        $a->save();

        return $a;
    }

    public function test_la_bandeja_separa_las_solicitudes_por_categoria(): void
    {
        // Intercaladas por antigüedad (la bandeja ordena oldest-first).
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'PROD uno', 40);
        $this->pendiente(self::OTRO_TIPO, 'OTRO uno', 30);
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'PROD dos', 20);
        $this->pendiente(self::OTRO_TIPO, 'OTRO dos', 10);

        $admin = tap(User::factory()->create())->assignRole('admin');

        $res = $this->actingAs($admin)->get(route('aprobaciones.index'))->assertOk();

        // Cada categoría con su cabecera (el tipo simulado cae al id crudo).
        $res->assertSee('Ajuste de reporte de producción');
        $res->assertSee(self::OTRO_TIPO);

        // Agrupadas: TODAS las de producción juntas antes de TODAS las del otro
        // tipo, pese a haberse creado intercaladas. En la lista plana vieja el
        // orden sería PROD uno · OTRO uno · PROD dos · OTRO dos → este assert falla.
        $res->assertSeeInOrder(['PROD uno', 'PROD dos', 'OTRO uno', 'OTRO dos']);
    }

    public function test_mis_solicitudes_separa_por_categoria(): void
    {
        $yo = tap(User::factory()->create())->assignRole('soplador'); // cualquier autenticado
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'MIA PROD uno', 40, $yo);
        $this->pendiente(self::OTRO_TIPO, 'MIA OTRO uno', 30, $yo);
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'MIA PROD dos', 20, $yo);
        $this->pendiente(self::OTRO_TIPO, 'MIA OTRO dos', 10, $yo);

        $res = $this->actingAs($yo)->get(route('aprobaciones.mias'))->assertOk();

        $res->assertSee('Ajuste de reporte de producción');
        // "Mis solicitudes" ordena latest-first; agrupado: prod=[dos, uno], otro=[dos, uno].
        // Plano sería OTRO dos · PROD dos · OTRO uno · PROD uno → este assert falla.
        $res->assertSeeInOrder(['MIA PROD dos', 'MIA PROD uno', 'MIA OTRO dos', 'MIA OTRO uno']);
    }

    public function test_el_historial_desglosa_por_tipo(): void
    {
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'H uno', 30);
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'H dos', 20);
        $this->pendiente(self::OTRO_TIPO, 'H tres', 10);

        $admin = tap(User::factory()->create())->assignRole('admin');

        $res = $this->actingAs($admin)->get(route('admin.aprobaciones.index'))->assertOk();

        // Recuadro-resumen "Por tipo" con el conteo por categoría (respeta filtros).
        $res->assertSee('Por tipo');
        $res->assertViewHas('porTipo', fn ($porTipo) => (int) ($porTipo[Aprobacion::ACCION_AJUSTE_REPORTE] ?? 0) === 2
            && (int) ($porTipo[self::OTRO_TIPO] ?? 0) === 1);
    }

    public function test_el_filtro_por_tipo_del_historial_acota_la_lista(): void
    {
        $this->pendiente(Aprobacion::ACCION_AJUSTE_REPORTE, 'SOLO PRODUCCION', 30);
        $this->pendiente(self::OTRO_TIPO, 'SOLO OTRO', 20);

        $admin = tap(User::factory()->create())->assignRole('admin');

        // Filtrar por el tipo real deja fuera al simulado.
        $this->actingAs($admin)
            ->get(route('admin.aprobaciones.index', ['tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE]))
            ->assertOk()
            ->assertSee('SOLO PRODUCCION')
            ->assertDontSee('SOLO OTRO');
    }
}
