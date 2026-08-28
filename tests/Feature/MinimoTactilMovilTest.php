<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * MÍNIMO TÁCTIL DE 44px EN MÓVIL (dueño, 28-08-2026).
 *
 * «Mejoremos lo de los píxeles para que sea prolijo en versión responsive mobile,
 *  me preocupa para el tema vendedores que andan afuera en terreno: necesitan que
 *  la app funcione bien y no tenga bug o mal diseño… ajustar los parámetros y que
 *  no queden espacios en blanco absurdos.»
 *
 * El número no es nuevo: `icon-button` ya lo declaraba («el mínimo táctil de
 * Apple») y las filas del panel de la campanita ya lo usaban. Lo que faltaba era
 * aplicarlo a los controles COMPARTIDOS, que es donde se multiplica: medido a
 * 375px, los botones daban 40px, los inputs 42, los ítems del menú 40, las
 * pestañas 32 y el menú de cuenta 36.
 *
 * DOS razones para que sea un candado estructural y no una medición de pantalla:
 *
 *  1. Es un defecto que revisando en escritorio NO se ve — ahí el mínimo está
 *     liberado a propósito y todo mide lo de siempre.
 *  2. Se rompe por omisión: alguien crea un botón nuevo copiando otro archivo y
 *     el mínimo no viaja. El test recorre los componentes, así que el que falte
 *     aparece con nombre.
 *
 * Por qué `min-h-*` y no más `py-*`: un mínimo solo crece lo que está corto. Un
 * botón de 40px sube a 44 y una fila de 88 no se mueve — eso es lo que evita los
 * «espacios en blanco absurdos». Verificado: a 768 y 1280 los mismos controles
 * miden exactamente lo que medían antes (botón 40, select 39, fila 60).
 */
class MinimoTactilMovilTest extends TestCase
{
    /**
     * Componentes compartidos que se tocan con el dedo, con el prefijo desde el
     * cual se LIBERA el mínimo.
     *
     * `sidebar-item` va con `max-lg:` y no con `sm:` porque el menú lateral es un
     * drawer táctil hasta 1024px: a 768 todavía se toca con el dedo. Los demás
     * recuperan la densidad de escritorio en `sm:`.
     */
    private const CONTROLES = [
        'primary-button' => 'sm',
        'secondary-button' => 'sm',
        'danger-button' => 'sm',
        'button-link' => 'sm',
        'text-input' => 'sm',
        'select' => 'sm',
        'dropdown-link' => 'sm',
        'tab-nav' => 'sm',
        'sidebar-item' => 'max-lg',
    ];

    /**
     * El Blade SIN sus comentarios.
     *
     * No es un detalle: cada uno de estos componentes lleva un comentario que
     * NOMBRA la clase para explicar por qué está, y con el archivo entero como
     * pajar el assert matcheaba el COMENTARIO. Se cazó mutando —le quité el
     * `min-h-11` real a `secondary-button` y el test siguió verde—, que es el
     * verde-engañoso de la bitácora [2026-07-20] causado por documentar el fix.
     */
    private function sinComentarios(string $componente): string
    {
        $blade = File::get(resource_path("views/components/{$componente}.blade.php"));

        return preg_replace('/\{\{--.*?--\}\}/s', '', $blade);
    }

    public function test_los_controles_compartidos_declaran_el_minimo_tactil(): void
    {
        foreach (self::CONTROLES as $componente => $prefijo) {
            $blade = $this->sinComentarios($componente);

            // El mínimo, con el prefijo que corresponda a cómo se libera.
            $esperado = $prefijo === 'max-lg' ? 'max-lg:min-h-11' : 'min-h-11';

            $this->assertStringContainsString($esperado, $blade,
                "[{$componente}] no declara el mínimo táctil de 44px: en el celular mide menos "
                .'y los vendedores lo tocan en terreno. Ver primary-button.blade.php.');

            // Y que se LIBERE en escritorio: sin esto, la densidad de escritorio se
            // pierde en toda la app (que es el otro modo de romper esto).
            if ($prefijo === 'sm') {
                $this->assertStringContainsString('sm:min-h-0', $blade,
                    "[{$componente}] fija el mínimo también en escritorio: ahí hay mouse y "
                    .'la densidad no se sacrifica.');
            }
        }
    }

    /**
     * La campana de la barra móvil, que es el control más tocado de la app: está
     * en TODAS las pantallas. Vive en su propio partial (`layout.topbar`, que es
     * `lg:hidden`), no en un componente, así que se verifica aparte.
     */
    public function test_la_campana_de_la_barra_movil_tiene_el_minimo(): void
    {
        $blade = preg_replace('/\{\{--.*?--\}\}/s',
            '', File::get(resource_path('views/components/layout/topbar.blade.php')));

        $this->assertStringContainsString('min-h-11 min-w-11', $blade,
            'La campana de la barra móvil medía 40x40. Es el único control de esa barra y '
            .'está en todas las pantallas.');

        // Esta barra SOLO existe en móvil, así que acá el mínimo NO se libera.
        $this->assertStringNotContainsString('sm:min-h-0', $blade,
            'La barra móvil es `lg:hidden`: liberar el mínimo en `sm:` lo apagaría justo en '
            .'las tablets, donde igual se toca con el dedo.');
    }

    /**
     * Contra-candado: el mínimo NO se cuela en los componentes de escritorio puro
     * ni en los correos.
     *
     * Sin esta mitad, «arreglar» este test sería poner `min-h-11` en todo, que es
     * exactamente el «espacio en blanco absurdo» que el dueño pidió evitar. Los
     * correos van con tablas y padding fijo porque los clientes de correo no
     * entienden media queries (regla de la casa).
     */
    public function test_el_minimo_no_se_cuela_en_los_correos(): void
    {
        $infractores = [];

        foreach (File::allFiles(resource_path('views/emails')) as $archivo) {
            if (str_contains(File::get($archivo->getPathname()), 'min-h-11')) {
                $infractores[] = str_replace('\\', '/', $archivo->getRelativePathname());
            }
        }

        $this->assertSame([], $infractores,
            'Los correos no llevan utilidades responsive: '.implode(', ', $infractores));
    }
}
