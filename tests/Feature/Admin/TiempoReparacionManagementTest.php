<?php

namespace Tests\Feature\Admin;

use App\Models\TiempoReparacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Costos generales de reparación": catálogo de tiempos estándar por trabajo,
 * que fija la mano de obra. Solo jefatura/admin lo gestionan.
 */
class TiempoReparacionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_ventas');
    }

    // --- Acceso / gating ---

    public function test_guest_es_redirigido(): void
    {
        $this->get('/admin/tiempos-reparacion')->assertRedirect('/login');
    }

    public function test_tecnico_no_puede_gestionar_los_tiempos(): void
    {
        // El técnico de taller NO fija los tiempos (esa es la gracia): solo jefatura.
        $tecnico = tap(User::factory()->create())->assignRole('tecnico');

        $this->actingAs($tecnico)->get('/admin/tiempos-reparacion')->assertForbidden();
        $this->actingAs($tecnico)->post('/admin/tiempos-reparacion', [
            'trabajo' => 'X', 'horas' => '1',
        ])->assertForbidden();
    }

    public function test_jefe_ventas_gestiona(): void
    {
        $this->actingAs($this->jefe())->get('/admin/tiempos-reparacion')->assertOk();
        $this->actingAs($this->jefe())->get('/admin/tiempos-reparacion/create')->assertOk();
    }

    // --- CRUD ---

    public function test_crea_un_tiempo_con_coma_decimal(): void
    {
        $this->actingAs($this->jefe())
            ->post('/admin/tiempos-reparacion', [
                'trabajo' => 'Cambio de caldera — funciona normal',
                'horas' => '1,5',   // coma decimal chilena
                'grupo' => 'Reparada',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.tiempos-reparacion.index'));

        $this->assertDatabaseHas('tiempos_reparacion', [
            'trabajo' => 'Cambio de caldera — funciona normal',
            'horas' => 1.5,
        ]);
    }

    public function test_no_permite_trabajo_duplicado(): void
    {
        TiempoReparacion::factory()->create(['trabajo' => 'Cambio de caldera']);

        $this->actingAs($this->jefe())
            ->post('/admin/tiempos-reparacion', ['trabajo' => 'Cambio de caldera', 'horas' => '1'])
            ->assertSessionHasErrors('trabajo');
    }

    public function test_horas_es_obligatoria_y_numerica(): void
    {
        $this->actingAs($this->jefe())
            ->post('/admin/tiempos-reparacion', ['trabajo' => 'Algo', 'horas' => ''])
            ->assertSessionHasErrors('horas');
    }

    public function test_edita_las_horas(): void
    {
        $t = TiempoReparacion::factory()->create(['trabajo' => 'Cambio de filtro', 'horas' => 0.5]);

        $this->actingAs($this->jefe())
            ->put("/admin/tiempos-reparacion/{$t->id}", ['trabajo' => 'Cambio de filtro', 'horas' => '2', 'activo' => '1'])
            ->assertRedirect(route('admin.tiempos-reparacion.index'));

        $this->assertSame('2.0', (string) $t->fresh()->horas);
    }

    // --- Fuente de la mano de obra ---

    public function test_horas_de_devuelve_solo_los_activos(): void
    {
        TiempoReparacion::factory()->create(['trabajo' => 'Trabajo activo', 'horas' => 1.5, 'activo' => true]);
        TiempoReparacion::factory()->create(['trabajo' => 'Trabajo inactivo', 'horas' => 2.0, 'activo' => false]);

        $this->assertSame(1.5, TiempoReparacion::horasDe('Trabajo activo'));
        $this->assertNull(TiempoReparacion::horasDe('Trabajo inactivo'));
        $this->assertNull(TiempoReparacion::horasDe('No existe'));
        $this->assertNull(TiempoReparacion::horasDe(null));
    }
}
