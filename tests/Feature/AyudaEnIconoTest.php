<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * LA AYUDA LARGA VA EN LA ⓘ, NO COMO PÁRRAFO GRIS BAJO EL CAMPO.
 *
 * Doctrina del dueño (17-08-2026), mirando el parte del técnico y señalando el listado de
 * Inventario como ejemplo: «dejarlo como un icono informativo y no como texto plano en la app».
 * En esa pantalla el campo «Trabajo realizado» tenía DOS párrafos pegados —uno de 223
 * caracteres— y el formulario se leía como un instructivo: el operario no encuentra el campo
 * entre la prosa.
 *
 * LA REGLA: una explicación que pasa de ~95 caracteres ocupa más de una línea en un teléfono y
 * es un párrafo, no una pista. Va en el globo de la ⓘ de la etiqueta (`<x-slot:ayuda>`), y
 * abajo del campo queda —si hace falta— UNA línea corta con lo operativo.
 *
 * POR QUÉ UN CANDADO ESTRUCTURAL Y NO SOLO EL BARRIDO: la prosa se acumula de a una, cada vez
 * con un buen motivo, y revisando pantalla por pantalla no se nota. Este test recorre las
 * vistas y nombra archivo:línea de cada párrafo nuevo. Las públicas del QR quedan FUERA a
 * propósito (ver más abajo).
 */
class AyudaEnIconoTest extends TestCase
{
    /** Más de esto es un párrafo: dos líneas a 375px. */
    private const TOPE = 95;

    /**
     * Excepciones documentadas. Vacío a propósito: si algún día una pantalla necesita un
     * párrafo visible, se agrega ACÁ con su motivo — no se sube el tope.
     *
     * @var array<string, string>
     */
    private const EXCEPCIONES = [
        // Dos líneas cortas, y la segunda NO es ayuda: es un aviso de ESTADO (aparece solo
        // cuando la máquina es propia, y en color de marca) que dice que el RUT deja de ser
        // obligatorio. El estado se muestra; lo que se esconde son las explicaciones.
        'components/cliente-ingreso.blade.php:57' => 'la segunda línea es un aviso de estado, no ayuda',
    ];

    /** @return array<int, array{0: string, 1: int, 2: int, 3: string}> archivo, línea, largo, texto */
    private function parrafosVisibles(string $subdirectorio): array
    {
        $hallazgos = [];

        foreach (File::allFiles(resource_path('views/'.$subdirectorio)) as $archivo) {
            if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            $relativo = str_replace(DIRECTORY_SEPARATOR, '/', $archivo->getRelativePathname());
            $html = File::get($archivo->getPathname());

            if (! preg_match_all('#<x-input-hint(\s[^>]*)?>(.*?)</x-input-hint>#s', $html, $coincidencias, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($coincidencias as $hint) {
                $texto = trim(preg_replace('/\s+/u', ' ', strip_tags($hint[2][0])));
                $largo = mb_strlen($texto);

                if ($largo <= self::TOPE) {
                    continue;
                }

                $linea = substr_count(substr($html, 0, $hint[0][1]), "\n") + 1;
                $clave = $subdirectorio.'/'.$relativo.':'.$linea;

                if (isset(self::EXCEPCIONES[$clave])) {
                    continue;
                }

                $hallazgos[] = [$subdirectorio.'/'.$relativo, $linea, $largo, mb_substr($texto, 0, 60)];
            }
        }

        return $hallazgos;
    }

    public function test_ninguna_pantalla_interna_explica_con_un_parrafo_bajo_el_campo(): void
    {
        $hallazgos = $this->parrafosVisibles('admin');

        $mensaje = "Estas ayudas pasan de ".self::TOPE." caracteres y tienen que ir en la ⓘ de la etiqueta\n"
            ."(<x-slot:ayuda>), no como párrafo bajo el campo:\n\n";
        foreach ($hallazgos as [$archivo, $linea, $largo, $texto]) {
            $mensaje .= "  {$archivo}:{$linea}  ({$largo} caracteres)  «{$texto}…»\n";
        }

        $this->assertSame([], $hallazgos, $mensaje);
    }

    /**
     * LAS DEL QR QUEDAN AFUERA, y es una decisión, no un olvido: ahí escribe el CLIENTE, una
     * sola vez, desde el teléfono, y esas ayudas son las que le dicen qué opción elegir
     * (garantía vs. reparación, qué equipos llevan N° de serie). Una ⓘ que hay que descubrir
     * cambia una elección informada por una adivinada. Este candado fija esa frontera para que
     * un barrido futuro no las "arregle" de paso.
     */
    public function test_las_pantallas_del_cliente_conservan_su_ayuda_a_la_vista(): void
    {
        $visibles = $this->parrafosVisibles('publico');

        $this->assertNotEmpty(
            $visibles,
            'Las ayudas de los formularios públicos del QR se movieron a una ⓘ: al cliente hay que '
            .'dejárselas a la vista (ver el comentario de este test).',
        );
    }

    /**
     * LA ⓘ VA COMO HERMANA DEL <label>, NUNCA ADENTRO. Un <button> dentro de un <label> es
     * interactivo-dentro-de-interactivo: al tocar la ayuda el navegador además enfoca o
     * conmuta el campo, así que en un checkbox la ⓘ lo marcaría.
     */
    public function test_el_icono_de_ayuda_no_queda_dentro_de_la_etiqueta(): void
    {
        $componente = File::get(resource_path('views/components/input-label.blade.php'));

        $this->assertStringContainsString('<x-info-tip>', $componente);
        $this->assertDoesNotMatchRegularExpression(
            '#<label\b[^>]*>(?:(?!</label>).)*<x-info-tip>#s',
            $componente,
            'La ⓘ quedó DENTRO del <label>: tocar la ayuda va a enfocar o conmutar el campo.',
        );
    }

    /**
     * Y LO QUE SE ACUMULA EN UN MISMO CAMPO, que es el hueco que dejó la primera versión de esta
     * regla: el dueño volvió con una captura de «Causa de la falla», que tenía DOS ayudas de 80 y
     * 86 caracteres. Cada una pasaba sola el corte —ninguna es un párrafo— y apiladas eran dos
     * renglones de prosa bajo el campo. Se mide por CAMPO, no por texto.
     *
     * Alternativas no suman: dos ayudas separadas por un `@else`/`@elseif` no pueden verse a la
     * vez (una es la rama de crear y la otra la de editar), así que se cuentan por grupo.
     */
    public function test_ningun_campo_interno_acumula_prosa(): void
    {
        $hallazgos = [];

        foreach (['admin', 'components'] as $subdirectorio) {
            foreach (File::allFiles(resource_path('views/'.$subdirectorio)) as $archivo) {
                if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                    continue;
                }

                $relativo = $subdirectorio.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $archivo->getRelativePathname());
                $html = File::get($archivo->getPathname());

                foreach ($this->gruposDeAyuda($html) as $grupo) {
                    $suma = array_sum(array_column($grupo, 'largo'));

                    if (count($grupo) < 2 && $suma <= self::TOPE) {
                        continue;
                    }

                    $clave = $relativo.':'.$grupo[0]['linea'];

                    if (isset(self::EXCEPCIONES[$clave])) {
                        continue;
                    }

                    $hallazgos[] = $clave.'  ('.count($grupo).' ayudas, '.$suma.' caracteres)';
                }
            }
        }

        $this->assertSame([], $hallazgos, "Estos CAMPOS acumulan prosa bajo el control. Dejá una sola línea corta\n"
            ."con lo operativo y mové el resto a la ⓘ de la etiqueta:\n\n  ".implode("\n  ", $hallazgos)."\n");
    }

    /**
     * Las ayudas agrupadas por campo (la etiqueta más cercana hacia arriba), y dentro del campo
     * separadas por rama: un `@else`/`@elseif` entre dos ayudas significa que son alternativas.
     *
     * @return array<int, array<int, array{linea: int, largo: int}>>
     */
    private function gruposDeAyuda(string $html): array
    {
        if (! preg_match_all('#<x-input-hint(\s[^>]*)?>(.*?)</x-input-hint>#s', $html, $coincidencias, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $porCampo = [];

        foreach ($coincidencias as $hint) {
            $antes = substr($html, 0, $hint[0][1]);
            $etiqueta = strrpos($antes, '<x-input-label');

            $porCampo[$etiqueta === false ? 'sin-etiqueta' : $etiqueta][] = [
                'linea' => substr_count($antes, "\n") + 1,
                'largo' => mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags($hint[2][0])))),
                'desde' => $hint[0][1],
            ];
        }

        $grupos = [];

        foreach ($porCampo as $hints) {
            $actual = [array_shift($hints)];

            foreach ($hints as $hint) {
                $entre = substr($html, $actual[count($actual) - 1]['desde'], $hint['desde'] - $actual[count($actual) - 1]['desde']);

                if (preg_match('/@(else|elseif)\b/', $entre)) {
                    $grupos[] = $actual;
                    $actual = [];
                }

                $actual[] = $hint;
            }

            $grupos[] = $actual;
        }

        return $grupos;
    }
}
