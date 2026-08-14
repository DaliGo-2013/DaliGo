<?php

namespace App\Services\ServicioTecnico;

use App\Models\Instalacion;
use App\Services\Excel\EscritorXlsx;
use App\Services\Excel\HojaPlanaXlsx;
use App\Support\FechaNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El registro de instalaciones del tecnico industrial como Excel. Pedido del
 * tecnico (13-08-2026): poder compartir el detalle MES POR MES de los trabajos
 * que hizo, porque con eso le pagan las horas extras.
 *
 * Que se lo paguen es lo que define la forma del archivo: no alcanza con listar
 * los trabajos, hay que poder sumar los DIAS por mes sin rehacer la cuenta a
 * mano. De ahi las dos hojas:
 *
 * - «Instalaciones»: una fila por instalacion, con `Año` y `Mes` como columnas
 *   propias (no solo dentro de la fecha) para que agrupe y filtre por periodo
 *   directo, y con `Dias` al lado de los datos del trabajo.
 * - «Resumen por mes»: un mes por fila con sus instalaciones y sus dias
 *   TOTALES. Es la hoja que se mira en la conversacion del pago: la pregunta
 *   «¿cuantos dias trabajo en julio?» se contesta leyendo una celda, no armando
 *   una tabla dinamica. El total del periodo va en la ultima fila.
 *
 * Exporta lo MISMO que muestra la pantalla (mismos filtros: busqueda, categoria
 * y periodo) y COMPLETO — el listado pagina de 25 en 25, pero un archivo de
 * respaldo de pago que se corta en la pagina 1 seria una liquidacion incompleta.
 */
class InstalacionesExcel
{
    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private const COLUMNAS_DETALLE = [
        ['Fecha', 13, 'fecha'],
        ['Año', 8, 'numero'],
        ['Mes', 7, 'numero'],
        ['Cliente', 32, 'texto'],
        ['RUT', 14, 'texto'],
        ['Comuna / Región', 24, 'texto'],
        ['Categoría', 12, 'texto'],
        ['Equipo instalado', 34, 'texto'],
        ['Instalación', 11, 'texto'],
        ['Puesta en marcha', 15, 'texto'],
        ['Días', 8, 'numero'],
        ['Vendedor', 20, 'texto'],
        ['N° factura', 13, 'texto'],
        ['Fecha factura', 13, 'fecha'],
        ['Forma de pago', 17, 'texto'],
        ['Fecha de pago', 13, 'fecha'],
        ['Registrado por', 18, 'texto'],
    ];

    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private const COLUMNAS_RESUMEN = [
        ['Período', 16, 'texto'],
        ['Año', 8, 'numero'],
        ['Mes', 7, 'numero'],
        ['Instalaciones', 13, 'numero'],
        ['Días trabajados', 14, 'numero'],
        ['Lavadoras', 11, 'numero'],
        ['Llenadoras', 11, 'numero'],
        ['Plantas', 10, 'numero'],
    ];

    /**
     * @param  Collection<int, Instalacion>  $instalaciones  ya filtradas, ordenadas por fecha
     */
    public function generar(Collection $instalaciones, string $periodoLabel): string
    {
        $dias = (int) $instalaciones->sum('dias');

        $resumen = sprintf(
            'Generado el %s · %s · %d %s · %d %s trabajados',
            Carbon::parse(FechaNegocio::hoy())->format('d-m-Y'),
            $periodoLabel,
            $instalaciones->count(),
            $instalaciones->count() === 1 ? 'instalación' : 'instalaciones',
            $dias,
            $dias === 1 ? 'día' : 'días',
        );

        $detalle = new HojaPlanaXlsx('INSTALACIONES Y PUESTAS EN MARCHA · DALI', $resumen, self::COLUMNAS_DETALLE);
        foreach ($instalaciones as $instalacion) {
            $detalle->fila($this->filaDetalle($instalacion));
        }

        return EscritorXlsx::armar([
            'Instalaciones' => $detalle->xml(),
            'Resumen por mes' => $this->hojaResumen($instalaciones, $resumen),
        ], HojaPlanaXlsx::estilos());
    }

    public static function nombreArchivo(string $periodoLabel): string
    {
        return 'Instalaciones_DaliGo_'.Str::slug($periodoLabel).'.xlsx';
    }

    /** @return array<int, mixed> */
    private function filaDetalle(Instalacion $i): array
    {
        return [
            $i->fecha,
            $i->fecha?->year,
            $i->fecha?->month,
            $i->cliente_nombre,
            $i->cliente_rut,
            $i->comuna_region,
            Instalacion::CATEGORIA_ETIQUETAS[$i->categoria] ?? $i->categoria,
            $i->producto,
            // «Sí»/«No» y no TRUE/FALSE: el archivo se lee, no se programa. Espeja
            // el SI/NO que la planilla original ya usaba en estas dos columnas.
            $i->instalacion ? 'Sí' : 'No',
            $i->puesta_en_marcha ? 'Sí' : 'No',
            $i->dias !== null ? (int) $i->dias : null,
            $i->vendedor,
            $i->n_factura,
            $i->fecha_factura,
            $i->forma_pago ? (Instalacion::FORMA_PAGO_ETIQUETAS[$i->forma_pago] ?? $i->forma_pago) : null,
            $i->fecha_pago,
            $i->creado_por,
        ];
    }

    /**
     * La hoja del pago: un mes por fila, en orden cronologico, y el total del
     * periodo al pie.
     *
     * @param  Collection<int, Instalacion>  $instalaciones
     */
    private function hojaResumen(Collection $instalaciones, string $resumen): string
    {
        $hoja = new HojaPlanaXlsx('DÍAS TRABAJADOS POR MES · DALI', $resumen, self::COLUMNAS_RESUMEN);

        // Agrupa por 'YYYY-MM' (ordenable como texto) y no por el nombre del mes,
        // que se ordena alfabeticamente y mezclaria años.
        $porMes = $instalaciones
            ->filter(fn (Instalacion $i) => $i->fecha !== null)
            ->groupBy(fn (Instalacion $i) => $i->fecha->format('Y-m'))
            ->sortKeys();

        foreach ($porMes as $clave => $delMes) {
            /** @var Instalacion $primera */
            $primera = $delMes->first();
            $hoja->fila([
                ucfirst($primera->fecha->locale('es')->translatedFormat('F Y')),
                $primera->fecha->year,
                $primera->fecha->month,
                $delMes->count(),
                (int) $delMes->sum('dias'),
                $delMes->where('categoria', 'lavadora')->count(),
                $delMes->where('categoria', 'llenadora')->count(),
                $delMes->where('categoria', 'planta')->count(),
            ]);
        }

        // Total del periodo. Va como fila y no como nota al pie para que entre en
        // el autofiltro igual que el resto y no se pierda al ordenar.
        if ($porMes->isNotEmpty()) {
            $hoja->fila([
                'TOTAL DEL PERÍODO',
                null,
                null,
                $instalaciones->count(),
                (int) $instalaciones->sum('dias'),
                $instalaciones->where('categoria', 'lavadora')->count(),
                $instalaciones->where('categoria', 'llenadora')->count(),
                $instalaciones->where('categoria', 'planta')->count(),
            ]);
        }

        return $hoja->xml();
    }
}
