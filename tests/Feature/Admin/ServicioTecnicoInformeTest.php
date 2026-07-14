<?php

namespace Tests\Feature\Admin;

use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Informe de estadisticas de Servicio Tecnico por periodo (año o mes):
 * KPIs (total / garantia / reparacion), desgloses por tipo, estado, equipo
 * del catalogo y cliente, y repuestos usados (apoyo al inventario).
 */
class ServicioTecnicoInformeTest extends TestCase
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

    private function userWith(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return tap(User::factory()->create())->assignRole($role);
    }

    // --- Acceso ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/servicio-tecnico/informe')->assertRedirect('/login');
    }

    public function test_member_without_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/servicio-tecnico/informe')->assertForbidden();
    }

    public function test_view_permission_puede_ver_el_informe(): void
    {
        // Los jefes (solo lectura) ven el informe sin permisos de gestion.
        $this->actingAs($this->userWith(['view servicio tecnico']))
            ->get('/admin/servicio-tecnico/informe')->assertOk();
    }

    // --- KPIs y período ---

    public function test_kpis_cuentan_solo_las_ordenes_del_periodo(): void
    {
        OrdenServicio::factory()->count(2)->create(['fecha_ingreso' => '2026-06-10', 'facturacion' => 'garantia']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-06-20', 'facturacion' => 'reparacion']);
        // Fuera del período (mayo): no debe contarse.
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-05-15', 'facturacion' => 'reparacion']);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('kpis.total', 3)
            ->assertViewHas('kpis.garantias', 2)
            ->assertViewHas('kpis.reparaciones', 1)
            ->assertViewHas('kpis.pctGarantia', 67);
    }

    public function test_todo_el_anio_agrega_los_meses(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-01-10']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-11-20']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2025-12-31']);

        // Solo anio (sin mes) = el año completo; el 2025 queda fuera.
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026')
            ->assertOk()
            ->assertViewHas('kpis.total', 2)
            ->assertViewHas('periodoLabel', 'Año 2026');
    }

    public function test_sin_parametros_usa_el_mes_actual(): void
    {
        OrdenServicio::factory()->create(['fecha_ingreso' => now()->toDateString()]);
        OrdenServicio::factory()->create(['fecha_ingreso' => now()->subYear()->toDateString()]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe')
            ->assertOk()
            ->assertViewHas('kpis.total', 1)
            ->assertViewHas('anio', now()->year)
            ->assertViewHas('mes', now()->month);
    }

    // --- Desgloses ---

    public function test_top_clientes_agrupa_por_rut(): void
    {
        OrdenServicio::factory()->count(2)->create([
            'fecha_ingreso' => '2026-06-10',
            'cliente_rut' => '11111111-1',
            'cliente_nombre' => 'Cliente Frecuente',
        ]);
        OrdenServicio::factory()->create([
            'fecha_ingreso' => '2026-06-12',
            'cliente_rut' => '22222222-2',
            'cliente_nombre' => 'Cliente Ocasional',
        ]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('topClientes', function (Collection $clientes) {
                $top = $clientes->first();

                return $clientes->count() === 2
                    && $top->cliente_rut === '11111111-1'
                    && (int) $top->cantidad === 2;
            });
    }

    public function test_top_clientes_sin_rut_cuentan_por_nombre(): void
    {
        // Sin RUT (muchas órdenes históricas): deben contar por NOMBRE, no
        // juntarse todos en una sola bolsa inflando a un cliente.
        OrdenServicio::factory()->count(2)->create([
            'fecha_ingreso' => '2026-06-10', 'cliente_rut' => null, 'cliente_nombre' => 'Zzz Cliente',
        ]);
        OrdenServicio::factory()->create([
            'fecha_ingreso' => '2026-06-11', 'cliente_rut' => null, 'cliente_nombre' => 'Aaa Cliente',
        ]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('topClientes', function (Collection $clientes) {
                $zzz = $clientes->firstWhere('nombre', 'Zzz Cliente');

                return $clientes->count() === 2 && $zzz && (int) $zzz->cantidad === 2;
            });
    }

    public function test_desglose_por_tipo_y_top_equipos_del_catalogo(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Dispensador D-100', 'sku' => '1030034']);
        OrdenServicio::factory()->count(2)->create([
            'fecha_ingreso' => '2026-06-10', 'tipo_equipo' => 'dispensador', 'producto_id' => $producto->id,
        ]);
        OrdenServicio::factory()->create([
            'fecha_ingreso' => '2026-06-12', 'tipo_equipo' => 'herramienta', 'producto_id' => null,
        ]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('porTipo', function (Collection $tipos) {
                return $tipos->firstWhere('nombre', 'dispensador')?->cantidad == 2
                    && $tipos->firstWhere('nombre', 'herramienta')?->cantidad == 1;
            })
            ->assertViewHas('topEquipos', function (Collection $equipos) use ($producto) {
                $top = $equipos->first();

                // El más ingresado es el producto del catálogo; el ingreso sin
                // código queda como fila aparte (id null → "Sin código").
                return (int) $top->id === $producto->id
                    && $top->nombre === 'Dispensador D-100'
                    && (int) $top->cantidad === 2
                    && $equipos->contains(fn ($e) => $e->id === null && (int) $e->cantidad === 1);
            });
    }

    public function test_filtra_todos_los_indicadores_por_tipo_de_equipo(): void
    {
        OrdenServicio::factory()->count(2)->create(['fecha_ingreso' => '2026-06-10', 'tipo_equipo' => 'bomba']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-06-12', 'tipo_equipo' => 'dispensador']);

        // Filtrado a bomba: solo cuenta las 2 bombas.
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6&tipo=bomba')
            ->assertOk()
            ->assertViewHas('kpis.total', 2)
            ->assertViewHas('tipo', 'bomba')
            ->assertViewHas('tipoLabel', 'Bomba de agua');

        // Sin tipo = todos (las 3).
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('kpis.total', 3)
            ->assertViewHas('tipo', null)
            ->assertViewHas('tipoLabel', 'Todos los equipos');
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?tipo=camion')
            ->assertSessionHasErrors('tipo');
    }

    public function test_desglose_por_causa_de_falla(): void
    {
        OrdenServicio::factory()->count(2)->create(['fecha_ingreso' => '2026-06-10', 'causa_falla' => 'mal_uso']);
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-06-12', 'causa_falla' => 'uso_normal']);
        // Sin diagnosticar => se agrupa como "sin_determinar".
        OrdenServicio::factory()->create(['fecha_ingreso' => '2026-06-15', 'causa_falla' => null]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('porCausa', function ($causas) {
                $porClave = $causas->keyBy('causa');

                return (int) $porClave['mal_uso']->cantidad === 2
                    && (int) $porClave['uso_normal']->cantidad === 1
                    && (int) $porClave['sin_determinar']->cantidad === 1;
            });
    }

    // --- Repuestos ---

    public function test_repuestos_se_agregan_por_periodo(): void
    {
        $enPeriodo1 = OrdenServicio::factory()->create(['fecha_ingreso' => '2026-06-10']);
        $enPeriodo2 = OrdenServicio::factory()->create(['fecha_ingreso' => '2026-06-20']);
        $fuera = OrdenServicio::factory()->create(['fecha_ingreso' => '2026-05-01']);

        $enPeriodo1->repuestos()->create(['nombre' => 'Placa electrica', 'cantidad' => 2, 'precio_unitario' => 10000]);
        $enPeriodo2->repuestos()->create(['nombre' => 'Placa electrica', 'cantidad' => 1, 'precio_unitario' => 10000]);
        $enPeriodo2->repuestos()->create(['nombre' => 'Motor', 'cantidad' => 1, 'precio_unitario' => 25000]);
        $fuera->repuestos()->create(['nombre' => 'Caldera', 'cantidad' => 5, 'precio_unitario' => 8000]);

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=2026&mes=6')
            ->assertOk()
            ->assertViewHas('repuestos', function (Collection $repuestos) {
                $placa = $repuestos->firstWhere('nombre', 'Placa electrica');

                // La caldera es de una orden fuera del período: no aparece.
                return $repuestos->count() === 2
                    && (int) $placa->unidades === 3
                    && (int) $placa->ordenes === 2
                    && $repuestos->firstWhere('nombre', 'Caldera') === null;
            })
            ->assertViewHas('totalUnidadesRepuestos', 4)
            ->assertViewHas('totalNombresRepuestos', 2);
    }

    // --- Validación ---

    public function test_rechaza_periodo_invalido(): void
    {
        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?mes=13')
            ->assertSessionHasErrors('mes');

        $this->actingAs($this->admin())->get('/admin/servicio-tecnico/informe?anio=1999')
            ->assertSessionHasErrors('anio');
    }
}
