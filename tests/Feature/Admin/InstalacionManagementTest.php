<?php

namespace Tests\Feature\Admin;

use App\Models\Instalacion;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstalacionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private const MIGRACION = 'migrations/2026_09_02_120000_instalaciones_solo_del_tecnico_industrial.php';

    private function tecnicoIndustrial(): User
    {
        return tap(User::factory()->create())->assignRole('tecnico_industrial');
    }

    private function usuario(string $rol): User
    {
        return tap(User::factory()->create())->assignRole($rol);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'fecha' => now()->toDateString(),
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'cliente_rut' => '12.345.678-5',
            'comuna_region' => 'Copiapó',
            'categoria' => 'lavadora',
            'producto' => 'LAVADORA BOTELLON 20L-220V',
            'instalacion' => '1',
            'dias' => '2',
            'vendedor' => 'Luis Figueroa',
            'n_factura' => '250868',
            'forma_pago' => 'transferencia',
        ], $overrides);
    }

    // --- Acceso / gating ---

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/instalaciones')->assertRedirect('/login');
    }

    public function test_member_sin_permiso_es_rechazado(): void
    {
        $member = tap(User::factory()->create())->assignRole('member');

        $this->actingAs($member)->get('/admin/instalaciones')->assertRedirect(route('dashboard'))
            ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);
        $this->actingAs($member)->post('/admin/instalaciones', $this->payload())->assertForbidden();
    }

    public function test_tecnico_industrial_ve_y_registra(): void
    {
        $this->actingAs($this->tecnicoIndustrial())->get('/admin/instalaciones')->assertOk();
    }

    /**
     * INSTALACIONES ES LA PLANILLA PERSONAL DEL TÉCNICO INDUSTRIAL, Y DE NADIE MÁS.
     *
     * Dueño, 02-09-2026, mirando el menú de un jefe de ventas: *«eso es para Carlos
     * Tablante, el técnico industrial, que ingresa sus instalaciones para que le
     * paguen el sueldo y horas extras. Es como un respaldo personal»*.
     *
     * Este candado es el INVERSO del que había acá (`test_jefe_ventas_tambien_gestiona`,
     * que fijaba que el jefe SÍ entraba): la pantalla nació como el Excel de Carlos y
     * el seeder se la repartió a los jefes «por si acaso». Se prueba el jefe de ventas
     * y el vendedor porque el dueño nombró a los dos — y porque en producción un
     * vendedor la veía aunque el seeder nunca se la dio (la recibió desde la UI).
     */
    public function test_ni_el_jefe_de_ventas_ni_el_vendedor_ven_instalaciones(): void
    {
        foreach (['jefe_ventas', 'vendedor'] as $rol) {
            $usuario = $this->usuario($rol);

            $this->actingAs($usuario)->get('/admin/instalaciones')
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('aviso', \App\Support\AvisosError::SIN_PERMISO);

            // Y el ítem no se le ofrece: el gate del menú y el de la ruta son el
            // mismo (D-014), o si no el menú ofrecería un 403.
            $this->actingAs($usuario)->get(route('dashboard'))->assertOk()
                ->assertDontSee(route('admin.instalaciones.index'));
        }
    }

    /**
     * La otra mitad: a Carlos le sigue apareciendo en el menú. Sin esto, el candado
     * de arriba se cumple igual con el permiso borrado del sistema entero.
     */
    public function test_el_tecnico_industrial_conserva_instalaciones_en_su_menu(): void
    {
        $this->actingAs($this->tecnicoIndustrial())->get(route('dashboard'))->assertOk()
            ->assertSee(route('admin.instalaciones.index'));
    }

    // --- La migración de producción ---

    public function test_la_migracion_les_quita_instalaciones_al_jefe_de_ventas_y_al_vendedor(): void
    {
        // Estado de producción antes del cambio: el seeder se lo había dado al jefe de
        // ventas, y alguien se lo marcó al vendedor desde la pantalla de Roles.
        Role::findByName('jefe_ventas')->givePermissionTo('gestionar instalaciones');
        Role::findByName('vendedor')->givePermissionTo('gestionar instalaciones');
        $this->assertTrue(Role::findByName('vendedor')->fresh()->hasPermissionTo('gestionar instalaciones'), 'El fixture no reprodujo el estado viejo.');

        (require database_path(self::MIGRACION))->up();

        $this->assertFalse(Role::findByName('jefe_ventas')->hasPermissionTo('gestionar instalaciones'));
        $this->assertFalse(Role::findByName('vendedor')->hasPermissionTo('gestionar instalaciones'));
        // Y a su dueño no se lo lleva.
        $this->assertTrue(Role::findByName('tecnico_industrial')->hasPermissionTo('gestionar instalaciones'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('gestionar instalaciones'));
    }

    /**
     * Reversible SOLO para el jefe de ventas: es el único al que el seeder se lo había
     * dado. El vendedor lo tenía por fuera del código, y devolvérselo en un `down()`
     * sería volver a repartir algo que nunca se decidió repartir.
     */
    public function test_la_migracion_se_revierte_solo_para_el_jefe_de_ventas(): void
    {
        Role::findByName('jefe_ventas')->givePermissionTo('gestionar instalaciones');
        Role::findByName('vendedor')->givePermissionTo('gestionar instalaciones');
        $migracion = require database_path(self::MIGRACION);

        $migracion->up();
        $migracion->down();

        $this->assertTrue(Role::findByName('jefe_ventas')->hasPermissionTo('gestionar instalaciones'));
        $this->assertFalse(Role::findByName('vendedor')->hasPermissionTo('gestionar instalaciones'));
    }

    public function test_formulario_sugiere_productos_activos_del_catalogo(): void
    {
        Producto::factory()->create(['nombre' => 'PLANTA DE OSMOSIS DEMO 1T', 'activo' => true]);
        Producto::factory()->create(['nombre' => 'PRODUCTO INACTIVO DEMO', 'activo' => false]);

        $res = $this->actingAs($this->tecnicoIndustrial())->get('/admin/instalaciones/create');

        $res->assertOk()
            ->assertSee('productos-catalogo')            // el datalist
            ->assertSee('PLANTA DE OSMOSIS DEMO 1T')     // activo → sugerido
            ->assertDontSee('PRODUCTO INACTIVO DEMO');   // inactivo → no
    }

    // --- CRUD ---

    public function test_registra_una_instalacion(): void
    {
        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', $this->payload(['puesta_en_marcha' => '1']))
            ->assertRedirect(route('admin.instalaciones.index'));

        $this->assertDatabaseHas('instalaciones', [
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'categoria' => 'lavadora',
            'instalacion' => true,
            'puesta_en_marcha' => true,
            'dias' => 2,
            'vendedor' => 'Luis Figueroa',
            'forma_pago' => 'transferencia',
        ]);
    }

    public function test_checkboxes_sin_marcar_quedan_en_falso(): void
    {
        $data = $this->payload();
        unset($data['instalacion']); // ninguna marcada

        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', $data)
            ->assertRedirect(route('admin.instalaciones.index'));

        $this->assertDatabaseHas('instalaciones', [
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'instalacion' => false,
            'puesta_en_marcha' => false,
        ]);
    }

    public function test_crear_exige_todos_los_datos_menos_factura_y_pago(): void
    {
        // Todo obligatorio salvo los datos de factura/pago (n_factura,
        // fecha_factura, forma_pago, fecha_pago) y los checkboxes SI/NO.
        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', [])
            ->assertSessionHasErrors([
                'fecha', 'cliente_nombre', 'cliente_rut', 'comuna_region',
                'categoria', 'producto', 'dias', 'vendedor',
            ])
            ->assertSessionDoesntHaveErrors(['n_factura', 'fecha_factura', 'forma_pago', 'fecha_pago']);
    }

    public function test_categoria_y_forma_pago_invalidas_se_rechazan(): void
    {
        $this->actingAs($this->tecnicoIndustrial())
            ->post('/admin/instalaciones', $this->payload(['categoria' => 'inventada', 'forma_pago' => 'trueque']))
            ->assertSessionHasErrors(['categoria', 'forma_pago']);
    }

    public function test_actualiza_una_instalacion(): void
    {
        $ins = Instalacion::factory()->create(['categoria' => 'lavadora', 'vendedor' => 'Luis Figueroa']);

        $this->actingAs($this->tecnicoIndustrial())
            ->put("/admin/instalaciones/{$ins->id}", $this->payload(['categoria' => 'planta', 'vendedor' => 'Carlos Toledo']))
            ->assertRedirect(route('admin.instalaciones.index'));

        $this->assertSame('planta', $ins->fresh()->categoria);
        $this->assertSame('Carlos Toledo', $ins->fresh()->vendedor);
    }

    // --- Historial por período (cards Año → Mes) ---

    public function test_el_historial_agrupa_por_anio_con_desglose_por_categoria(): void
    {
        Instalacion::factory()->create(['fecha' => '2026-07-13', 'categoria' => 'lavadora']);
        Instalacion::factory()->create(['fecha' => '2026-06-25', 'categoria' => 'lavadora']);
        Instalacion::factory()->create(['fecha' => '2026-06-13', 'categoria' => 'planta']);
        Instalacion::factory()->create(['fecha' => '2025-04-02', 'categoria' => 'llenadora']);

        $historial = $this->actingAs($this->tecnicoIndustrial())
            ->get(route('admin.instalaciones.index'))
            ->assertOk()
            ->viewData('historial');

        // Años más recientes primero.
        $this->assertSame([2026, 2025], array_keys($historial['anios']->all()));
        $this->assertSame(3, $historial['anios'][2026]['total']);
        $this->assertSame(['lavadora' => 2, 'planta' => 1], $historial['anios'][2026]['categorias']);
        // Sin año elegido no se calculan los meses (una consulta menos).
        $this->assertNull($historial['meses']);
    }

    public function test_las_categorias_sin_registros_no_aparecen_en_la_card(): void
    {
        Instalacion::factory()->create(['fecha' => '2026-07-13', 'categoria' => 'lavadora']);

        $historial = $this->actingAs($this->tecnicoIndustrial())
            ->get(route('admin.instalaciones.index'))
            ->viewData('historial');

        // Una card que dijera "0 planta · 0 llenadora" es ruido.
        $this->assertSame(['lavadora' => 1], $historial['anios'][2026]['categorias']);
    }

    public function test_al_elegir_un_anio_aparecen_sus_doce_meses_con_conteo(): void
    {
        Instalacion::factory()->create(['fecha' => '2026-07-13']);
        Instalacion::factory()->create(['fecha' => '2026-06-25']);
        Instalacion::factory()->create(['fecha' => '2026-06-13']);
        Instalacion::factory()->create(['fecha' => '2025-06-01']); // otro año: no cuenta

        $historial = $this->actingAs($this->tecnicoIndustrial())
            ->get(route('admin.instalaciones.index', ['anio' => 2026]))
            ->assertOk()
            ->viewData('historial');

        $this->assertCount(12, $historial['meses'], 'Van los 12 meses, con 0 los vacíos.');
        $this->assertSame(2, $historial['meses'][6]);
        $this->assertSame(1, $historial['meses'][7]);
        $this->assertSame(0, $historial['meses'][1]);
    }

    public function test_el_listado_obedece_el_periodo_elegido(): void
    {
        Instalacion::factory()->create(['fecha' => '2026-07-13', 'cliente_nombre' => 'Canto del Agua']);
        Instalacion::factory()->create(['fecha' => '2026-06-25', 'cliente_nombre' => 'Aguas del Norte']);
        Instalacion::factory()->create(['fecha' => '2025-04-02', 'cliente_nombre' => 'Vida Sana']);

        $tecnico = $this->tecnicoIndustrial();

        // Año completo.
        $anio = $this->actingAs($tecnico)->get(route('admin.instalaciones.index', ['anio' => 2026]))
            ->assertOk()->viewData('instalaciones');
        $this->assertSame(2, $anio->total());
        $this->assertFalse($anio->pluck('cliente_nombre')->contains('Vida Sana'));

        // Un mes puntual dentro del año.
        $mes = $this->actingAs($tecnico)->get(route('admin.instalaciones.index', ['anio' => 2026, 'mes' => 6]))
            ->assertOk()->viewData('instalaciones');
        $this->assertSame(1, $mes->total());
        $this->assertSame('Aguas del Norte', $mes->first()->cliente_nombre);
    }

    public function test_el_periodo_convive_con_los_otros_filtros(): void
    {
        Instalacion::factory()->create(['fecha' => '2026-06-25', 'categoria' => 'lavadora']);
        Instalacion::factory()->create(['fecha' => '2026-06-13', 'categoria' => 'planta']);

        $listado = $this->actingAs($this->tecnicoIndustrial())
            ->get(route('admin.instalaciones.index', ['anio' => 2026, 'mes' => 6, 'categoria' => 'planta']))
            ->assertOk()
            ->viewData('instalaciones');

        $this->assertSame(1, $listado->total());
        $this->assertSame('planta', $listado->first()->categoria);
    }

    public function test_un_periodo_invalido_se_rechaza(): void
    {
        $this->actingAs($this->tecnicoIndustrial())
            ->get(route('admin.instalaciones.index', ['mes' => 13]))
            ->assertSessionHasErrors('mes');
    }

    public function test_el_desplegable_de_anio_ya_no_existe(): void
    {
        Instalacion::factory()->create(['fecha' => '2026-07-13']);

        // Las cards del historial lo reemplazaron: dos formas de filtrar el mismo
        // parámetro se contradicen entre sí.
        $this->actingAs($this->tecnicoIndustrial())
            ->get(route('admin.instalaciones.index'))
            ->assertOk()
            ->assertDontSee('<select id="anio"', false)
            ->assertSee('Historial');
    }

    public function test_elimina_una_instalacion(): void
    {
        $ins = Instalacion::factory()->create();

        $this->actingAs($this->tecnicoIndustrial())->delete("/admin/instalaciones/{$ins->id}");

        $this->assertDatabaseMissing('instalaciones', ['id' => $ins->id]);
    }
}
