<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Candados de COM-1 (PLAN-PARAMETRICOS): las dos listas del negocio del
 * módulo Comercial como claves JSON editables — molde DASH adaptado a listas:
 *  1. Default idéntico con BD virgen (regla de oro).
 *  2. Agregar un valor a la clave mueve selector, filtro y validación SIN
 *     tocar código (el candado estrella).
 *  3. La regla de seguridad dictada: quitar un segmento con clientes
 *     asignados se rechaza nombrando la cifra.
 *  4. El placeholder del corrector deriva del primer elemento.
 *  5. Normalización: una lista rota por fuera de la UI no tumba el selector.
 *  6. La UI una-por-línea guarda el JSON bien.
 */
class ParametrosComercialTest extends TestCase
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

    public function test_sin_claves_en_bd_todo_rinde_identico_al_historico(): void
    {
        // BD virgen (sin ConfiguracionSeeder): rigen los fallbacks.
        $res = $this->actingAs($this->admin())->get(route('admin.clientes.index'))->assertOk();
        $this->assertSame(['mayorista', 'retail', 'recurrente'], $res->viewData('segmentos'));

        $this->actingAs($this->admin())->get(route('admin.productos.index'))
            ->assertOk()
            ->assertSee('Ej. Repuestos industriales');

        // La validación acepta lo histórico y rechaza lo inexistente.
        $this->actingAs($this->admin())
            ->post(route('admin.clientes.store'), ['razon_social' => 'Aguas X', 'segmento' => 'mayorista'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin())
            ->post(route('admin.clientes.store'), ['razon_social' => 'Aguas Y', 'segmento' => 'horeca'])
            ->assertSessionHasErrors('segmento');
    }

    public function test_agregar_un_segmento_a_la_clave_mueve_la_pantalla_sin_tocar_codigo(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('clientes_segmentos', ['mayorista', 'retail', 'recurrente', 'horeca']);

        // Aparece en el selector/filtro…
        $res = $this->actingAs($this->admin())->get(route('admin.clientes.index'))->assertOk();
        $this->assertContains('horeca', $res->viewData('segmentos'));

        // …y la validación lo acepta: el candado estrella del lote.
        $this->actingAs($this->admin())
            ->post(route('admin.clientes.store'), ['razon_social' => 'Casino Z', 'segmento' => 'horeca'])
            ->assertSessionHasNoErrors();
        $this->assertSame('horeca', Cliente::where('razon_social', 'Casino Z')->firstOrFail()->segmento);
    }

    public function test_quitar_un_segmento_con_clientes_se_rechaza_con_la_cifra(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Cliente::factory()->count(3)->create(['segmento' => 'retail']);
        $config = Configuracion::where('clave', 'clientes_segmentos')->firstOrFail();

        // La UI manda la lista SIN retail (una por línea) → rechazo con nombre y cuenta.
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.update', $config), ['valor' => "mayorista\nrecurrente"])
            ->assertInvalid(['valor' => 'No puedes quitar «retail»: 3 cliente(s)']);
        $this->assertContains('retail', Cliente::segmentos()); // no se guardó

        // Quitar uno SIN clientes sí pasa (agregar es libre, quitar-vacío también).
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.update', $config), ['valor' => "mayorista\nretail"])
            ->assertSessionHasNoErrors();
        $this->assertSame(['mayorista', 'retail'], Cliente::segmentos());
    }

    public function test_el_placeholder_del_corrector_deriva_de_la_primera_sugerida(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        Configuracion::set('catalogo_categorias_sugeridas', ['Filtros de agua', 'Repuestos industriales']);

        $this->actingAs($this->admin())->get(route('admin.productos.index'))
            ->assertOk()
            ->assertSee('Ej. Filtros de agua');
    }

    public function test_una_lista_rota_por_fuera_de_la_ui_no_tumba_el_selector(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        // Duplicados (con mayúsculas), espacios y vacíos: el consumidor limpia.
        Configuracion::set('clientes_segmentos', ['mayorista', 'MAYORISTA', ' retail ', '', 'recurrente']);
        $this->assertSame(['mayorista', 'retail', 'recurrente'], Cliente::segmentos());

        // Lista vacía: cae al fallback histórico, jamás un selector vacío.
        Configuracion::set('clientes_segmentos', []);
        $this->assertSame(['mayorista', 'retail', 'recurrente'], Cliente::segmentos());
    }

    public function test_la_ui_una_por_linea_normaliza_y_guarda_json(): void
    {
        $this->seed(ConfiguracionSeeder::class);
        $config = Configuracion::where('clave', 'clientes_segmentos')->firstOrFail();

        // Líneas con ruido (vacías, espacios, un duplicado en mayúsculas).
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.update', $config), ['valor' => "mayorista\n\n retail \nRETAIL\nrecurrente\nhoreca"])
            ->assertSessionHasNoErrors();
        $this->assertSame(['mayorista', 'retail', 'recurrente', 'horeca'], Configuracion::get('clientes_segmentos'));

        // Vaciarla del todo se rechaza (el selector no puede quedar sin opciones).
        $this->actingAs($this->admin())
            ->put(route('admin.configuracion.update', $config), ['valor' => "\n \n"])
            ->assertSessionHasErrors('valor');
    }
}
