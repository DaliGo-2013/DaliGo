<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermisosAgrupados;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los permisos se agrupan por dominio en la UI de Roles (config permissions.grupos).
 * Un permiso nuevo se deriva SOLO a su categoría por keyword; si no matcha ninguna,
 * cae en "Generales" (hasta que se agregue su categoría en el config).
 */
class PermisosAgrupadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_deriva_cada_permiso_a_su_categoria(): void
    {
        $this->assertSame('Servicio técnico', PermisosAgrupados::categoriaDe('manage servicio tecnico'));
        $this->assertSame('Servicio técnico', PermisosAgrupados::categoriaDe('aplicar descuento servicio tecnico'));
        $this->assertSame('Servicio técnico', PermisosAgrupados::categoriaDe('crear lote servicio'));
        $this->assertSame('Servicio técnico', PermisosAgrupados::categoriaDe('gestionar tiempos reparacion'));
        $this->assertSame('Terreno', PermisosAgrupados::categoriaDe('agendar servicio terreno'));
        $this->assertSame('Terreno', PermisosAgrupados::categoriaDe('ver agenda terreno'));
        $this->assertSame('Producción', PermisosAgrupados::categoriaDe('manage production'));
        $this->assertSame('Comercial', PermisosAgrupados::categoriaDe('manage productos'));
        $this->assertSame('Usuarios y accesos', PermisosAgrupados::categoriaDe('manage roles'));
        $this->assertSame('Notificaciones', PermisosAgrupados::categoriaDe('view notificaciones'));
        // Dominio nuevo sin categoría todavía → Generales (no se pierde).
        $this->assertSame('Generales', PermisosAgrupados::categoriaDe('gestionar bodega central'));
    }

    public function test_agrupa_todos_los_permisos_sembrados_sin_perder_ninguno(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $todos = Permission::all();

        $grupos = PermisosAgrupados::agrupar($todos);

        // No se pierde ni se duplica ningún permiso al agrupar.
        $totalAgrupados = collect($grupos)->sum(fn ($c) => $c->count());
        $this->assertSame($todos->count(), $totalAgrupados);

        $this->assertArrayHasKey('Servicio técnico', $grupos);
        $this->assertArrayHasKey('Producción', $grupos);
        $this->assertTrue($grupos['Servicio técnico']->contains(fn ($p) => $p->name === 'manage servicio tecnico'));
    }

    public function test_la_edicion_de_rol_muestra_las_categorias(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $role = Role::findByName('tecnico');

        $this->actingAs($admin)
            ->get(route('admin.roles.edit', $role))
            ->assertOk()
            ->assertSee('Servicio técnico')   // encabezado de categoría
            ->assertSee('Producción');
    }
}
