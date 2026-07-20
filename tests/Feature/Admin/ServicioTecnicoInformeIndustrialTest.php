<?php

namespace Tests\Feature\Admin;

use App\Models\AgendaTrabajo;
use App\Models\ServicioTerreno;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Informes de Servicio Técnico separados en dos "carpetas" (Dispensadores /
 * Industrial) + el informe INDUSTRIAL (agenda de terreno): uso de repuestos en
 * números, % por tipo de trabajo y servicios más usados. Los repuestos los
 * registra el técnico al cerrar el trabajo (PATCH estado=realizado).
 */
class ServicioTecnicoInformeIndustrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return tap(User::factory()->create())->assignRole('admin');
    }

    // --- Landing (dos carpetas) ---

    public function test_guest_es_redirigido_del_landing(): void
    {
        $this->get('/admin/servicio-tecnico/informe')->assertRedirect('/login');
    }

    public function test_sin_permiso_no_ve_el_landing(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/servicio-tecnico/informe')->assertForbidden();
    }

    public function test_landing_muestra_las_dos_carpetas(): void
    {
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe')
            ->assertOk()
            ->assertSee('Dispensadores')
            ->assertSee('Industrial')
            ->assertSee(route('admin.servicio-tecnico.informe.dispensadores'), false)
            ->assertSee(route('admin.servicio-tecnico.informe.industrial'), false);
    }

    // --- Informe industrial ---

    public function test_industrial_cuenta_por_tipo_y_servicios_del_periodo(): void
    {
        $svc = ServicioTerreno::factory()->create(['nombre' => 'Full planta 1T']);
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-10', 'tipo' => 'mantencion', 'estado' => 'agendado', 'servicio_terreno_id' => $svc->id]);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'tipo' => 'instalacion', 'estado' => 'realizado', 'servicio_terreno_id' => $svc->id]);
        // Fuera del período y cancelado: NO cuentan.
        AgendaTrabajo::factory()->create(['fecha' => '2026-06-01', 'tipo' => 'reparacion', 'estado' => 'agendado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-15', 'tipo' => 'reparacion', 'estado' => 'cancelado']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('total', 3)
            ->assertViewHas('porTipo', function (Collection $tipos) {
                return (int) $tipos->firstWhere('nombre', 'mantencion')?->cantidad === 2
                    && (int) $tipos->firstWhere('nombre', 'instalacion')?->cantidad === 1
                    && $tipos->firstWhere('nombre', 'reparacion') === null;   // cancelado/fuera de período
            })
            ->assertViewHas('topServicios', function (Collection $servicios) use ($svc) {
                return (int) $servicios->firstWhere('id', $svc->id)?->cantidad === 3;
            });
    }

    public function test_repuestos_se_registran_al_cerrar_y_se_cuentan_en_el_informe(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $trabajo = AgendaTrabajo::factory()->create(['fecha' => '2026-07-10', 'estado' => 'agendado']);

        // El técnico cierra el trabajo y registra repuestos (una fila vacía se descarta).
        $this->actingAs($tecnico)
            ->patch(route('admin.agenda-terreno.estado', $trabajo), [
                'estado' => 'realizado',
                'repuestos' => [
                    ['nombre' => 'Membrana', 'cantidad' => 2],
                    ['nombre' => 'Filtro de papel', 'cantidad' => 1],
                    ['nombre' => '', 'cantidad' => 3],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('realizado', $trabajo->fresh()->estado);
        $this->assertSame(2, $trabajo->repuestos()->count());
        $this->assertSame(3, (int) $trabajo->repuestos()->sum('cantidad'));

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('totalUnidadesRepuestos', 3)
            ->assertViewHas('totalNombresRepuestos', 2)
            ->assertViewHas('repuestos', function (Collection $r) {
                return (int) $r->firstWhere('nombre', 'Membrana')?->unidades === 2;
            });
    }

    public function test_cerrar_sin_repuestos_sigue_funcionando(): void
    {
        $tecnico = tap(User::factory()->create())->assignRole('tecnico_industrial');
        $trabajo = AgendaTrabajo::factory()->create(['fecha' => '2026-07-10', 'estado' => 'agendado']);

        $this->actingAs($tecnico)
            ->patch(route('admin.agenda-terreno.estado', $trabajo), ['estado' => 'realizado'])
            ->assertRedirect();

        $this->assertSame('realizado', $trabajo->fresh()->estado);
        $this->assertSame(0, $trabajo->repuestos()->count());
    }

    // --- Indicadores nuevos ---

    public function test_cumplimiento_realizados_vs_pendientes(): void
    {
        AgendaTrabajo::factory()->count(3)->create(['fecha' => '2026-07-10', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-11', 'estado' => 'agendado']);
        // Cancelado y solicitud sin fecha NO cuentan.
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'estado' => 'cancelado']);
        AgendaTrabajo::factory()->create(['fecha' => null, 'estado' => 'solicitado']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('total', 4)
            ->assertViewHas('realizados', 3)
            ->assertViewHas('pendientes', 1)
            ->assertViewHas('pctCumplimiento', 75);
    }

    public function test_clientes_que_mas_solicitan_agrupa_por_rut(): void
    {
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-10', 'estado' => 'realizado', 'cliente_rut' => '11111111-1', 'cliente_nombre' => 'Aguas Frecuentes']);
        AgendaTrabajo::factory()->create(['fecha' => '2026-07-12', 'estado' => 'agendado', 'cliente_rut' => '22222222-2', 'cliente_nombre' => 'Cliente Ocasional']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('topClientes', function (Collection $clientes) {
                $top = $clientes->first();

                return $clientes->count() === 2
                    && $top->cliente_rut === '11111111-1'
                    && (int) $top->cantidad === 2;
            });
    }

    public function test_visitas_tecnicas_cuenta_y_porcentaje(): void
    {
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-10', 'tipo' => 'visita_tecnica', 'estado' => 'realizado']);
        AgendaTrabajo::factory()->count(2)->create(['fecha' => '2026-07-11', 'tipo' => 'mantencion', 'estado' => 'agendado']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe/industrial?anio=2026&mes=7')
            ->assertOk()
            ->assertViewHas('visitas', 2)
            ->assertViewHas('visitasRealizadas', 2)
            ->assertViewHas('pctVisitas', 50);
    }
}
