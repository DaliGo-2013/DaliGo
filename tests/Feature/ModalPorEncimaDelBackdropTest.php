<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/**
 * EL PANEL DEL MODAL VA POR ENCIMA DE SU BACKDROP.
 *
 * Lo reportó el dueño (28-08-2026) sobre la ventana previa de la cotización, con tres síntomas
 * que parecían tres problemas y eran uno: «aparece oscura», «no me deja deslizar la barra para
 * ver el detalle» y «si aprieto un clic en la pantalla se sale».
 *
 * La causa: el backdrop es `fixed inset-0` y el panel era su hermano SIN posicionamiento. Un
 * elemento posicionado se pinta siempre por encima de un hermano estático, sin importar el orden
 * del DOM — así que el backdrop quedaba delante, atenuaba la ventana y se comía el scroll y los
 * clics (y su `x-on:click` la cerraba).
 *
 * POR QUÉ NO SE HABÍA NOTADO: el panel trae `transform`, que en Tailwind v3 emitía un transform
 * real y creaba contexto de apilamiento — lo elevaba POR ACCIDENTE. En v4 esa clase compila con
 * todas sus variables en `initial`, la declaración resuelve a vacío y el navegador la descarta:
 * quedó siendo un no-op. Medido en el navegador: `getComputedStyle(panel).transform === 'none'`.
 *
 * ESTE CANDADO ES ESTRUCTURAL Y NO PUEDE SER OTRA COSA: el apilamiento solo se puede comprobar
 * de verdad en un navegador (se hizo, con `elementFromPoint` sobre el punto donde el usuario
 * scrollea y sobre el botón de enviar — sin el fix los dos devolvían el backdrop). Desde PHP lo
 * único verificable es que el panel declare posicionamiento, que es la condición necesaria.
 */
class ModalPorEncimaDelBackdropTest extends TestCase
{
    private function render(): string
    {
        return Blade::render(
            file_get_contents(resource_path('views/components/modal.blade.php')),
            [
                'name' => 'prueba',
                'show' => true,
                'maxWidth' => '2xl',
                'attributes' => new ComponentAttributeBag([]),
                'slot' => new HtmlString('<p>contenido</p>'),
            ],
        );
    }

    /** El div que contiene el slot tiene que estar POSICIONADO y con z-index propio. */
    public function test_el_panel_del_modal_esta_posicionado_por_encima_del_backdrop(): void
    {
        $html = $this->render();

        // El panel es el div cuya clase trae el fondo blanco y el ancho maximo (el que envuelve
        // al slot). Se localiza por esa combinacion, que es unica en el componente.
        $this->assertMatchesRegularExpression(
            '/<div\s+[^>]*class="[^"]*\brelative\b[^"]*\bz-\d+\b[^"]*bg-white[^"]*sm:max-w-2xl[^"]*"/s',
            $html,
            'El panel del modal perdió su posicionamiento (`relative z-N`): el backdrop `fixed` se '
            .'va a pintar encima, la ventana se va a ver atenuada y se va a comer el scroll y los clics.',
        );
    }

    /**
     * Y el backdrop NO puede tener un z-index que le gane al panel. Sin este assert, alguien
     * podría "arreglar" un apilamiento futuro subiéndole el z al backdrop y volver a tapar todo.
     */
    public function test_el_backdrop_no_le_gana_en_z_index_al_panel(): void
    {
        $html = $this->render();

        // z del panel (el que trae `relative`).
        preg_match('/class="[^"]*\brelative\b[^"]*\bz-(\d+)\b[^"]*bg-white[^"]*"/s', $html, $mPanel);
        $this->assertNotEmpty($mPanel, 'No se pudo leer el z-index del panel.');
        $zPanel = (int) $mPanel[1];

        // El backdrop: el div `fixed inset-0` que envuelve la capa oscura.
        preg_match('/<div[^>]*class="([^"]*\bfixed\b[^"]*inset-0[^"]*)"[^>]*x-on:click/s', $html, $mBack);
        $this->assertNotEmpty($mBack, 'No se encontró el backdrop del modal.');

        if (preg_match('/\bz-(\d+)\b/', $mBack[1], $mz)) {
            $this->assertLessThan(
                $zPanel,
                (int) $mz[1],
                'El backdrop tiene un z-index mayor o igual que el panel: vuelve a taparlo.',
            );
        } else {
            $this->assertTrue(true, 'El backdrop no declara z-index (auto), así que el panel con z propio le gana.');
        }
    }

    /**
     * El contenedor exterior sigue siendo el que scrollea la pagina del modal: si perdiera el
     * `overflow-y-auto`, una carta larga no se podria recorrer — que es la mitad del sintoma que
     * reporto el dueño.
     */
    public function test_el_contenedor_del_modal_conserva_su_scroll(): void
    {
        $this->assertStringContainsString('overflow-y-auto', $this->render());
    }
}
