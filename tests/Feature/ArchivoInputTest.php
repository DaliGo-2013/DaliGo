<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Selectores de archivo legibles (pedido del dueño, 2026-07-28).
 *
 * Un `<input type="file">` nativo dibuja su propio rótulo y el navegador lo
 * RECORTA: en 375px se leía «Seleccionar archivo ningú…onado». No se puede
 * arreglar con CSS —`::file-selector-button` alcanza el botón, pero el texto de
 * «ningún archivo seleccionado» no es alcanzable— así que la única salida es
 * dibujar el control propio y dejar el nativo transparente encima.
 *
 * `<x-archivo-input>` hace eso. Este candado impide que vuelva un file crudo.
 */
class ArchivoInputTest extends TestCase
{
    public function test_ninguna_vista_usa_un_input_file_crudo(): void
    {
        $crudos = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            // getRelativePathname() + normalizar a '/': la forma anterior era
            // str_replace(resource_path('views').'/', '', getPathname()), y en
            // WINDOWS los dos caminos vienen con '\', así que el '/' concatenado
            // no matcheaba nunca → $ruta se quedaba con la ruta ABSOLUTA y las
            // dos guardas de abajo no se aplicaban jamás. Resultado: el test
            // fallaba en cualquier máquina Windows acusando al propio componente
            // y a errors/429, mientras en la CI (Linux) pasaba. Un candado que
            // no aplica su propia lista de excepciones en la plataforma donde se
            // desarrolla es un rojo falso permanente. Detectado en el gate
            // P-NAV-05 al correr la suite completa.
            $ruta = str_replace('\\', '/', $archivo->getRelativePathname());

            // El componente ES el que lleva el input nativo, y las vistas de error
            // solo lo mencionan en un comentario.
            if ($ruta === 'components/archivo-input.blade.php' || str_starts_with($ruta, 'errors/')) {
                continue;
            }

            foreach (explode("\n", File::get($archivo->getPathname())) as $n => $linea) {
                if (str_contains($linea, 'type="file"')) {
                    $crudos[] = $ruta.':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $crudos,
            "Estos file inputs son nativos: el navegador RECORTA su rótulo y en 375px no se entiende.\n".
            "Usá <x-archivo-input texto=\"…\" vacio=\"…\">, que muestra el texto completo y el nombre del archivo debajo:\n  ".
            implode("\n  ", $crudos));
    }

    public function test_el_componente_no_esconde_el_input_con_display_none(): void
    {
        // Esconder un `required` con display:none o `hidden` aborta el envío EN
        // SILENCIO: el navegador no puede enfocar el campo inválido. Esa mina ya
        // está en la bitácora por el acordeón del lote. Acá el input queda
        // TRANSPARENTE y encima del botón, así que sigue recibiendo el toque, el
        // foco y la validación.
        $comp = File::get(resource_path('views/components/archivo-input.blade.php'));

        $this->assertStringContainsString('opacity-0', $comp,
            'El input nativo debe quedar transparente, no oculto.');
        $this->assertStringContainsString('absolute inset-0', $comp,
            'El input transparente debe cubrir el botón para recibir el toque.');
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*\b(hidden|sr-only)\b/', $comp,
            'El input NO puede esconderse con hidden/sr-only: un required oculto rompe el envío en silencio.');
    }

    public function test_el_nombre_del_archivo_se_muestra_completo_y_envuelve(): void
    {
        $comp = File::get(resource_path('views/components/archivo-input.blade.php'));

        // El pedido literal del dueño: nada abreviado. `break-words` envuelve un
        // nombre largo en varias líneas en vez de recortarlo, y no debe haber
        // `truncate` ni `line-clamp` en el párrafo del nombre.
        $this->assertStringContainsString('break-words', $comp);
        $this->assertStringNotContainsString('truncate', $comp);
        $this->assertStringNotContainsString('line-clamp', $comp);
    }
}
