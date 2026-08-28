<?php

namespace Tests\Feature\Admin;

use App\Models\Instalacion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * La planilla de instalaciones EN EL CELULAR (dueño 28-08-2026: «los vendedores
 * usarán mucho el celular cuando estén en terreno visitando clientes»).
 *
 * Los dos defectos que cazó la medición a 375px, y que en pantalla grande no se
 * ven — de ahí que sean candados estructurales sobre el Blade y no asserts de
 * pantalla:
 *
 *  1. La acción DESTRUCTIVA era la fácil de acertar con el pulgar: «Eliminar»
 *     medía 44x44px (lo trae `x-icon-button`) y «Editar» era un enlace de texto
 *     de 39x20px al lado. En un teléfono eso es una invitación a borrar.
 *  2. Las dos líneas de datos de la fila miden 473 y 344px en un ancho útil de
 *     310: `truncate` a secas se comía la comuna y el RUT del cliente que el
 *     vendedor fue a ver. La regla de la casa es reflowar en móvil, no recortar.
 */
class InstalacionesMovilTest extends TestCase
{
    use RefreshDatabase;

    private const VISTA = 'resources/views/admin/instalaciones/index.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fuenteDeLaFila(): string
    {
        return File::get(base_path(self::VISTA));
    }

    /**
     * Las dos acciones de la fila usan el MISMO componente, que es lo que las
     * empareja: `x-icon-button` trae `min-h-11 min-w-11` en móvil y vuelve a la
     * densidad de fila desde `sm:`. Se cuenta en vez de buscar la cadena porque
     * la fila emite dos y con un solo `assertStringContainsString` el candado
     * pasaría en verde con una de las dos degradada a enlace de texto.
     */
    public function test_editar_y_eliminar_son_el_mismo_control_tactil(): void
    {
        $blade = $this->fuenteDeLaFila();

        $this->assertSame(2, substr_count($blade, '<x-icon-button'),
            'La fila debe tener SUS DOS acciones como icon-button: si una vuelve a ser enlace '
            .'de texto, en el celular mide 20px de alto contra los 44 de la otra — y la otra borra.');

        // Y que no vuelva el idioma viejo justo en la fila.
        $this->assertStringNotContainsString('<x-secondary-link :href="route(\'admin.instalaciones.edit\'',
            $blade, 'El Editar de la fila volvió a ser un enlace de texto.');
    }

    /**
     * `truncate` solo desde `sm:`. Mutado: al dejar un `truncate` pelado en
     * cualquiera de las dos líneas de datos, este test se pone rojo.
     */
    public function test_la_fila_no_recorta_los_datos_en_el_celular(): void
    {
        $blade = $this->fuenteDeLaFila();

        // Un `truncate` sin prefijo responsive aplica en TODOS los anchos.
        $this->assertSame(0, preg_match_all('/class="[^"]*(?<![:\w-])truncate/', $blade),
            'Hay un `truncate` sin prefijo `sm:` en la fila: en el celular esconde datos '
            .'(la comuna y el RUT del cliente) sin dar forma de leerlos.');

        $this->assertGreaterThanOrEqual(2, substr_count($blade, 'sm:truncate'),
            'Las líneas de datos de la fila deben truncar SOLO desde sm:, donde sobra ancho.');
    }

    /** Y que la pantalla siga sirviendo: el vendedor la abre y ve su registro. */
    public function test_el_listado_carga_con_sus_datos(): void
    {
        $vendedor = tap(User::factory()->create())->assignRole('vendedor');
        $instalacion = Instalacion::factory()->create([
            'cliente_nombre' => 'Agua Purificada Canto del Agua',
            'comuna_region' => 'Copiapó, Atacama',
        ]);

        $this->actingAs($vendedor)->get(route('admin.instalaciones.index'))
            ->assertOk()
            ->assertSee('Agua Purificada Canto del Agua')
            // El dato que el truncate se comía en el celular.
            ->assertSee('Copiapó, Atacama')
            ->assertSee(route('admin.instalaciones.edit', $instalacion));
    }
}
