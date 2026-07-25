<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Volver" único (doctrina del dueño, 2026-07-24). Una sola forma de volver a la
 * pantalla anterior en toda la app: texto fijo "Volver", arriba a la izquierda
 * pegado al título, y presente SOLO en pantallas que cuelgan de un listado.
 *
 * Ancla: el atributo `data-dg-volver` que emite <x-volver> (doctrina anti
 * verde-engañoso). No se asserta por clases CSS —las comparte cualquier botón
 * secundario de la página— ni por la palabra "Volver" suelta, que también
 * aparece en tooltips, en prosa de ayuda y en los avisos de error.
 */
class VolverTest extends TestCase
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

    private function cuantosVolver(string $html): int
    {
        return substr_count($html, 'data-dg-volver');
    }

    /**
     * El candado central: ni cero (usuario atrapado) ni dos (el defecto que
     * motivó todo esto — produccion/reporte tenía dos con destinos distintos).
     */
    public function test_pantalla_hija_tiene_exactamente_un_volver(): void
    {
        foreach (['admin.produccion.dia', 'admin.produccion.movimientos'] as $ruta) {
            $html = $this->actingAs($this->admin())->get(route($ruta))->assertOk()->getContent();

            $this->assertSame(1, $this->cuantosVolver($html),
                "[$ruta] debe tener EXACTAMENTE un botón Volver.");
        }
    }

    /**
     * Posición: el "Volver" se renderiza ANTES del título, no en el bloque de
     * acciones de la derecha (donde 26 vistas lo habían escrito a mano, pegado
     * al botón de confirmar).
     */
    public function test_el_volver_va_antes_del_titulo(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.produccion.dia'))->assertOk()->getContent();

        $volver = strpos($html, 'data-dg-volver');
        $titulo = strpos($html, '<h2 class="text-xl font-semibold');

        $this->assertNotFalse($volver, 'No se encontró el botón Volver.');
        $this->assertNotFalse($titulo, 'No se encontró el título del page-header.');
        $this->assertLessThan($titulo, $volver,
            'El "Volver" debe ir arriba a la IZQUIERDA (antes del título), no en las acciones de la derecha.');
    }

    /**
     * Forma: el texto VISIBLE es el literal "Volver" y nada más. El nombre del
     * destino va en el tooltip — si vuelve a colarse en el texto ("Volver a
     * listas", "Mis producciones"), el botón deja de verse igual en cada
     * pantalla y esto se pone rojo.
     */
    public function test_el_texto_visible_es_solo_volver(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.produccion.dia'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<a [^>]*data-dg-volver.*?<\/a>/s', $html, $enlace),
            'No se encontró el enlace del botón Volver.');
        $this->assertSame('Volver', trim(strip_tags($enlace[0])),
            'El texto visible debe ser exactamente "Volver"; el destino va en el title.');
    }

    /** El href apunta a la pantalla padre (destino garantizado sin JS). */
    public function test_el_volver_apunta_al_padre(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.produccion.dia'))
            ->assertOk()
            ->assertSee('href="'.route('admin.produccion.index').'" data-dg-volver', false);
    }

    /**
     * Un listado que ES ítem del menú no tiene página padre: se llega por la
     * sidebar. Cuatro listados llevaban un "Volver al inicio" que hacía que el
     * mismo botón significara dos cosas distintas según la pantalla.
     */
    public function test_un_listado_del_menu_no_lleva_volver(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.produccion.index'))->assertOk()->getContent();

        $this->assertSame(0, $this->cuantosVolver($html),
            'Un listado del menú no lleva Volver: se llega por la sidebar.');
    }
}
