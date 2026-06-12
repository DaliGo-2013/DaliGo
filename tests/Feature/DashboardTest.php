<?php

namespace Tests\Feature;

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
