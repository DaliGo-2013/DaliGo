<?php

namespace App\Services\Carga;

// La LETRA de cada producto sale de SimuladorCargaController: es la única fuente
// (lo dice §4.1septies de las reglas), así que el lienzo, la lista de la pantalla
// y esta planilla no pueden desalinearse. Importarlo acuerda con esa regla; tener
// una segunda función que genere letras sería exactamente lo que evita.
use App\Http\Controllers\Admin\SimuladorCargaController;
use App\Models\TipoBulto;
use App\Services\Excel\EscritorXlsx;
use App\Services\Excel\FilasXlsx;
use App\Support\FechaNegocio;
use Illuminate\Support\Carbon;

/**
 * El plan de carga como archivo Excel (.xlsx), pedido del dueño (10-08-2026): que
 * el resultado del simulador deje de vivir solo en la pantalla y se pueda bajar
 * para el andén, el conductor o la cotización.
 *
 * Sin dependencias nuevas: reusa `App\Services\Excel\EscritorXlsx` y `FilasXlsx`,
 * el escritor compartido que ya generan la carta Gantt y la flota — verificado
 * abriendo los archivos con Excel de verdad.
 *
 * LO QUE HACE ÚTIL A ESTA PLANILLA no es repetir los números de la pantalla, es
 * el **ORDEN DE CARGA**: qué bloque va contra la cabina y cuál contra la puerta.
 * Es el dato que el andén no tiene de ninguna otra forma y el que convierte una
 * simulación en una instrucción.
 *
 * Recibe los datos YA CALCULADOS por el controlador — los mismos que pinta la
 * pantalla, no un segundo cálculo. Si la descarga recalculara por su cuenta,
 * empezaría a diferir de lo que el usuario está mirando: el defecto clásico de
 * este tipo de botón, que ya está documentado para el Excel de la flota.
 */
class PlanDeCargaExcel
{
    /**
     * Por qué quedó carga afuera, en castellano. El motor los emite como códigos
     * (`espacio`, `pallet_vacio`) y esto es lo que se imprime.
     *
     * @var array<string, string>
     */
    private const MOTIVOS = [
        'espacio' => 'No queda espacio con el resto de la carga',
        'peso' => 'Se pasa de la carga máxima en kilos',
        'largo' => 'No entra por el largo de la caja',
        'ancho' => 'No entra por el ancho de la caja',
        'alto' => 'No entra por la altura de la caja',
        'pallet_vacio' => 'No entra ni una encima del pallet',
        'ninguno' => 'No entra en esta caja de carga',
    ];

    /** @var array<int, string> nombres de las 8 columnas de la tabla de productos */
    private const COLUMNAS = [
        ['Cód.', 6],
        ['Producto', 42],
        ['Cómo viaja', 22],
        ['Pedidas', 10],
        ['Cargadas', 10],
        ['Faltan', 10],
        ['Bultos', 9],
        ['Por qué falta', 40],
    ];

    private FilasXlsx $filas;

    private Carbon $hoy;

    public function __construct()
    {
        $this->hoy = Carbon::parse(FechaNegocio::hoy());
    }

    /** Nombre de descarga, fechado con el día de negocio. */
    public static function nombreArchivo(): string
    {
        return 'Plan_de_carga_'.FechaNegocio::hoy().'.xlsx';
    }

    /**
     * @param  array<string, mixed>  $datos  lo que el controlador le pasó a la vista
     */
    public function generar(array $datos): string
    {
        $this->filas = new FilasXlsx(self::ESTILOS);

        $this->cabecera($datos);
        $this->productos($datos);
        $this->ordenDeCarga($datos);

        return EscritorXlsx::armar(['Plan de carga' => $this->hoja()], $this->estilos());
    }

    // ------------------------------------------------------------------
    // Contenido
    // ------------------------------------------------------------------

    /** @param  array<string, mixed>  $d */
    private function cabecera(array $d): void
    {
        $camion = $d['camion'] ?? null;
        $modo = $d['mixta'] !== null
            ? '¿Cabe esta carga?'
            : (($d['enPallet'] ?? null) !== null ? 'Sobre pallet' : '¿Cuánto entra?');

        $this->filas->celdas([[1, 'PLAN DE CARGA · DALI', 'titulo']]);
        $this->filas->celdas([[1, sprintf(
            'Generado el %s · Modo: %s · %s',
            $this->hoy->format('d-m-Y'),
            $modo,
            $camion?->nombre ?? 'sin camión',
        ), 'sub']]);

        if ($camion) {
            $this->filas->celdas([[1, sprintf(
                'Caja útil %s × %s × %s m · %s m³ · carga máxima %s kg',
                number_format($camion->largo_cm / 100, 2, ',', '.'),
                number_format($camion->ancho_cm / 100, 2, ',', '.'),
                number_format($camion->alto_cm / 100, 2, ',', '.'),
                number_format($camion->largo_cm * $camion->ancho_cm * $camion->alto_cm / 1_000_000, 1, ',', '.'),
                number_format((int) $camion->peso_max_kg, 0, ',', '.'),
            ), 'sub']]);
        }

        // El aviso que la pantalla también da: los números son un TECHO mientras
        // el factor no esté calibrado. Sacarlo del papel sería prometer más de lo
        // que el propio motor dice que sabe.
        $this->filas->celdas([[1, 'Los cupos son un máximo geométrico (pasillo 0, factor 1). Verificar contra la carga real antes de comprometer un viaje.', 'aviso']]);
        $this->filas->vacia();
    }

    /** @param  array<string, mixed>  $d */
    private function productos(array $d): void
    {
        $cabeceras = [];
        foreach (self::COLUMNAS as $i => [$titulo, $ancho]) {
            $cabeceras[] = [$i + 1, $titulo, 'cab'];
        }
        $this->filas->celdas($cabeceras);

        // Carga mixta: una fila por línea, con lo que quedó afuera y por qué.
        if ($d['mixta'] !== null) {
            foreach ($d['mixta']['lineas'] as $i => $l) {
                $faltan = max(0, $l['pedidas_unidades'] - $l['cargadas_unidades']);
                $this->filas->celdas([
                    [1, SimuladorCargaController::letra($i), 'negrita'],
                    [2, $l['modelo']->nombre, 'texto'],
                    [3, TipoBulto::ESTIBAS_ELEGIBLES[$l['estiba']] ?? $l['estiba'], 'texto'],
                    [4, $l['pedidas_unidades'], 'numero'],
                    [5, $l['cargadas_unidades'], 'numero'],
                    [6, $faltan ?: null, $faltan ? 'falta' : 'numero'],
                    [7, $l['bultos_colocados'], 'numero'],
                    // El motivo va en castellano y no con el código del motor: la planilla
                    // circula por correo, así que la lee gente que nunca vio la pantalla.
                    [8, self::MOTIVOS[$l['motivo']] ?? $l['motivo'], 'ajustado'],
                ]);
            }

            $this->filas->vacia();
            $this->filas->celdas([[1, $d['mixta']['cabeTodo'] ? 'CABE TODO' : 'NO CABE TODO', $d['mixta']['cabeTodo'] ? 'ok' : 'falta']]);

            return;
        }

        // Cupo máximo / sobre pallet: un solo producto.
        $bulto = $d['bulto'] ?? null;
        $unidades = ($d['enPallet'] ?? null) !== null
            ? $d['enPallet']['unidadesTotales']
            : ($d['resultado']['unidades'] ?? 0);
        $bultos = ($d['enPallet'] ?? null) !== null
            ? $d['enPallet']['cabenPallets']
            : ($d['resultado']['bultos'] ?? 0);

        $this->filas->celdas([
            [1, 'A', 'negrita'],
            [2, $bulto?->nombre ?? '—', 'texto'],
            [3, TipoBulto::ESTIBAS_ELEGIBLES[$d['estiba'] ?? 'auto'] ?? 'Automático', 'texto'],
            [5, $unidades, 'numero'],
            [7, $bultos, 'numero'],
            [8, ($d['enPallet'] ?? null) !== null ? 'Total apilando pallets' : 'Máximo del camión vacío', 'ajustado'],
        ]);
    }

    /**
     * EL ORDEN DE CARGA: qué va primero contra la cabina y qué queda en la puerta.
     *
     * Es lo que el andén no puede deducir de la pantalla sin mirar el dibujo, y la
     * razón de ser de esta planilla. Sale de los bloques de la escena, que ya
     * vienen ordenados fondo → puerta (es el mismo orden en que el visor los
     * anima), así que acá no se reordena nada: se numera lo que el motor decidió.
     *
     * @param  array<string, mixed>  $d
     */
    private function ordenDeCarga(array $d): void
    {
        $bloques = $d['escena']['bloques'] ?? [];
        if ($bloques === []) {
            return;
        }

        $this->filas->vacia();
        $this->filas->celdas([[1, 'ORDEN DE CARGA — del fondo hacia la puerta', 'seccion'], [2, '', 'seccion'], [3, '', 'seccion'], [4, '', 'seccion']]);
        $this->filas->celdas([
            [1, '#', 'cab'], [2, 'Producto', 'cab'], [3, 'Cód.', 'cab'],
            [4, 'Bultos', 'cab'], [5, 'Rejilla (largo × ancho × alto)', 'cab'],
        ]);

        foreach (array_values($bloques) as $n => $b) {
            $r = $b['rejilla'];
            $this->filas->celdas([
                [1, $n + 1, 'numero'],
                [2, $b['nombre'] ?? '—', 'texto'],
                [3, $b['letra'] ?? '', 'negrita'],
                [4, $b['cantidad'], 'numero'],
                [5, sprintf('%d × %d × %d', $r['largo'], $r['ancho'], $r['alto']), 'texto'],
            ]);
        }
    }

    // ------------------------------------------------------------------
    // La hoja y sus estilos
    // ------------------------------------------------------------------

    private function hoja(): string
    {
        $cols = '<cols>';
        foreach (self::COLUMNAS as $i => [$titulo, $ancho]) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$ancho.'" customWidth="1"/>';
        }
        $cols .= '</cols>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetFormatPr defaultRowHeight="14"/>'
            .$cols
            .'<sheetData>'.$this->filas->xml().'</sheetData>'
            .'</worksheet>';
    }

    /** Índice estilo → posición en cellXfs. Contrato con estilos(). */
    private const ESTILOS = [
        'texto' => 0, 'negrita' => 1, 'titulo' => 2, 'sub' => 3, 'cab' => 4,
        'numero' => 5, 'ajustado' => 6, 'aviso' => 7, 'falta' => 8, 'ok' => 9,
        'seccion' => 10,
    ];

    private function estilos(): string
    {
        $fonts = '<fonts count="7">'
            .'<font><sz val="10"/><name val="Arial"/></font>'                                    // 0 normal
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'                                // 1 negrita
            .'<font><b/><sz val="16"/><color rgb="FFC2410C"/><name val="Arial"/></font>'         // 2 titulo
            .'<font><sz val="9"/><color rgb="FF737373"/><name val="Arial"/></font>'              // 3 sub gris
            .'<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'          // 4 blanca negrita
            .'<font><b/><sz val="10"/><color rgb="FFB71C1C"/><name val="Arial"/></font>'         // 5 rojo negrita
            .'<font><b/><sz val="10"/><color rgb="FF2E7D32"/><name val="Arial"/></font>'         // 6 verde negrita
            .'</fonts>';

        $solido = fn (string $rgb) => "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"FF$rgb\"/></patternFill></fill>";
        $fills = '<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .$solido('2B2B2B')   // 2 cabecera
            .$solido('FFF7ED')   // 3 aviso (naranja muy suave)
            .$solido('FFCDD2')   // 4 falta (rojo pálido)
            .$solido('455A64')   // 5 sección
            .'</fills>';

        $xf = fn (int $font, int $fill, bool $wrap = false) => '<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($fill ? ' applyFill="1"' : '').' applyFont="1"'
            .($wrap ? ' applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' : '/>');

        $xfC = fn (int $font, int $fill) => '<xf numFmtId="0" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($fill ? ' applyFill="1"' : '').' applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';

        $cellXfs = '<cellXfs count="11">'
            .$xf(0, 0)            // 0 texto
            .$xf(1, 0)            // 1 negrita
            .$xf(2, 0)            // 2 titulo
            .$xf(3, 0)            // 3 sub
            .$xf(4, 2)            // 4 cab
            .$xfC(0, 0)           // 5 numero
            .$xf(0, 0, true)      // 6 ajustado
            .$xf(3, 3)            // 7 aviso
            .$xfC(5, 4)           // 8 falta
            .$xf(6, 0)            // 9 ok
            .$xf(4, 5)            // 10 seccion
            .'</cellXfs>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$fonts.$fills
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .$cellXfs
            .'</styleSheet>';
    }
}
