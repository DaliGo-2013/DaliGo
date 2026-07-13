<?php

namespace Tests\Feature;

use App\Models\OrdenServicio;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_sees_indicadores_and_all_quick_access_groups(): void
    {
        Producto::factory()->create(['activo' => true, 'peso_kg' => null]); // sin medidas

        $res = $this->actingAs($this->userWithRole('admin'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Reportes por revisar')
            ->assertSee('Productos sin medidas')
            ->assertSee('Comercial')
            ->assertSee('Operación')
            ->assertSee('Administración')
            ->assertSee('Auditoría');
    }

    public function test_soplador_sees_cta_but_no_admin_blocks(): void
    {
        $res = $this->actingAs($this->userWithRole('soplador'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Tu reporte de producción')
            ->assertSee('Mi producción')
            ->assertDontSee('Reportes por revisar')
            ->assertDontSee('Administración')
            ->assertDontSee('Usuarios');
    }

    public function test_tecnico_ve_cards_por_estado_con_conteos_y_enlaces(): void
    {
        OrdenServicio::factory()->count(2)->create(['estado' => 'cotizacion']);
        OrdenServicio::factory()->create(['estado' => 'reparado']);
        OrdenServicio::factory()->count(3)->create(['estado' => 'entregado']);
        OrdenServicio::factory()->create(['estado' => 'sin_solucion']);
        OrdenServicio::factory()->create(['estado' => 'recibido']);

        $res = $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Equipos en taller')
            ->assertSee('Cotización (espera cliente)')
            ->assertSee('Reparado')
            ->assertSee('Entregado')
            ->assertSee('Sin solución')
            // enlaces al listado filtrado por estado
            ->assertSee(route('admin.servicio-tecnico.index', ['estado' => 'cotizacion']), false)
            ->assertSee(route('admin.servicio-tecnico.index', ['estado' => 'sin_solucion']), false);

        // "Equipos en taller" = todo lo que no está entregado (2+1+1+1 = 5).
        $indicadores = collect($res->viewData('indicadores'))->keyBy('label');
        $this->assertSame(5, $indicadores['Equipos en taller']['valor']);
        $this->assertSame(2, $indicadores['Cotización (espera cliente)']['valor']);
        $this->assertSame(3, $indicadores['Entregado']['valor']);
    }

    public function test_barra_muestra_contador_de_pendientes_de_servicio_tecnico(): void
    {
        // Pendientes = todos los estados activos (recibido, en_revision, cotizacion,
        // esperando_repuesto, reparado). Solo entregado y sin_solucion NO cuentan.
        OrdenServicio::factory()->count(2)->create(['estado' => 'recibido']);
        OrdenServicio::factory()->create(['estado' => 'cotizacion']);
        OrdenServicio::factory()->create(['estado' => 'reparado']);       // ahora SÍ cuenta
        OrdenServicio::factory()->create(['estado' => 'entregado']);      // no cuenta
        OrdenServicio::factory()->create(['estado' => 'sin_solucion']);   // no cuenta

        $this->assertSame(4, OrdenServicio::pendientesTecnico()->count());

        // El técnico ve el badge con el número en la barra.
        $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard')
            ->assertOk()
            ->assertSee('4 equipo(s) por atender');
    }

    public function test_barra_no_muestra_contador_a_rol_sin_acceso_a_servicio_tecnico(): void
    {
        OrdenServicio::factory()->count(2)->create(['estado' => 'recibido']);

        // Un rol sin permiso de servicio técnico no ve el link ni el contador.
        $this->actingAs($this->userWithRole('soplador'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('equipo(s) por atender');
    }

    public function test_barra_no_muestra_badge_si_no_hay_pendientes(): void
    {
        OrdenServicio::factory()->create(['estado' => 'entregado']);   // no pendiente

        $this->actingAs($this->userWithRole('tecnico'))->get('/dashboard')
            ->assertOk()
            ->assertDontSee('equipo(s) por atender');
    }

    public function test_member_sees_only_greeting(): void
    {
        $res = $this->actingAs($this->userWithRole('member'))->get('/dashboard');

        $res->assertOk()
            ->assertSee('Bienvenido')
            ->assertDontSee('Tu reporte de producción')
            ->assertDontSee('Comercial')
            ->assertDontSee('Administración');
    }

    public function test_jefe_bodega_sees_pending_reports_count(): void
    {
        $soplador = $this->userWithRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
        ]);
        ProduccionReporte::create([
            'asignacion_id' => $asignacion->id,
            'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(),
            'turno' => 'dia',
            'asignadas' => 100,
            'estado' => ProduccionReporte::ENVIADO,
        ]);

        $res = $this->actingAs($this->userWithRole('jefe_bodega'))->get('/dashboard');

        $res->assertOk()->assertSee('Reportes por revisar');
        $this->assertSame(1, ProduccionReporte::pendientes()->count());
    }
}
