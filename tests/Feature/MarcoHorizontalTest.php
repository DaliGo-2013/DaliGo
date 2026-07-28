<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Marco horizontal: dos capas como máximo en móvil (ver «Marco horizontal» en
 * las Reglas de diseño de CLAUDE.md).
 *
 * El defecto que previene NO se ve en escritorio: ahí el aire ordena. En 375px
 * el campo «Equipo» del formulario por cantidad tenía 217px de ancho útil sobre
 * 375 porque cinco capas de padding sumaban 158px, y buena parte era invisible
 * (una tarjeta blanca dentro de otra tarjeta blanca no muestra su borde, pero sí
 * cobra su padding). Como es un defecto de móvil, un candado estructural es lo
 * único que lo caza: nadie lo nota revisando en un monitor.
 */
class MarcoHorizontalTest extends TestCase
{
    /** Las pantallas públicas del QR, que es donde el cliente externo sufre el margen. */
    private const VISTAS_QR = [
        'publico/taller/create.blade.php',
        'publico/taller/create-lote.blade.php',
        'publico/taller/create-visita.blade.php',
    ];

    public function test_el_marco_de_pagina_se_declara_solo_en_el_layout(): void
    {
        // guest-layout emite el marco (px-3 sm:px-6) y la tarjeta blanca (px-4
        // sm:px-8). Si una vista escribe su propio px- de PÁGINA, se suma.
        $guest = File::get(resource_path('views/layouts/guest.blade.php'));

        $this->assertStringContainsString('px-3 py-12 sm:px-6', $guest,
            'El marco de página del layout de invitado debe ser mobile-first (px-3 y el px-6 desde sm:).');
        $this->assertStringContainsString('px-4 py-6 shadow-sm sm:px-8 sm:py-8', $guest,
            'La tarjeta blanca del layout de invitado debe ser mobile-first (px-4 y el px-8 desde sm:).');
    }

    public function test_las_vistas_del_qr_no_dibujan_tarjetas_de_seccion_a_mano(): void
    {
        // El patrón prohibido: el marco COMPLETO escrito a mano sin variante sm:.
        // Eso es lo que cobraba 16px por lado en móvil sin mostrar su borde.
        foreach (self::VISTAS_QR as $vista) {
            $html = File::get(resource_path('views/'.$vista));

            $this->assertDoesNotMatchRegularExpression(
                '/class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm/',
                $html,
                "{$vista}: usa <x-seccion> en vez de la tarjeta de sección a mano ".
                '(en móvil no debe dibujar marco; ver «Marco horizontal» en CLAUDE.md).',
            );
        }
    }

    public function test_ninguna_tarjeta_cobra_su_padding_de_escritorio_en_movil(): void
    {
        // El barrido del 28-07: 78 tarjetas declaraban un padding único (p-4 a
        // p-8) que el celular pagaba igual que el monitor. Ahora TODAS declaran
        // el valor de móvil y el de escritorio detrás de sm:. Este candado
        // impide que una vista nueva vuelva al padding único — es el defecto que
        // nadie nota revisando en pantalla grande.
        $sinVariante = [];

        foreach (File::allFiles(resource_path('views')) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }
            // Los correos van con tablas y padding fijo a propósito: los
            // clientes de correo no entienden media queries.
            if (str_contains($archivo->getPathname(), '/emails/')) {
                continue;
            }

            $lineas = explode("\n", File::get($archivo->getPathname()));
            foreach ($lineas as $n => $linea) {
                // Tarjeta = fondo blanco + padding parejo de 4 o más, sin sm:.
                if (preg_match('/bg-white p-[4-8] shadow-sm(?! sm:p-)/', $linea)) {
                    $sinVariante[] = str_replace(resource_path('views').'/', '', $archivo->getPathname()).':'.($n + 1);
                }
            }
        }

        $this->assertSame([], $sinVariante,
            "Estas tarjetas cobran en móvil el padding pensado para escritorio. Declaralo mobile-first ".
            "(p-3/p-4 y el valor grande detrás de sm:) — ver «Marco horizontal» en CLAUDE.md:\n  ".
            implode("\n  ", $sinVariante));
    }

    public function test_la_seccion_no_dibuja_marco_en_movil_y_lo_recupera_en_escritorio(): void
    {
        $seccion = File::get(resource_path('views/components/seccion.blade.php'));

        // Todo el marco va detrás de sm:. Si alguna de estas clases pierde el
        // prefijo, el componente vuelve a cobrar padding en el celular.
        foreach (['sm:rounded-2xl', 'sm:border', 'sm:bg-white', 'sm:p-4', 'sm:shadow-sm'] as $clase) {
            $this->assertStringContainsString($clase, $seccion,
                "<x-seccion> debe declarar {$clase} (el marco solo existe desde sm:).");
        }

        // Y NO debe traer las mismas clases sin prefijo. Se mira SOLO la línea
        // del merge de atributos: los comentarios del componente citan el patrón
        // viejo como ejemplo de lo que reemplaza, y buscarlo en todo el archivo
        // hacía que el candado se cazara a sí mismo.
        preg_match('/\$attributes->merge\(\[.*?\]\)/s', $seccion, $m);
        $this->assertNotEmpty($m, 'No se encontró el merge de atributos de <x-seccion>.');
        $clases = $m[0];

        foreach (['p-4', 'bg-white', 'rounded-2xl', 'border-neutral-200', 'shadow-sm'] as $clase) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<!:)\b'.preg_quote($clase, '/').'\b/',
                $clases,
                "<x-seccion> no debe declarar {$clase} sin el prefijo sm: (en móvil no dibuja marco).",
            );
        }
    }
}
