<?php

namespace Tests\Feature\Admin;

use App\Models\ProduccionAsignacion;
use App\Models\ProduccionReporte;
use App\Models\TipoBotellon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TipoBotellonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipoBotellonManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function jefe(): User
    {
        return tap(User::factory()->create())->assignRole('jefe_bodega');
    }

    // --- Acceso / gating ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/tipos-botellon')->assertRedirect('/login');
    }

    public function test_member_sin_permiso_es_rechazado(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)->get('/admin/tipos-botellon')->assertForbidden();
    }

    public function test_jefe_bodega_ve_el_listado(): void
    {
        $this->actingAs($this->jefe())->get('/admin/tipos-botellon')->assertOk();
    }

    // --- CRUD ---

    public function test_jefe_crea_tipo(): void
    {
        $this->actingAs($this->jefe())->post('/admin/tipos-botellon', [
            'nombre' => 'Azul 20L c/manilla',
            'codigo' => 'AZUL-20L-MANILLA',
            'activo' => '1',
        ])->assertRedirect(route('admin.tipos-botellon.index'));

        $this->assertDatabaseHas('tipos_botellon', [
            'codigo' => 'AZUL-20L-MANILLA',
            'nombre' => 'Azul 20L c/manilla',
            'activo' => true,
        ]);
    }

    public function test_crear_exige_nombre_y_codigo(): void
    {
        $this->actingAs($this->jefe())->post('/admin/tipos-botellon', [])
            ->assertSessionHasErrors(['nombre', 'codigo']);
    }

    public function test_codigo_duplicado_es_rechazado(): void
    {
        TipoBotellon::create(['codigo' => 'AZUL-20L', 'nombre' => 'Azul 20L']);

        $this->actingAs($this->jefe())->post('/admin/tipos-botellon', [
            'nombre' => 'Otro',
            'codigo' => 'AZUL-20L',
        ])->assertSessionHasErrors('codigo');
    }

    public function test_jefe_actualiza_tipo(): void
    {
        $tipo = TipoBotellon::create(['codigo' => 'AZUL-20L', 'nombre' => 'Azul 20L']);

        $this->actingAs($this->jefe())->put("/admin/tipos-botellon/{$tipo->id}", [
            'nombre' => 'Azul 20L s/manilla',
            'codigo' => 'AZUL-20L',
            'activo' => '1',
        ])->assertRedirect(route('admin.tipos-botellon.index'));

        $this->assertSame('Azul 20L s/manilla', $tipo->fresh()->nombre);
    }

    public function test_eliminar_tipo_sin_registros(): void
    {
        $tipo = TipoBotellon::create(['codigo' => 'TEMP', 'nombre' => 'Temporal']);

        $this->actingAs($this->jefe())->delete("/admin/tipos-botellon/{$tipo->id}");

        $this->assertDatabaseMissing('tipos_botellon', ['codigo' => 'TEMP']);
    }

    public function test_no_se_elimina_tipo_con_produccion_registrada(): void
    {
        $tipo = TipoBotellon::create(['codigo' => 'USADO', 'nombre' => 'Usado']);
        $soplador = tap(User::factory()->create())->assignRole('soplador');
        $asignacion = ProduccionAsignacion::create([
            'soplador_id' => $soplador->id, 'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100,
        ]);
        $reporte = ProduccionReporte::create([
            'asignacion_id' => $asignacion->id, 'soplador_id' => $soplador->id,
            'fecha' => now()->toDateString(), 'turno' => 'dia', 'asignadas' => 100, 'estado' => 'borrador',
        ]);
        $reporte->registros()->create(['tipo_botellon_id' => $tipo->id, 'primera' => 5]);

        $this->actingAs($this->jefe())->delete("/admin/tipos-botellon/{$tipo->id}");

        $this->assertDatabaseHas('tipos_botellon', ['codigo' => 'USADO']);
    }

    // --- Seeder ---

    public function test_seeder_crea_tipos_base_idempotente(): void
    {
        $this->seed(TipoBotellonSeeder::class);
        $this->seed(TipoBotellonSeeder::class); // re-ejecutar no duplica

        $this->assertSame(4, TipoBotellon::count());
        $this->assertDatabaseHas('tipos_botellon', ['codigo' => 'AZUL-20L-MANILLA', 'activo' => true]);
    }

    public function test_seeder_no_pisa_renombres_del_admin(): void
    {
        $this->seed(TipoBotellonSeeder::class);
        TipoBotellon::where('codigo', 'AZUL-20L')->first()->update(['nombre' => 'Azul 20 litros liso']);

        $this->seed(TipoBotellonSeeder::class);

        $this->assertDatabaseHas('tipos_botellon', ['codigo' => 'AZUL-20L', 'nombre' => 'Azul 20 litros liso']);
        $this->assertSame(4, TipoBotellon::count());
    }
}
