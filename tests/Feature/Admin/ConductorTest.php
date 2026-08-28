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
        // 'manage vehiculos' gestiona el catálogo de conductores: quien administra la flota
        // administra quién la maneja. Era `manage servicio tecnico` hasta el 26-08-2026 —ver
        // el candado de abajo sobre el técnico.
        $role = Role::firstOrCreate(['name' => 'custom', 'guard_name' => 'web']);
        $role->syncPermissions(['manage vehiculos']);

        return tap(User::factory()->create())->assignRole($role);
    }

    /** Un usuario con un rol REAL del seeder, para probar lo que ve de verdad. */
    private function usuario(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    public function test_sin_permiso_es_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/conductores')->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
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

    /**
     * EL TÉCNICO NO ADMINISTRA CHOFERES, Y NI LOS VE EN EL MENÚ.
     *
     * Reportado por el dueño el 26-08-2026 mirando el menú de un técnico en su teléfono: *«no
     * entiendo por qué ve conductores, no recuerdo habilitar ese permiso»*. Y no lo habilitó:
     * el gate era `manage servicio tecnico|manage vehiculos`, o sea que el permiso CENTRAL DEL
     * TALLER abría una pantalla de LOGÍSTICA.
     *
     * El comentario que justificaba ese canAny decía que sacárselo dejaría al técnico sin
     * conductores en el selector del ingreso por lote y en el del traslado. **Era falso**, y el
     * segundo candado de abajo es el que lo demuestra: los dos selectores leen el catálogo
     * desde sus propios controladores, con sus propios permisos.
     */
    public function test_el_tecnico_no_administra_conductores_ni_los_ve_en_el_menu(): void
    {
        $tecnico = $this->usuario('tecnico');

        $this->actingAs($tecnico)->get('/admin/conductores')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);

        // Y el ítem NO se le ofrece: el gate de la ruta y el del menú son el mismo (D-014),
        // así que un menú que lo mostrara estaría ofreciendo un 403.
        $this->actingAs($tecnico)->get(route('dashboard'))->assertOk()
            ->assertDontSee(route('admin.conductores.index'));
    }

    /**
     * Y LO QUE EL TÉCNICO SÍ NECESITA SIGUE FUNCIONANDO: elegir un chofer.
     *
     * Es la otra mitad, y la que vuelve segura la de arriba. Los dos selectores que nombraba
     * el comentario viejo —el ingreso por lote y el traslado al taller— leen
     * `Conductor::activos()` desde `LoteServicioController` y `TrasladoServicioController`, con
     * permisos propios (`crear lote servicio`, `recibir traslado servicio`), así que nunca
     * pasaron por el gate de esta pantalla. Sin este candado, el próximo que lea el cambio no
     * tiene forma de saber que no rompió el retiro de máquinas en ruta.
     */
    public function test_el_tecnico_sigue_pudiendo_elegir_un_chofer_en_el_ingreso_por_lote(): void
    {
        Conductor::create(['nombre' => 'Chofer En Ruta', 'activo' => true]);

        $this->actingAs($this->usuario('tecnico'))
            ->get(route('admin.servicio-tecnico.lote.create'))
            ->assertOk()
            ->assertSee('Chofer En Ruta');
    }
}
