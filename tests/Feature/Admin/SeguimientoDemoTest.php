<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Boceto interno de la vista de seguimiento (estilo Blue Express). Es estático
 * (datos de ejemplo, sin BD); el test solo cubre acceso y que rinda las etapas.
 */
class SeguimientoDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_redirige_a_login(): void
    {
        $this->get('/admin/servicio-tecnico/seguimiento-demo')->assertRedirect('/login');
    }

    public function test_sin_permiso_es_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/servicio-tecnico/seguimiento-demo')->assertForbidden();
    }

    public function test_staff_ve_el_boceto_con_las_etapas(): void
    {
        // 'view servicio tecnico' (solo lectura) basta: es la misma puerta del listado.
        $user = tap(User::factory()->create())->givePermissionTo('view servicio tecnico');

        $this->actingAs($user)
            ->get('/admin/servicio-tecnico/seguimiento-demo')
            ->assertOk()
            ->assertSee('Seguimiento de tu equipo')
            ->assertSee('ST-YQUW6P4E')
            ->assertSee('Boceto')
            ->assertSee('En Revision')       // etapa normal (Str::headline)
            ->assertSee('Reparado')
            ->assertDontSee('Esperando Repuesto') // etapa quitada del seguimiento del cliente
            ->assertSee('Sin Solucion');     // cierre negativo (escenario alterno)
    }
}
