<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\PermisosSoloAdmin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ADMINISTRACIÓN es solo del admin (dueño, 27-08-2026).
 *
 * Dos reglas que se apoyan una en la otra:
 *
 *  1. El jefe de ventas no ve el módulo ADMINISTRACIÓN. Le aparecía porque
 *     llevaba `view users`, y la visibilidad de un módulo se deriva de sus ítems:
 *     Usuarios era el único para el que calificaba.
 *  2. Los permisos que REPARTEN permisos no los puede llevar ningún rol salvo
 *     `admin`, y no por convención sino por construcción — la pantalla de Roles
 *     los descarta del POST y los dibuja bloqueados.
 *
 * La segunda es la que importa de verdad, porque la primera sin la segunda es una
 * puerta cerrada al lado de una ventana abierta: bastaba con marcarle «Editar
 * usuarios» a un rol para que alguien se asignara `admin` a sí mismo.
 */
class PermisosSoloAdminTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRACION = 'migrations/2026_08_27_120000_administracion_solo_para_admin.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    // 1. El menú.

    public function test_el_jefe_de_ventas_no_ve_administracion_ni_entra_a_usuarios(): void
    {
        $jefe = $this->usuario('jefe_ventas');

        // El ítem no se le ofrece...
        $this->actingAs($jefe)->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('admin.users.index'))
            ->assertDontSee('Administración');

        // ...y la ruta tampoco lo deja pasar: el gate del menú y el de la ruta son
        // el mismo (D-014), o si no el menú ofrecería un 403.
        $this->actingAs($jefe)->get('/admin/users')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
    }

    /**
     * Y lo que el jefe de ventas SÍ hace sigue en pie. Es la otra mitad: sin esto,
     * el candado de arriba se cumple igual con el rol vacío.
     */
    public function test_el_jefe_de_ventas_conserva_su_trabajo(): void
    {
        $rol = Role::findByName('jefe_ventas');

        foreach ([
            'view servicio tecnico',
            'aplicar descuento servicio tecnico',
            'agendar servicio terreno',
            'aprobar solicitudes',
            'manage clientes',
        ] as $permiso) {
            $this->assertTrue($rol->hasPermissionTo($permiso), "El jefe de ventas debería conservar '{$permiso}'.");
        }
    }

    // 2. Nadie reparte permisos salvo admin.

    public function test_ningun_rol_del_negocio_reparte_permisos(): void
    {
        $roles = Role::where('name', '!=', PermisosSoloAdmin::ROL)->pluck('name');

        // Control positivo: si la consulta no trae roles, el barrido no prueba nada.
        $this->assertGreaterThan(5, $roles->count(), 'No se sembraron los roles del negocio.');

        foreach ($roles as $nombre) {
            foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
                $this->assertFalse(
                    Role::findByName($nombre)->hasPermissionTo($permiso),
                    "El rol '{$nombre}' NO debería poder repartir accesos ('{$permiso}').",
                );
            }
        }
    }

    public function test_el_rol_admin_si_los_lleva(): void
    {
        foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
            $this->assertTrue(Role::findByName(PermisosSoloAdmin::ROL)->hasPermissionTo($permiso));
        }
    }

    public function test_editar_un_rol_no_le_puede_dar_los_permisos_de_acceso(): void
    {
        $rol = Role::create(['name' => 'supervisor', 'guard_name' => 'web']);

        $this->actingAs($this->usuario('admin'))
            ->put("/admin/roles/{$rol->id}", [
                'name' => 'supervisor',
                'permissions' => array_merge(PermisosSoloAdmin::PERMISOS, ['view users', 'manage clientes']),
            ])
            ->assertRedirect(route('admin.roles.index'));

        $rol = $rol->fresh();

        // Los cuatro se descartaron...
        foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
            $this->assertFalse($rol->hasPermissionTo($permiso), "'{$permiso}' no debería haberse guardado.");
        }

        // ...y el resto del formulario se guardó igual: el filtro descarta cuatro
        // permisos, no el POST entero (si rechazara todo, este test pasaría por la
        // razón equivocada).
        $this->assertTrue($rol->hasPermissionTo('view users'));
        $this->assertTrue($rol->hasPermissionTo('manage clientes'));
    }

    public function test_un_rol_nuevo_no_puede_nacer_repartiendo_permisos(): void
    {
        $this->actingAs($this->usuario('admin'))
            ->post('/admin/roles', [
                'name' => 'supervisor',
                'permissions' => array_merge(PermisosSoloAdmin::PERMISOS, ['manage clientes']),
            ])
            ->assertRedirect(route('admin.roles.index'));

        $rol = Role::findByName('supervisor');

        foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
            $this->assertFalse($rol->hasPermissionTo($permiso), "Un rol nuevo no debería nacer con '{$permiso}'.");
        }
        $this->assertTrue($rol->hasPermissionTo('manage clientes'));
    }

    /**
     * El filtro NO le saca los permisos al propio admin: si se los sacara, el
     * primer guardado de su ficha lo dejaría sin poder administrar nada.
     */
    public function test_guardar_la_ficha_del_admin_no_lo_desarma(): void
    {
        $rolAdmin = Role::findByName('admin');

        $this->actingAs($this->usuario('admin'))
            ->put("/admin/roles/{$rolAdmin->id}", [
                'permissions' => array_merge(PermisosSoloAdmin::PERMISOS, ['view users']),
            ]);

        foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
            $this->assertTrue($rolAdmin->fresh()->hasPermissionTo($permiso), "El admin debería conservar '{$permiso}'.");
        }
    }

    // 3. La pantalla dice la verdad.

    public function test_la_ficha_de_un_rol_dibuja_bloqueadas_las_casillas_de_acceso(): void
    {
        $rol = Role::findByName('jefe_ventas');

        $html = $this->actingAs($this->usuario('admin'))
            ->get("/admin/roles/{$rol->id}/edit")->assertOk()->getContent();

        // Cada una de las cuatro, bloqueada. Se asserta la forma CONTIGUA del
        // atributo: un `value="…"` suelto lo satisface cualquier casilla de la
        // página (doctrina verde-engañoso).
        foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
            $this->assertMatchesRegularExpression(
                '/value="'.preg_quote($permiso, '/').'"\s+x-model="sel" disabled/',
                $html,
                "La casilla de '{$permiso}' debería venir bloqueada en la ficha de un rol que no es admin.",
            );
        }
        $this->assertStringContainsString('solo el rol admin', $html);

        // Y NO viaja en un hidden: la gracia es que el POST no la traiga.
        $this->assertStringNotContainsString('name="permissions[]" value="edit users">', $html);
    }

    public function test_la_ficha_del_admin_no_bloquea_lo_que_el_admin_si_puede(): void
    {
        $rolAdmin = Role::findByName('admin');

        $html = $this->actingAs($this->usuario('admin'))
            ->get("/admin/roles/{$rolAdmin->id}/edit")->assertOk()->getContent();

        // 'manage roles' sigue bloqueado, pero al revés: en ON y obligatorio.
        $this->assertStringContainsString('obligatorio para admin', $html);
        $this->assertStringNotContainsString('solo el rol admin', $html);
        // Y las otras tres quedan marcables (sin `disabled`).
        $this->assertMatchesRegularExpression('/value="edit users"\s+x-model="sel"\s+class=/', $html);
    }

    // 4. La migración de producción.

    public function test_la_migracion_le_quita_administracion_al_jefe_de_ventas(): void
    {
        // Estado de producción antes del cambio: el seeder ya corrió alguna vez con
        // 'view users' en la lista del jefe de ventas.
        $jefe = Role::findByName('jefe_ventas');
        $jefe->givePermissionTo('view users');
        $this->assertTrue($jefe->fresh()->hasPermissionTo('view users'), 'El fixture no reprodujo el estado viejo.');

        (require database_path(self::MIGRACION))->up();

        $this->assertFalse(Role::findByName('jefe_ventas')->hasPermissionTo('view users'));
        // Y no se llevó de paso a los otros dos jefes, que lo conservan a propósito.
        $this->assertTrue(Role::findByName('jefe_bodega')->hasPermissionTo('view users'));
        $this->assertTrue(Role::findByName('jefe_sucursal')->hasPermissionTo('view users'));
    }

    public function test_la_migracion_barre_los_permisos_de_acceso_que_se_dieron_desde_la_ui(): void
    {
        // Lo que este barrido existe para arreglar: alguien, en algún momento, marcó
        // «Editar usuarios» y «Gestionar roles» a un rol desde la pantalla de Roles.
        Role::findByName('jefe_ventas')->givePermissionTo(['edit users', 'manage roles']);
        Role::findByName('vendedor')->givePermissionTo('create users');
        $this->assertTrue(Role::findByName('jefe_ventas')->fresh()->hasPermissionTo('manage roles'));

        (require database_path(self::MIGRACION))->up();

        $this->assertFalse(Role::findByName('jefe_ventas')->hasPermissionTo('edit users'));
        $this->assertFalse(Role::findByName('jefe_ventas')->hasPermissionTo('manage roles'));
        $this->assertFalse(Role::findByName('vendedor')->hasPermissionTo('create users'));

        // El admin queda intacto: el barrido excluye su rol, no filtra por permiso.
        foreach (PermisosSoloAdmin::PERMISOS as $permiso) {
            $this->assertTrue(Role::findByName('admin')->hasPermissionTo($permiso), "El barrido no debe tocar al admin ('{$permiso}').");
        }
    }

    public function test_la_migracion_es_idempotente(): void
    {
        // El fixture importa: con el seeder ya corregido, jefe_ventas NO tiene
        // 'view users', así que sin sembrarlo la primera corrida no haría nada y las
        // dos pasadas serían igual de vacías — el test pasaría sin probar nada.
        Role::findByName('jefe_ventas')->givePermissionTo('view users');

        (require database_path(self::MIGRACION))->up();
        (require database_path(self::MIGRACION))->up();

        $this->assertFalse(Role::findByName('jefe_ventas')->hasPermissionTo('view users'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('manage roles'));
    }

    /**
     * Y se puede volver atrás: el `down()` le devuelve el listado al jefe de ventas.
     *
     * El barrido de los cuatro permisos NO se revierte, y está declarado en el
     * `down()` de la migración: no guardamos qué rol tenía cuál, así que revertirlo
     * sería repartir accesos a ciegas — justo lo que esto existe para impedir.
     */
    public function test_la_migracion_se_puede_revertir_en_su_mitad_reversible(): void
    {
        $migracion = require database_path(self::MIGRACION);

        $migracion->up();
        $this->assertFalse(Role::findByName('jefe_ventas')->hasPermissionTo('view users'));

        $migracion->down();
        $this->assertTrue(Role::findByName('jefe_ventas')->hasPermissionTo('view users'));
    }
}
