<?php

namespace Tests\Feature\Aprobaciones;

use App\Models\Aprobacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P-M14-06: historial admin /admin/aprobaciones (permiso `view aprobaciones`).
 * Solo lectura: permiso, filtros (whereDate en el rango), resumen y paginación.
 */
class AprobacionHistorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_exige_el_permiso_view_aprobaciones(): void
    {
        // jefe_bodega tiene 'aprobar solicitudes' (bandeja) pero NO 'view
        // aprobaciones' (historial): el historial es solo del admin.
        $jefe = tap(User::factory()->create())->assignRole('jefe_bodega');
        $this->actingAs($jefe)->get(route('admin.aprobaciones.index'))->assertForbidden();

        $admin = tap(User::factory()->create())->assignRole('admin');
        $this->actingAs($admin)->get(route('admin.aprobaciones.index'))->assertOk();
    }

    public function test_lista_y_filtra_por_estado(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $this->fila('Ajuste aprobado', Aprobacion::ESTADO_APROBADA);
        $this->fila('Ajuste rechazado', Aprobacion::ESTADO_RECHAZADA);

        // Sin filtro: las dos.
        $this->actingAs($admin)->get(route('admin.aprobaciones.index'))
            ->assertOk()->assertSee('Ajuste aprobado')->assertSee('Ajuste rechazado');

        // Filtrada a rechazada: solo esa.
        $this->actingAs($admin)->get(route('admin.aprobaciones.index', ['estado' => Aprobacion::ESTADO_RECHAZADA]))
            ->assertOk()->assertSee('Ajuste rechazado')->assertDontSee('Ajuste aprobado');
    }

    public function test_el_filtro_de_rango_incluye_el_dia_hasta(): void
    {
        // Regresión del gotcha whereBetween (bitácora): una fila creada HOY a
        // una hora != 00:00 debe entrar con hasta=hoy. whereDate la incluye;
        // whereBetween('created_at', [hoy, hoy]) la dejaría fuera.
        $admin = tap(User::factory()->create())->assignRole('admin');
        $hoy = now()->toDateString();
        $f = $this->fila('Creada hoy 14:30', Aprobacion::ESTADO_APROBADA);
        $f->forceFill(['created_at' => $hoy.' 14:30:00'])->save();

        $this->actingAs($admin)
            ->get(route('admin.aprobaciones.index', ['desde' => $hoy, 'hasta' => $hoy]))
            ->assertOk()
            ->assertSee('Creada hoy 14:30');
    }

    public function test_filtra_por_solicitante_y_aprobador(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $pedro = tap(User::factory()->create(['name' => 'Pedro Pide']))->assignRole('vendedor');
        $lucia = tap(User::factory()->create(['name' => 'Lucía Aprueba']))->assignRole('admin');

        Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'estado' => Aprobacion::ESTADO_APROBADA,
            'motivo' => 'm', 'descripcion' => 'De Pedro por Lucía', 'rol_aprobador' => 'admin',
            'solicitante_id' => $pedro->id, 'resuelto_por' => $lucia->id, 'resuelta_at' => now(),
        ]);
        $this->fila('De otro', Aprobacion::ESTADO_APROBADA);

        $this->actingAs($admin)->get(route('admin.aprobaciones.index', ['solicitante_id' => $pedro->id]))
            ->assertOk()->assertSee('De Pedro por Lucía')->assertDontSee('De otro');

        $this->actingAs($admin)->get(route('admin.aprobaciones.index', ['resuelto_por' => $lucia->id]))
            ->assertOk()->assertSee('De Pedro por Lucía')->assertDontSee('De otro');
    }

    public function test_resumen_por_estado_cuenta_lo_filtrado(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        $this->fila('a', Aprobacion::ESTADO_APROBADA);
        $this->fila('b', Aprobacion::ESTADO_APROBADA);
        $this->fila('c', Aprobacion::ESTADO_RECHAZADA);

        $res = $this->actingAs($admin)->get(route('admin.aprobaciones.index'));
        $res->assertOk();
        $porEstado = $res->viewData('porEstado');
        $this->assertSame(2, $porEstado[Aprobacion::ESTADO_APROBADA]);
        $this->assertSame(1, $porEstado[Aprobacion::ESTADO_RECHAZADA]);
    }

    public function test_la_paginacion_preserva_los_filtros(): void
    {
        $admin = tap(User::factory()->create())->assignRole('admin');
        foreach (range(1, 30) as $i) {
            $this->fila("Fila {$i}", Aprobacion::ESTADO_APROBADA);
        }

        $this->actingAs($admin)
            ->get(route('admin.aprobaciones.index', ['estado' => Aprobacion::ESTADO_APROBADA]))
            ->assertOk()
            ->assertSee('estado='.Aprobacion::ESTADO_APROBADA); // el link de página conserva el filtro
    }

    private function fila(string $descripcion, string $estado): Aprobacion
    {
        return Aprobacion::create([
            'tipo_accion' => Aprobacion::ACCION_AJUSTE_REPORTE,
            'estado' => $estado,
            'monto' => 100,
            'motivo' => 'motivo',
            'descripcion' => $descripcion,
            'rol_aprobador' => 'admin',
        ]);
    }
}
