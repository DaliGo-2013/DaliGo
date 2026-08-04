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
            ->assertSee('Servicio técnico')   // nombre del área en su tarjeta
            ->assertSee('Producción');
    }

    /**
     * Candado del POST: las áreas arrancan CERRADAS y solo se ocultan en el
     * cliente (`x-show`), así que el HTML tiene que traer las casillas de TODAS
     * ellas. Si el panel pasara a renderizarse condicionalmente EN EL SERVIDOR
     * —un `@if` de Blade alrededor del panel, o un `<template x-if>`— guardar el
     * rol con un área cerrada BORRARÍA sus permisos sin avisar: el navegador no
     * envía lo que no existe en el DOM.
     *
     * Mutación verificada (31-jul-2026): envolver el panel en `@if ($loop->first)`
     * pone este test ROJO. Ojo con la mutación que NO sirve: cambiar `x-show` por
     * `x-if` sobre el `<div>` es INERTE —Alpine solo honra `x-if` en `<template>`—
     * así que el HTML del servidor no cambia y el test sigue verde con razón.
     */
    public function test_la_edicion_renderiza_las_casillas_de_todas_las_areas(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');
        $role = Role::findByName('tecnico');

        $respuesta = $this->actingAs($admin)
            ->get(route('admin.roles.edit', $role))
            ->assertOk();

        $grupos = PermisosAgrupados::agrupar(Permission::all());
        // Más de un área: si no, el test pasaría sin probar nada.
        $this->assertGreaterThan(1, count($grupos));

        foreach (Permission::all() as $permiso) {
            $respuesta->assertSee('value="'.$permiso->name.'"', false);
        }
    }

    /**
     * Las áreas se eligen en una rejilla de tarjetas que ENVUELVE. La fila de
     * pestañas anterior necesitaba scroll horizontal y escondía áreas enteras
     * sin avisar (feedback del dueño, 31-jul-2026): un contenedor de permisos
     * con scroll lateral es la regresión que no queremos de vuelta.
     */
    public function test_las_areas_no_se_eligen_en_una_franja_con_scroll_lateral(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = tap(User::factory()->create())->assignRole('admin');

        $html = $this->actingAs($admin)
            ->get(route('admin.roles.edit', Role::findByName('tecnico')))
            ->assertOk()
            ->assertSee('Seleccionar todo en esta área')
            ->getContent();

        $bloque = substr($html, (int) strpos($html, 'Áreas'));
        $this->assertStringNotContainsString('overflow-x-auto', $bloque);
        $this->assertStringContainsString('grid-cols-2', $bloque);
    }
}
