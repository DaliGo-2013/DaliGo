<?php

namespace App\Services\Plan;

use App\Models\PlanExtra;
use App\Support\FechaNegocio;
use App\Support\PlanProyecto;
use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * Genera la Carta Gantt del proyecto como archivo Excel (.xlsx), alimentada de
 * PlanProyecto — la MISMA fuente que pinta la pagina /plan. Pedido del dueño
 * (03-08-2026): un boton de descarga para que el Excel que circula en las
 * reuniones deje de mantenerse a mano y salga siempre al dia del repo.
 *
 * Sin dependencias: un .xlsx es un ZIP de XMLs y ZipArchive viene con PHP.
 * Se escriben las 6 partes minimas del formato OOXML con strings INLINE
 * (t="inlineStr"), que evita la tabla de sharedStrings.
 *
 * Semaforo (pedido de Carlos, jefe de proyecto): verde realizada / amarillo en
 * curso / rojo atrasada (fecha fin del plan pasada y <100%) / gris no iniciada.
 * Es el mismo criterio del Excel entregado el 03-08; aca vive en codigo y no
 * vuelve a desactualizarse.
 */
class CartaGanttExcel
{
    // Colores (RGB hex, sin #). Los mismos del Excel entregado a Carlos.
    private const COLORES = [
        'realizada' => ['solido' => '2E7D32', 'pale' => 'C8E6C9', 'texto' => 'FFFFFF'],
        'en_curso' => ['solido' => 'F9A825', 'pale' => 'FFF3CD', 'texto' => '3C2800'],
        'atrasada' => ['solido' => 'C62828', 'pale' => 'FFCDD2', 'texto' => 'FFFFFF'],
        'no_iniciada' => ['solido' => '9E9E9E', 'pale' => 'EEEEEE', 'texto' => 'FFFFFF'],
    ];

    private const ETIQUETAS = [
        'realizada' => 'Realizada',
        'en_curso' => 'En curso',
        'atrasada' => 'Atrasada',
        'no_iniciada' => 'No iniciada',
    ];

    /** @var array<int, string> filas XML de la hoja ya armadas */
    private array $filas = [];

    private int $fila = 0;

    private int $totalSemanas;

    private Carbon $inicio;

    private Carbon $hoy;

    public function __construct()
    {
        $this->inicio = Carbon::parse(PlanProyecto::INICIO_PROYECTO);
        $fin = Carbon::parse(PlanProyecto::FIN_PROYECTO);
        $this->totalSemanas = (int) floor($this->inicio->diffInDays($fin) / 7) + 1;
        $this->hoy = Carbon::parse(FechaNegocio::hoy());
    }

    /** El contenido binario del .xlsx, listo para descargar. */
    public function generar(): string
    {
        $tracker = PlanProyecto::tracker();
        $sheet = $this->hoja($tracker);

        $tmp = tempnam(sys_get_temp_dir(), 'gantt');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el zip del Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsRaiz());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsWorkbook());
        $zip->addFromString('xl/styles.xml', $this->estilos());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->hojaAvance($tracker));
        $zip->close();

        $binario = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $binario;
    }

    /** Nombre de descarga, fechado con el dia de negocio. */
    public static function nombreArchivo(): string
    {
        return 'Carta_Gantt_DaliGo_'.FechaNegocio::hoy().'.xlsx';
    }

    /**
     * El semaforo de Carlos a partir del % del tracker y las fechas del plan.
     * Distinto de PlanProyecto::estadoDe (los 3 estados de la pagina): aca se
     * agrega 'atrasada' porque el Excel es el que se lee en la reunion de avance.
     */
    public function semaforo(int $pct, string $fin, string $inicio): string
    {
        if ($pct >= 100) {
            return 'realizada';
        }
        if (Carbon::parse($fin)->lt($this->hoy)) {
            return 'atrasada';
        }
        if ($pct > 0 || Carbon::parse($inicio)->lte($this->hoy)) {
            return 'en_curso';
        }

        return 'no_iniciada';
    }

    /** Semana del proyecto (1..N) para una fecha. */
    private function semana(Carbon $fecha): int
    {
        $n = (int) floor($this->inicio->diffInDays($fecha, false) / 7) + 1;

        return max(1, min($this->totalSemanas, $n));
    }

    // ------------------------------------------------------------------
    // La hoja
    // ------------------------------------------------------------------

    private function hoja(array $tracker): string
    {
        $colGrid = 10;                     // J: primera semana
        $ultimaCol = $colGrid + $this->totalSemanas - 1;

        // --- Titulo + resumen global -----------------------------------
        $pct = $tracker['pct_global'];
        $peso = $tracker['total']['peso'] ?? 0;
        $aporta = $tracker['total']['aporta'] ?? 0;

        $this->filaCeldas([[1, 'DALI Cargos-Transporte · Carta Gantt — Sistema de gestión (App DALI)', 'titulo']]);
        $this->filaCeldas([[1, 'Generada el '.$this->hoy->format('d-m-Y').' desde el tracker del repositorio (RUTA-MAESTRA §10) — la misma fuente que la página /plan. Cada descarga sale al día.', 'sub']]);
        $this->filaCeldas([[1, "AVANCE GLOBAL: {$pct}%   ({$aporta} de {$peso} puntos ponderados por esfuerzo)", 'banner']]);
        $this->filaVacia();

        // --- Leyenda ----------------------------------------------------
        $this->filaCeldas([
            [1, 'Leyenda:', 'negrita'],
            [3, 'Realizada', 'chip_realizada'], [5, 'En curso', 'chip_en_curso'],
            [7, 'Atrasada', 'chip_atrasada'], [9, 'No iniciada', 'chip_no_iniciada'],
            [11, 'Tono claro en la barra = tramo que aún falta · Atrasada = fin de plan pasado y <100%', 'sub'],
        ]);
        $this->filaVacia();

        // --- Cabecera: meses + numeros de semana ------------------------
        $celdasMes = [];
        for ($m = $this->inicio->copy()->startOfMonth(); $m->lte(Carbon::parse(PlanProyecto::FIN_PROYECTO)); $m = $m->copy()->addMonth()) {
            $etiqueta = mb_strtoupper($m->translatedFormat($m->month === 1 || $m->equalTo($this->inicio->copy()->startOfMonth()) ? 'M Y' : 'M'));
            $celdasMes[] = [$colGrid + $this->semana($m->copy()->max($this->inicio)) - 1, $etiqueta, 'mes'];
        }
        $celdasMes[] = [$colGrid + $this->semana($this->hoy) - 1, 'HOY', 'hoy'];
        $this->filaCeldas($celdasMes);

        $cab = [
            [1, 'Cód.', 'cab'], [2, 'Módulo / Fase', 'cab'], [3, 'Fase', 'cab'],
            [4, 'Peso', 'cab'], [5, '% Avance', 'cab'], [6, 'Estado', 'cab'],
            [7, 'Inicio', 'cab'], [8, 'Fin', 'cab'], [9, 'Días atraso', 'cab'],
        ];
        for ($s = 1; $s <= $this->totalSemanas; $s++) {
            $cab[] = [$colGrid + $s - 1, (string) $s, 'cab_sem'];
        }
        $this->filaCeldas($cab);

        // --- Fase 0 · Discovery -------------------------------------------
        // El tracker no lleva las sub-tareas del discovery (cerraron en junio);
        // mantener esa lista aca seria una segunda copia que driftea. Una fila
        // resumen, realizada, y listo.
        $this->filaCeldas([[1, 'FASE 0 · Discovery y planificación  (May–Jun) — cerrada', 'seccion']]);
        $barraF0 = [];
        for ($k = 1; $k <= $this->semana(Carbon::parse('2026-06-30')); $k++) {
            $barraF0[] = [$colGrid + $k - 1, null, 'barra_realizada'];
        }
        $this->filaCeldas(array_merge([
            [1, 'F0', 'negrita'], [2, 'Discovery: requerimientos, entrevistas, arquitectura, presupuesto', 'texto'],
            [5, 1.0, 'pct'], [6, self::ETIQUETAS['realizada'], 'chip_realizada'],
            [7, '01-05-2026', 'texto'], [8, '30-06-2026', 'texto'],
        ], $barraF0));

        // --- Modulos, agrupados por fase (la forma del Excel de referencia) ---
        $secciones = [
            'F1' => 'FASE 1 · Fundación + módulos transversales  (para las 3 sucursales)',
            'F2' => 'FASE 2 · Núcleo operativo Mirador + prioridades',
            'F3' => 'FASE 3 · Piloto MVP en Mirador 150',
            'F4' => 'FASE 4 · Rollout Abate Molina',
            'F5' => 'FASE 5 · Inicio Coquimbo + cierre',
        ];
        $faseAbierta = null;
        foreach (PlanProyecto::MODULOS as $key => $modulo) {
            if ($modulo['fase'] !== $faseAbierta) {
                $faseAbierta = $modulo['fase'];
                $this->filaCeldas([[1, $secciones[$faseAbierta] ?? $faseAbierta, 'seccion']]);
            }
            $filaTracker = $tracker['filas'][$key] ?? null;
            $pctItem = (int) ($filaTracker['pct'] ?? 0);
            $estado = $this->semaforo($pctItem, $modulo['fin'], $modulo['inicio']);
            $sIni = $this->semana(Carbon::parse($modulo['inicio']));
            $sFin = $this->semana(Carbon::parse($modulo['fin']));
            $atraso = $estado === 'atrasada' ? (int) Carbon::parse($modulo['fin'])->diffInDays($this->hoy) : null;

            $celdas = [
                [1, $key, 'negrita'],
                [2, $filaTracker['item'] ?? $modulo['label'], 'texto'],
                [3, $modulo['fase'], 'texto'],
                [4, $filaTracker['peso'] ?? null, 'numero'],
                [5, $pctItem / 100, 'pct'],
                [6, self::ETIQUETAS[$estado], 'chip_'.$estado],
                [7, Carbon::parse($modulo['inicio'])->format('d-m-Y'), 'texto'],
                [8, Carbon::parse($modulo['fin'])->format('d-m-Y'), 'texto'],
                [9, $atraso, 'numero'],
            ];

            // La barra: tramo avanzado en solido, lo que falta en palido. Mismas
            // guardas de honestidad del Excel manual: <100% nunca llena; >0%
            // muestra al menos una semana.
            $n = max($sFin - $sIni + 1, 1);
            $solidas = (int) round($n * $pctItem / 100);
            if ($pctItem < 100 && $solidas >= $n) {
                $solidas = $n - 1;
            }
            if ($pctItem > 0 && $solidas < 1) {
                $solidas = 1;
            }
            for ($k = 0; $k < $n; $k++) {
                $celdas[] = [$colGrid + $sIni - 1 + $k, null, ($k < $solidas ? 'barra_' : 'barrap_').$estado];
            }

            $this->filaCeldas($celdas);
        }

        // --- Hitos --------------------------------------------------------
        $this->filaVacia();
        $this->filaCeldas([[1, 'HITOS', 'cab'], [2, '', 'cab'], [3, '', 'cab'], [4, '', 'cab'], [5, '', 'cab'], [6, '', 'cab'], [7, '', 'cab'], [8, '', 'cab'], [9, '', 'cab']]);
        foreach (PlanProyecto::hitos() as $hito) {
            $estado = $hito['cumplido'] ? 'realizada' : ($hito['estado'] === 'atrasado' ? 'atrasada' : 'no_iniciada');
            $texto = $hito['cumplido'] ? 'Cumplido' : ($hito['estado'] === 'atrasado' ? 'Atrasado '.abs($hito['dias']).' d' : 'Faltan '.$hito['dias'].' d');
            $this->filaCeldas([
                [1, $hito['key'], 'negrita'],
                [2, '◆ '.$hito['label'], 'texto'],
                [6, $texto, 'chip_'.$estado],
                [7, Carbon::parse($hito['fecha'])->format('d-m-Y'), 'texto'],
                [$colGrid + $this->semana(Carbon::parse($hito['fecha'])) - 1, '◆', 'hito_'.$estado],
            ]);
        }

        // --- Trabajos extras (los anotados a mano en /plan) ---------------
        $extras = PlanExtra::orderByDesc('created_at')->get();
        if ($extras->isNotEmpty()) {
            $this->filaVacia();
            $this->filaCeldas([[1, 'TRABAJOS EXTRAS EN PARALELO (fuera de la planificación oficial)', 'cab'], [2, '', 'cab'], [3, '', 'cab'], [4, '', 'cab'], [5, '', 'cab'], [6, '', 'cab'], [7, '', 'cab'], [8, '', 'cab'], [9, '', 'cab']]);
            foreach ($extras as $extra) {
                $estadoExtra = match ($extra->estado) {
                    'finalizada' => 'realizada',
                    'en_curso' => 'en_curso',
                    default => 'no_iniciada',
                };
                $this->filaCeldas([
                    [2, $extra->titulo, 'texto'],
                    [5, ((int) $extra->avance) / 100, 'pct'],
                    [6, self::ETIQUETAS[$estadoExtra], 'chip_'.$estadoExtra],
                    [7, (string) $extra->responsable, 'texto'],
                ]);
            }
        }

        // --- Ensamblar ----------------------------------------------------
        $cols = '<cols>'
            .'<col min="1" max="1" width="6" customWidth="1"/>'
            .'<col min="2" max="2" width="46" customWidth="1"/>'
            .'<col min="3" max="4" width="6" customWidth="1"/>'
            .'<col min="5" max="6" width="11" customWidth="1"/>'
            .'<col min="7" max="9" width="11" customWidth="1"/>'
            ."<col min=\"$colGrid\" max=\"$ultimaCol\" width=\"2.6\" customWidth=\"1\"/>"
            .'</cols>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$cols
            .'<sheetData>'.implode('', $this->filas).'</sheetData>'
            .'</worksheet>';
    }

    /**
     * Hoja 2 «Avance por módulo»: el POR QUÉ de cada porcentaje (el fundamento
     * que el tracker anota junto al número). Es la hoja que Carlos lee cuando un
     * % le llama la atención; en el Excel manual existía y acá viaja sola.
     */
    private function hojaAvance(array $tracker): string
    {
        // Buffer propio: la hoja 1 ya consumió $this->filas/$this->fila.
        $this->filas = [];
        $this->fila = 0;

        $this->filaCeldas([[1, 'Avance por módulo — por qué cada uno está en ese porcentaje', 'titulo']]);
        $this->filaCeldas([[1, 'Fuente: tracker RUTA-MAESTRA §10 del repositorio, al '.$this->hoy->format('d-m-Y').'. La misma que alimenta la página /plan.', 'sub']]);
        $this->filaVacia();
        $this->filaCeldas([
            [1, 'Cód.', 'cab'], [2, 'Módulo / Fase', 'cab'], [3, 'Fase', 'cab'], [4, 'Peso', 'cab'],
            [5, '% Avance', 'cab'], [6, 'Estado', 'cab'], [7, 'Por qué ese % (fundamento del tracker)', 'cab'],
        ]);

        foreach (PlanProyecto::MODULOS as $key => $modulo) {
            $filaTracker = $tracker['filas'][$key] ?? null;
            $pctItem = (int) ($filaTracker['pct'] ?? 0);
            $estado = $this->semaforo($pctItem, $modulo['fin'], $modulo['inicio']);
            $this->filaCeldas([
                [1, $key, 'negrita'],
                [2, $filaTracker['item'] ?? $modulo['label'], 'texto'],
                [3, $modulo['fase'], 'texto'],
                [4, $filaTracker['peso'] ?? null, 'numero'],
                [5, $pctItem / 100, 'pct'],
                [6, self::ETIQUETAS[$estado], 'chip_'.$estado],
                [7, ($filaTracker['fundamento'] ?? '') ?: '—', 'ajustado'],
            ]);
        }

        $cols = '<cols>'
            .'<col min="1" max="1" width="6" customWidth="1"/>'
            .'<col min="2" max="2" width="42" customWidth="1"/>'
            .'<col min="3" max="4" width="6" customWidth="1"/>'
            .'<col min="5" max="6" width="11" customWidth="1"/>'
            .'<col min="7" max="7" width="95" customWidth="1"/>'
            .'</cols>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$cols
            .'<sheetData>'.implode('', $this->filas).'</sheetData>'
            .'</worksheet>';
    }

    // ------------------------------------------------------------------
    // Primitivas de escritura
    // ------------------------------------------------------------------

    /**
     * Agrega una fila. Cada celda es [columna 1-based, valor|null, estilo].
     * Valor null = celda solo con relleno (las de la barra).
     *
     * @param  array<int, array{0: int, 1: mixed, 2: string}>  $celdas
     */
    private function filaCeldas(array $celdas): void
    {
        // Excel exige las celdas de una fila EN ORDEN de columna y sin referencias
        // repetidas — si no, rechaza el archivo entero sin decir por que (el XML
        // sigue siendo bien formado, asi que el candado de parseo no lo ve). El
        // caso real que lo destapo: la etiqueta del mes y la marca HOY caen en la
        // MISMA columna cuando el mes empieza esta semana. Se indexa por columna
        // (la ultima celda gana: HOY pisa al mes, que es lo que se quiere ver) y
        // se ordena.
        $porCol = [];
        foreach ($celdas as $celda) {
            $porCol[$celda[0]] = $celda;
        }
        ksort($porCol);

        $this->fila++;
        $xml = '';
        foreach ($porCol as [$col, $valor, $estilo]) {
            $ref = $this->letra($col).$this->fila;
            $s = self::ESTILOS[$estilo] ?? 0;
            if ($valor === null || $valor === '') {
                $xml .= "<c r=\"$ref\" s=\"$s\"/>";
            } elseif (is_int($valor) || is_float($valor)) {
                $xml .= "<c r=\"$ref\" s=\"$s\"><v>$valor</v></c>";
            } else {
                $texto = htmlspecialchars((string) $valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "<c r=\"$ref\" s=\"$s\" t=\"inlineStr\"><is><t xml:space=\"preserve\">$texto</t></is></c>";
            }
        }
        $this->filas[] = '<row r="'.$this->fila.'">'.$xml.'</row>';
    }

    private function filaVacia(): void
    {
        $this->fila++;
        $this->filas[] = '<row r="'.$this->fila.'"/>';
    }

    /** A, B, ..., Z, AA, AB... para una columna 1-based. */
    private function letra(int $col): string
    {
        $s = '';
        while ($col > 0) {
            $col--;
            $s = chr(65 + ($col % 26)).$s;
            $col = intdiv($col, 26);
        }

        return $s;
    }

    // ------------------------------------------------------------------
    // Partes fijas del OOXML
    // ------------------------------------------------------------------

    /**
     * Indice estilo -> posicion en cellXfs de styles.xml. Los dos arrays se
     * construyen juntos en estilos(); este mapa es el contrato entre ambos.
     */
    private const ESTILOS = [
        'texto' => 0, 'negrita' => 1, 'titulo' => 2, 'sub' => 3, 'banner' => 4,
        'cab' => 5, 'cab_sem' => 6, 'mes' => 7, 'hoy' => 8,
        'numero' => 9, 'pct' => 10,
        'chip_realizada' => 11, 'chip_en_curso' => 12, 'chip_atrasada' => 13, 'chip_no_iniciada' => 14,
        'barra_realizada' => 15, 'barra_en_curso' => 16, 'barra_atrasada' => 17, 'barra_no_iniciada' => 18,
        'barrap_realizada' => 19, 'barrap_en_curso' => 20, 'barrap_atrasada' => 21, 'barrap_no_iniciada' => 22,
        'hito_realizada' => 23, 'hito_atrasada' => 24, 'hito_no_iniciada' => 25,
        'seccion' => 26, 'ajustado' => 27,
    ];

    private function estilos(): string
    {
        // fonts: 0 normal, 1 negrita, 2 titulo, 3 sub gris, 4 blanca negrita,
        // 5 chip en_curso (texto oscuro), 6 banner naranja
        $fonts = '<fonts count="7">'
            .'<font><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FFC2410C"/><name val="Arial"/></font>'
            .'<font><sz val="9"/><color rgb="FF737373"/><name val="Arial"/></font>'
            .'<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            .'<font><b/><sz val="9"/><color rgb="FF3C2800"/><name val="Arial"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF9A3412"/><name val="Arial"/></font>'
            .'</fonts>';

        // fills: 0 y 1 obligatorios (none, gray125); despues los del semaforo.
        $solidos = fn (string $rgb) => "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"FF$rgb\"/></patternFill></fill>";
        $fills = '<fills count="14">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .$solidos('2B2B2B')                                   // 2 cabecera
            .$solidos(self::COLORES['realizada']['solido'])       // 3
            .$solidos(self::COLORES['realizada']['pale'])         // 4
            .$solidos(self::COLORES['en_curso']['solido'])        // 5
            .$solidos(self::COLORES['en_curso']['pale'])          // 6
            .$solidos(self::COLORES['atrasada']['solido'])        // 7
            .$solidos(self::COLORES['atrasada']['pale'])          // 8
            .$solidos(self::COLORES['no_iniciada']['solido'])     // 9
            .$solidos(self::COLORES['no_iniciada']['pale'])       // 10
            .$solidos('FFF7ED')                                   // 11 banner
            .$solidos('EA580C')                                   // 12 HOY
            .$solidos('455A64')                                   // 13 seccion de fase
            .'</fills>';

        // cellXfs en el MISMO orden que el mapa ESTILOS. Dos variantes: normal y
        // con el contenido centrado (chips, barras, cabecera de semanas).
        $xf = fn (int $font, int $fill, int $numFmt = 0, bool $wrap = false) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '')
            .($fill ? ' applyFill="1"' : '')
            .' applyFont="1"'
            .($wrap ? ' applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' : '/>');

        $xfC = fn (int $font, int $fill, int $numFmt = 0) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '').($fill ? ' applyFill="1"' : '')
            .' applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';

        $cellXfs = '<cellXfs count="28">'
            .$xf(0, 0)                    // 0 texto
            .$xf(1, 0)                    // 1 negrita
            .$xf(2, 0)                    // 2 titulo
            .$xf(3, 0)                    // 3 sub
            .$xf(6, 11)                   // 4 banner
            .$xf(4, 2)                    // 5 cab
            .$xfC(4, 2)                   // 6 cab_sem
            .$xfC(4, 2)                   // 7 mes
            .$xfC(4, 12)                  // 8 hoy
            .$xfC(0, 0)                   // 9 numero
            .$xfC(1, 0, 9)                // 10 pct (formato 0%)
            .$xfC(4, 3)                   // 11 chip_realizada
            .$xfC(5, 5)                   // 12 chip_en_curso
            .$xfC(4, 7)                   // 13 chip_atrasada
            .$xfC(4, 9)                   // 14 chip_no_iniciada
            .$xf(0, 3)                    // 15 barra_realizada
            .$xf(0, 5)                    // 16 barra_en_curso
            .$xf(0, 7)                    // 17 barra_atrasada
            .$xf(0, 9)                    // 18 barra_no_iniciada
            .$xf(0, 4)                    // 19 barrap_realizada
            .$xf(0, 6)                    // 20 barrap_en_curso
            .$xf(0, 8)                    // 21 barrap_atrasada
            .$xf(0, 10)                   // 22 barrap_no_iniciada
            .$xfC(4, 3)                   // 23 hito_realizada
            .$xfC(4, 7)                   // 24 hito_atrasada
            .$xfC(4, 9)                   // 25 hito_no_iniciada
            .$xf(4, 13)                   // 26 seccion de fase
            .$xf(0, 0, 0, true)           // 27 ajustado (wrap para el fundamento)
            .'</cellXfs>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$fonts.$fills
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .$cellXfs
            .'</styleSheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Carta Gantt" sheetId="1" r:id="rId1"/><sheet name="Avance por modulo" sheetId="2" r:id="rId3"/></sheets>'
            .'</workbook>';
    }

    private function relsWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            .'</Relationships>';
    }
}
