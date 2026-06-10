<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Testea la MIGRACION de datos reconcile_business_roles. En el flujo normal de
 * RefreshDatabase la migracion corre sobre una BD vacia (no-op), asi que aqui
 * se reconstruye el estado legacy a mano y se ejecuta up() directamente.
 */
class ReconcileBusinessRolesTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_06_10_120000_reconcile_business_roles.php');
        $migration->up();
    }

    public function test_renombra_soplador_preservando_asignacion(): void
    {
        Permission::firstOrCreate(['name' => 'report production', 'guard_name' => 'web']);
        $legacy = Role::create(['name' => 'Soplador', 'guard_name' => 'web']);
        $legacy->givePermissionTo('report production');
        $legacyId = $legacy->id;

        $user = User::factory()->create();
        $user->assignRole('Soplador');

        $this->runMigration();

        // Mismo role_id, nuevo nombre: la asignacion y el permiso siguen colgados.
        $this->assertDatabaseHas('roles', ['id' => $legacyId, 'name' => 'soplador']);
        $this->assertTrue($user->fresh()->hasRole('soplador'));
        $this->assertTrue(Role::findByName('soplador')->hasPermissionTo('report production'));
    }

    public function test_consolida_jefatura_en_jefe_bodega_reasignando_usuarios(): void
    {
        Permission::firstOrCreate(['name' => 'manage production', 'guard_name' => 'web']);
        $jefatura = Role::create(['name' => 'Jefatura', 'guard_name' => 'web']);
        $jefatura->givePermissionTo('manage production');
        Role::create(['name' => 'jefe_bodega', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('Jefatura');

        $this->runMigration();

        $this->assertDatabaseMissing('roles', ['name' => 'Jefatura']);
        $this->assertTrue($user->fresh()->hasRole('jefe_bodega')); // reasignado, no perdido
    }

    public function test_jefatura_se_renombra_si_jefe_bodega_no_existe(): void
    {
        $jefatura = Role::create(['name' => 'Jefatura', 'guard_name' => 'web']);
        $jefaturaId = $jefatura->id;

        $this->runMigration();

        $this->assertDatabaseHas('roles', ['id' => $jefaturaId, 'name' => 'jefe_bodega']);
    }

    public function test_borra_ventas_huerfano_sin_usuarios(): void
    {
        Role::create(['name' => 'Ventas', 'guard_name' => 'web']);

        $this->runMigration();

        $this->assertDatabaseMissing('roles', ['name' => 'Ventas']);
    }

    public function test_conserva_ventas_si_tiene_usuarios(): void
    {
        Role::create(['name' => 'Ventas', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Ventas');

        $this->runMigration();

        $this->assertDatabaseHas('roles', ['name' => 'Ventas']);
        $this->assertTrue($user->fresh()->hasRole('Ventas'));
    }

    public function test_es_idempotente(): void
    {
        Role::create(['name' => 'Soplador', 'guard_name' => 'web']);
        Role::create(['name' => 'Jefatura', 'guard_name' => 'web']);
        Role::create(['name' => 'jefe_bodega', 'guard_name' => 'web']);
        Role::create(['name' => 'Ventas', 'guard_name' => 'web']);

        $this->runMigration();
        $this->runMigration(); // segunda pasada: no debe explotar ni duplicar

        $this->assertSame(2, Role::count()); // soplador + jefe_bodega
        $this->assertDatabaseHas('roles', ['name' => 'soplador']);
        $this->assertDatabaseHas('roles', ['name' => 'jefe_bodega']);
    }

    public function test_en_bd_vacia_es_noop(): void
    {
        $this->runMigration();

        $this->assertSame(0, Role::count());
    }
}
