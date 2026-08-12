<?php

namespace App\Services\Logistica;

use App\Models\Vehiculo;
use App\Services\Excel\EscritorXlsx;
use App\Services\Excel\FilasXlsx;
use App\Support\FechaNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La flota como archivo Excel (.xlsx), pedido del dueño (04-08-2026): un botón
 * de descarga para que la planilla que circula salga siempre AL DÍA de la app y
 * no se mantenga a mano.
 *
 * Espeja la forma de «Control vehiculos» (identificación · dimensiones ·
 * documentos) y le agrega las dos columnas que la planilla no puede tener porque
 * requieren calcularse: **Estado documental** y **Qué vence**. Las fechas van
 * como fechas de Excel de verdad (serial + formato), así que se ordenan y se
 * filtran; y la hoja sale con autofiltro y la cabecera congelada, como la usan.
 *
 * Sin dependencias: un .xlsx es un ZIP de XMLs y ZipArchive viene con PHP. El
 * esqueleto del formato lo pone App\Services\Excel\EscritorXlsx y las filas las
 * arma FilasXlsx — los mismos que usa CartaGanttExcel (la deuda que esta
 * cabecera anotaba el 04-08 quedó pagada ese mismo día). Acá vive lo propio de
 * este Excel: sus columnas, su contenido y su tabla de estilos.
 *
 * Colores: acá SÍ se usa el semáforo rojo/ámbar/verde y no la paleta de 4 de la
 * app. Es un archivo que se abre FUERA de DaliGo, donde el verde de "al día" es
 * el idioma que la gente espera de una planilla — mismo criterio que el Excel de
 * la carta Gantt (ver su cabecera).
 */
class FlotaExcel
{
    /** Serial de fechas de Excel: día 0 = 30-12-1899. */
    private const EPOCA_EXCEL = '1899-12-30';

    /** Columnas de la hoja, en orden. La clave es el resolver del valor. */
    private const COLUMNAS = [
        ['Patente', 14],
        ['Vehículo', 22],
        ['Conductor asignado', 24],
        ['Base', 14],
        ['Estado', 12],
        ['Tipo', 16],
        ['Marca', 12],
        ['Modelo', 26],
        ['Año', 7],
        ['Combustible', 13],
        ['VIN / Chasis', 22],
        ['N° motor', 22],
        ['Cilindrada', 10],
        ['PBV (Kg)', 10],
        ['Cap. carga (Kg)', 13],
        ['Presión (PSI)', 11],
        ['Rev. técnica / homologación', 15],
        ['Emisiones', 15],
        ['Permiso de circulación', 15],
        ['SOAP', 15],
        ['Mantención extintor', 15],
        ['Cap. extintor (Kg)', 11],
        ['Estado documental', 17],
        ['Qué vence', 46],
        ['Observaciones', 34],
    ];

    /** Buffer de la única hoja (se estrena en cada generar()). */
    private FilasXlsx $filas;

    private Carbon $hoy;

    public function __construct()
    {
        $this->hoy = Carbon::parse(FechaNegocio::hoy());
    }

    /**
     * El .xlsx listo para descargar.
     *
     * @param  Collection<int, Vehiculo>  $vehiculos  ya filtrados y ordenados por el controlador
     * @param  string  $filtro  descripción del filtro aplicado (vacío = flota completa)
     */
    public function generar(Collection $vehiculos, string $filtro = ''): string
    {
        $this->filas = new FilasXlsx(self::ESTILOS);

        $this->cabecera($vehiculos, $filtro);
        foreach ($vehiculos as $vehiculo) {
            $this->filaVehiculo($vehiculo);
        }

        return EscritorXlsx::armar(['Flota' => $this->hoja($vehiculos->count())], $this->estilos());
    }

    /** Nombre de descarga, fechado con el día de negocio. */
    public static function nombreArchivo(): string
    {
        return 'Vehiculos_DaliGo_'.FechaNegocio::hoy().'.xlsx';
    }

    // ------------------------------------------------------------------
    // Contenido
    // ------------------------------------------------------------------

    /** @param  Collection<int, Vehiculo>  $vehiculos */
    private function cabecera(Collection $vehiculos, string $filtro): void
    {
        $this->filas->celdas([[1, 'FLOTA DE VEHÍCULOS · DALI', 'titulo']]);

        // El resumen se calcula sobre lo que se exporta: si el Excel dice 17 y
        // la lista 10, el archivo miente sobre sí mismo.
        $conteo = ['vencido' => 0, 'por_vencer' => 0, 'al_dia' => 0, 'sin_fecha' => 0];
        foreach ($vehiculos as $vehiculo) {
            $estado = $vehiculo->estado_documental;
            $clave = match ($estado) {
                Vehiculo::DOC_VENCIDO => 'vencido',
                Vehiculo::DOC_POR_VENCER => 'por_vencer',
                Vehiculo::DOC_AL_DIA => 'al_dia',
                Vehiculo::DOC_SIN_REGISTRO => 'sin_fecha',
                default => null,
            };
            if ($clave) {
                $conteo[$clave]++;
            }
        }

        $resumen = sprintf(
            'Generado el %s · %d %s · %d con documento vencido · %d por vencer (30 días) · %d al día · %d con fechas sin cargar',
            $this->hoy->format('d-m-Y'),
            $vehiculos->count(),
            $vehiculos->count() === 1 ? 'vehículo' : 'vehículos',
            $conteo['vencido'],
            $conteo['por_vencer'],
            $conteo['al_dia'],
            $conteo['sin_fecha'],
        );

        $this->filas->celdas([[1, $resumen, 'sub']]);
        $this->filas->celdas([[1, $filtro !== '' ? 'Filtro aplicado: '.$filtro : 'Flota completa (sin filtros)', 'sub']]);

        $cabeceras = [];
        foreach (self::COLUMNAS as $i => [$titulo, $ancho]) {
            $cabeceras[] = [$i + 1, $titulo, 'cab'];
        }
        $this->filas->celdas($cabeceras);
    }

    private function filaVehiculo(Vehiculo $vehiculo): void
    {
        $documentos = collect($vehiculo->documentos())->keyBy('clave');
        $estado = $vehiculo->estado_documental;

        // Qué vence: los documentos críticos con su plazo, en una sola celda.
        // Es la columna que responde «¿y qué le falta a este?» sin abrir la app.
        $criticos = collect($vehiculo->documentosCriticos())
            ->map(fn (array $d) => $d['label'].': '.mb_strtolower(Vehiculo::plazoLabel($d['dias'])))
            ->implode(' · ');

        $celdas = [
            [1, $vehiculo->ppu, 'negrita'],
            [2, $vehiculo->alias, 'texto'],
            [3, $vehiculo->conductor_nombre, 'texto'],
            [4, $vehiculo->base, 'texto'],
            [5, $vehiculo->estado_label, 'texto'],
            [6, $vehiculo->tipo_label, 'texto'],
            [7, $vehiculo->marca, 'texto'],
            [8, $vehiculo->modelo, 'texto'],
            [9, $vehiculo->anio, 'numero'],
            [10, $vehiculo->combustible_label, 'texto'],
            [11, $vehiculo->vin, 'texto'],
            [12, $vehiculo->numero_motor, 'texto'],
            [13, $vehiculo->cilindrada, 'numero'],
            [14, $vehiculo->pbv_kg, 'numero'],
            [15, $vehiculo->capacidad_carga_kg, 'numero'],
            [16, $vehiculo->presion_psi, 'numero'],
        ];

        // Las 5 fechas de documentos, cada una con el color de SU estado — igual
        // que las celdas pintadas a mano en la planilla, pero calculado.
        //
        // SOLO LAS 5 DE LA LEY, a propósito. Los tipos creados desde la app
        // (`Vehiculo::catalogoDocumentos`) no tienen columna propia: el mapa de
        // columnas de esta planilla es fijo (17-21 las fechas, 22-25 el resto) y
        // hacerlo variable movería el encabezado, los anchos y las fórmulas de quien
        // ya usa el archivo. Igual NO se pierden: la columna «Estado» y la de
        // documentos críticos salen de `documentos()`, que sí los incluye, así que un
        // documento creado y vencido pinta la fila y se nombra. Si algún día hacen
        // falta como columna, el cambio es rehacer el mapa entero, no agregar un
        // `foreach`.
        $columnaDoc = 17;
        foreach (Vehiculo::DOCUMENTOS as $clave => $label) {
            $doc = $documentos->get($clave);
            $celdas[] = [$columnaDoc, ...$this->celdaFecha($doc)];
            $columnaDoc++;
        }

        $celdas[] = [22, $vehiculo->extintor_capacidad_kg ? (float) $vehiculo->extintor_capacidad_kg : null, 'numero'];
        $celdas[] = [23, Vehiculo::estadoDocumentalLabel($estado), 'chip_'.$estado];
        $celdas[] = [24, $criticos, 'ajustado'];
        $celdas[] = [25, $vehiculo->observaciones, 'ajustado'];

        $this->filas->celdas($celdas);
    }

    /**
     * Una celda de fecha: el valor como serial de Excel (para que ordene y
     * filtre como fecha) y el estilo según su estado.
     *
     * @param  array<string, mixed>|null  $doc
     * @return array{0: int|string|null, 1: string}
     */
    private function celdaFecha(?array $doc): array
    {
        if ($doc === null) {
            return [null, 'fecha'];
        }

        if ($doc['estado'] === Vehiculo::DOC_NO_APLICA) {
            // Texto y no fecha vacía: «no aplica» es información (el
            // semirremolque no rinde emisiones), distinta de «falta el dato».
            return ['No aplica', 'fecha_no_aplica'];
        }

        if ($doc['vence'] === null) {
            return [null, 'fecha'];
        }

        $estilo = match ($doc['estado']) {
            Vehiculo::DOC_VENCIDO => 'fecha_vencida',
            Vehiculo::DOC_POR_VENCER => 'fecha_por_vencer',
            default => 'fecha',
        };

        return [$this->serial($doc['vence']), $estilo];
    }

    /** Fecha → serial de Excel (días desde el 30-12-1899). */
    private function serial(Carbon $fecha): int
    {
        return (int) Carbon::parse(self::EPOCA_EXCEL)->diffInDays(Carbon::parse($fecha->toDateString()));
    }

    // ------------------------------------------------------------------
    // La hoja y sus estilos
    // ------------------------------------------------------------------

    private function hoja(int $filasDatos): string
    {
        $cols = '<cols>';
        foreach (self::COLUMNAS as $i => [$titulo, $ancho]) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$ancho.'" customWidth="1"/>';
        }
        $cols .= '</cols>';

        // Autofiltro sobre la fila de encabezados (4) + los datos: la planilla
        // original se usa con los desplegables de filtro puestos.
        $ultima = 4 + max(1, $filasDatos);
        $ultimaLetra = FilasXlsx::letra(count(self::COLUMNAS));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // Cabecera congelada: con 25 columnas y decenas de filas, sin esto se
            // pierde de vista qué columna se está mirando.
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="14"/>'
            .$cols
            .'<sheetData>'.$this->filas->xml().'</sheetData>'
            .'<autoFilter ref="A4:'.$ultimaLetra.$ultima.'"/>'
            .'</worksheet>';
    }

    /**
     * Índice estilo → posición en cellXfs. Contrato con estilos(), y lo que
     * recibe FilasXlsx para resolver el `s=` de cada celda.
     */
    private const ESTILOS = [
        'texto' => 0, 'negrita' => 1, 'titulo' => 2, 'sub' => 3, 'cab' => 4,
        'numero' => 5, 'ajustado' => 6,
        'fecha' => 7, 'fecha_vencida' => 8, 'fecha_por_vencer' => 9, 'fecha_no_aplica' => 10,
        'chip_vencido' => 11, 'chip_por_vencer' => 12, 'chip_al_dia' => 13,
        'chip_sin_registro' => 14, 'chip_no_aplica' => 15,
    ];

    private function estilos(): string
    {
        // 164 = formato de fecha propio; los ids <164 están reservados por Excel.
        $numFmts = '<numFmts count="1"><numFmt numFmtId="164" formatCode="dd\-mm\-yyyy"/></numFmts>';

        $fonts = '<fonts count="7">'
            .'<font><sz val="10"/><name val="Arial"/></font>'                                             // 0 normal
            .'<font><b/><sz val="10"/><name val="Arial"/></font>'                                         // 1 negrita
            .'<font><b/><sz val="16"/><color rgb="FFC2410C"/><name val="Arial"/></font>'                  // 2 titulo
            .'<font><sz val="9"/><color rgb="FF737373"/><name val="Arial"/></font>'                       // 3 sub gris
            .'<font><b/><sz val="9"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'                   // 4 blanca negrita
            .'<font><b/><sz val="10"/><color rgb="FFB71C1C"/><name val="Arial"/></font>'                  // 5 rojo negrita
            .'<font><b/><sz val="9"/><color rgb="FF3C2800"/><name val="Arial"/></font>'                   // 6 oscura (sobre ámbar)
            .'</fonts>';

        $solido = fn (string $rgb) => "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"FF$rgb\"/></patternFill></fill>";
        $fills = '<fills count="9">'
            .'<fill><patternFill patternType="none"/></fill>'   // 0 obligatorio
            .'<fill><patternFill patternType="gray125"/></fill>' // 1 obligatorio
            .$solido('2B2B2B')   // 2 cabecera
            .$solido('FFCDD2')   // 3 rojo pálido (fecha vencida)
            .$solido('FFF3CD')   // 4 ámbar pálido (fecha por vencer)
            .$solido('C62828')   // 5 rojo sólido (chip vencido)
            .$solido('F9A825')   // 6 ámbar sólido (chip por vencer)
            .$solido('2E7D32')   // 7 verde sólido (chip al día)
            .$solido('9E9E9E')   // 8 gris sólido (chip sin fecha / no aplica)
            .'</fills>';

        $xf = fn (int $font, int $fill, int $numFmt = 0, bool $wrap = false) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '')
            .($fill ? ' applyFill="1"' : '')
            .' applyFont="1"'
            .($wrap ? ' applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>' : '/>');

        $xfC = fn (int $font, int $fill, int $numFmt = 0) => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="0" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '').($fill ? ' applyFill="1"' : '')
            .' applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';

        $cellXfs = '<cellXfs count="16">'
            .$xf(0, 0)                 // 0 texto
            .$xf(1, 0)                 // 1 negrita
            .$xf(2, 0)                 // 2 titulo
            .$xf(3, 0)                 // 3 sub
            .$xf(4, 2)                 // 4 cab
            .$xfC(0, 0)                // 5 numero
            .$xf(0, 0, 0, true)        // 6 ajustado
            .$xfC(0, 0, 164)           // 7 fecha
            .$xfC(5, 3, 164)           // 8 fecha vencida
            .$xfC(6, 4, 164)           // 9 fecha por vencer
            .$xfC(3, 0)                // 10 fecha "No aplica" (texto gris)
            .$xfC(4, 5)                // 11 chip vencido
            .$xfC(6, 6)                // 12 chip por vencer
            .$xfC(4, 7)                // 13 chip al día
            .$xfC(4, 8)                // 14 chip sin fecha
            .$xfC(4, 8)                // 15 chip no aplica
            .'</cellXfs>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$numFmts.$fonts.$fills
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .$cellXfs
            .'</styleSheet>';
    }
}
