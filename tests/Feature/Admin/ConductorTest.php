<?php

namespace Tests\Feature\Admin;

use App\Models\Conductor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Administración de conductores (choferes de ruta): CRUD sin borrar (se
 * desactivan). Solo los activos alimentan el selector del ingreso por lote.
 */
class ConductorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function gestor(): User
    {
        // 'manage servicio tecnico' gestiona el catálogo de conductores.
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions(['manage servicio tecnico']);

        return tap(User::factory()->create())->assignRole($role);
    }

    public function test_sin_permiso_es_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/conductores')->assertForbidden();
    }

    public function test_gestor_ve_el_listado_y_crea(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor)->get('/admin/conductores')->assertOk();

        $this->actingAs($gestor)
            ->post('/admin/conductores', ['nombre' => 'Nuevo Chofer', 'activo' => '1'])
            ->assertRedirect(route('admin.conductores.index'));

        $this->assertDatabaseHas('conductores', ['nombre' => 'Nuevo Chofer', 'activo' => true]);
    }

    public function test_nombre_es_obligatorio_y_unico(): void
    {
        Conductor::create(['nombre' => 'Ariel Hernández', 'activo' => true]);

        $this->actingAs($this->gestor())
            ->post('/admin/conductores', ['nombre' => ''])
            ->assertSessionHasErrors('nombre');

        $this->actingAs($this->gestor())
            ->post('/admin/conductores', ['nombre' => 'Ariel Hernández'])
            ->assertSessionHasErrors('nombre');
    }

    public function test_editar_y_desactivar(): void
    {
        $c = Conductor::create(['nombre' => 'Rodrigo Escobar', 'activo' => true]);

        $this->actingAs($this->gestor())
            ->put(route('admin.conductores.update', $c), ['nombre' => 'Rodrigo Escobar', 'activo' => '0'])
            ->assertRedirect(route('admin.conductores.index'));

        $this->assertFalse($c->fresh()->activo);
    }

    public function test_solo_los_activos_alimentan_el_selector_del_lote(): void
    {
        Conductor::create(['nombre' => 'Activo Uno', 'activo' => true]);
        Conductor::create(['nombre' => 'Inactivo Dos', 'activo' => false]);

        // El scope activos es la fuente del selector; el inactivo no aparece.
        $activos = Conductor::activos()->pluck('nombre')->all();
        $this->assertContains('Activo Uno', $activos);
        $this->assertNotContains('Inactivo Dos', $activos);
    }
}
