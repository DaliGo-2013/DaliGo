<?php

namespace Tests\Feature\Admin;

use App\Models\Maquina;
use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaquinaManagementTest extends TestCase
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

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    private function sucursal(string $codigo = 'MIRADOR'): Sucursal
    {
        return Sucursal::firstOrCreate(['codigo' => $codigo], ['nombre' => ucfirst(strtolower($codigo))]);
    }

    // --- Acceso / gating ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/maquinas')->assertRedirect('/login');
    }

    public function test_member_sin_permiso_es_rechazado(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)->get('/admin/maquinas')->assertForbidden();
        $this->actingAs($member)->post('/admin/maquinas', ['nombre' => 'X'])->assertForbidden();
    }

    public function test_soplador_no_gestiona_maquinas(): void
    {
        $soplador = tap(User::factory()->create())->assignRole('soplador');

        $this->actingAs($soplador)->get('/admin/maquinas')->assertForbidden();
    }

    public function test_jefe_bodega_y_admin_ven_el_listado(): void
    {
        $this->actingAs($this->jefe())->get('/admin/maquinas')->assertOk();
        $this->actingAs($this->admin())->get('/admin/maquinas')->assertOk();
    }

    // --- CRUD ---

    public function test_jefe_crea_maquina(): void
    {
        $sucursal = $this->sucursal();

        $this->actingAs($this->jefe())->post('/admin/maquinas', [
            'nombre' => 'Sopladora 1',
            'sucursal_id' => $sucursal->id,
            'activa' => '1',
        ])->assertRedirect(route('admin.maquinas.index'));

        $this->assertDatabaseHas('maquinas', [
            'nombre' => 'Sopladora 1',
            'sucursal_id' => $sucursal->id,
            'activa' => true,
        ]);
    }

    public function test_crear_exige_nombre_y_sucursal(): void
    {
        $this->actingAs($this->jefe())->post('/admin/maquinas', [])
            ->assertSessionHasErrors(['nombre', 'sucursal_id']);
    }

    public function test_nombre_duplicado_en_la_misma_sucursal_es_rechazado(): void
    {
        $sucursal = $this->sucursal();
        Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $sucursal->id]);

        $this->actingAs($this->jefe())->post('/admin/maquinas', [
            'nombre' => 'Sopladora 1',
            'sucursal_id' => $sucursal->id,
        ])->assertSessionHasErrors('nombre');
    }

    public function test_mismo_nombre_en_otra_sucursal_es_valido(): void
    {
        Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $this->sucursal()->id]);
        $otra = $this->sucursal('COQUIMBO');

        $this->actingAs($this->jefe())->post('/admin/maquinas', [
            'nombre' => 'Sopladora 1',
            'sucursal_id' => $otra->id,
        ])->assertRedirect(route('admin.maquinas.index'));

        $this->assertSame(2, Maquina::where('nombre', 'Sopladora 1')->count());
    }

    public function test_jefe_actualiza_maquina(): void
    {
        $maquina = Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $this->sucursal()->id]);

        $this->actingAs($this->jefe())->put("/admin/maquinas/{$maquina->id}", [
            'nombre' => 'Sopladora 1B',
            'sucursal_id' => $maquina->sucursal_id,
            'activa' => '1',
        ])->assertRedirect(route('admin.maquinas.index'));

        $this->assertSame('Sopladora 1B', $maquina->fresh()->nombre);
    }

    public function test_eliminar_maquina_sin_registros(): void
    {
        $maquina = Maquina::create(['nombre' => 'Temporal', 'sucursal_id' => $this->sucursal()->id]);

        $this->actingAs($this->jefe())->delete("/admin/maquinas/{$maquina->id}");

        $this->assertDatabaseMissing('maquinas', ['nombre' => 'Temporal']);
    }

    public function test_no_se_elimina_maquina_con_produccion_registrada(): void
    {
        $maquina = Maquina::create(['nombre' => 'Con historia', 'sucursal_id' => $this->sucursal()->id]);
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100, 'estado' => 'borrador',
        ]);
        $reporte->registros()->create(['maquina_id' => $maquina->id, 'primera' => 10]);

        $this->actingAs($this->jefe())->delete("/admin/maquinas/{$maquina->id}");

        $this->assertDatabaseHas('maquinas', ['nombre' => 'Con historia']);
    }

    public function test_no_se_elimina_sucursal_con_maquinas(): void
    {
        $sucursal = $this->sucursal('BUZETA');
        Maquina::create(['nombre' => 'Sopladora 1', 'sucursal_id' => $sucursal->id]);

        $this->actingAs($this->admin())->delete("/admin/sucursales/{$sucursal->id}");

        $this->assertDatabaseHas('sucursales', ['codigo' => 'BUZETA']);
    }
}
