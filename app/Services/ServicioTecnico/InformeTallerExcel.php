<?php

namespace App\Services\ServicioTecnico;

use App\Models\OrdenServicio;
use App\Services\Excel\EscritorXlsx;
use App\Services\Excel\HojaPlanaXlsx;
use App\Support\FechaNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El informe del taller (DISPENSADORES) como Excel de datos crudos. Pedido del
 * gerente general (13-08-2026): poder bajarse el apartado de Informes «como
 * informacion» para tomar decisiones con ella, sin especificar cuales — asi que
 * el archivo entrega los HECHOS del periodo, no las conclusiones de la pantalla.
 *
 * Dos hojas, porque hay dos granos distintos y meterlos en uno obligaria a
 * repetir o a combinar celdas:
 *
 * - «Ordenes»: una fila por orden ingresada en el periodo.
 * - «Repuestos»: una fila por repuesto usado (una orden puede llevar varios).
 *   Repite en cada fila el contexto de su orden —fecha, cliente, equipo,
 *   condicion— A PROPOSITO: asi la hoja se cruza sola en una tabla dinamica sin
 *   tener que armar un BUSCARV contra la otra.
 *
 * Va COMPLETO, sin el top 15 de la pantalla: el recorte es una decision de
 * legibilidad de la vista, y en un archivo de datos truncar en silencio haria
 * creer que eso es todo lo que hubo.
 */
class InformeTallerExcel
{
    /** Rotulo de `fuente`; no hay accessor en el modelo y «Qr» se lee mal. */
    private const ORIGENES = [
        'qr' => 'QR (cliente)',
        'ruta' => 'Ruta (conductor)',
        'mostrador' => 'Mostrador',
    ];

    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private const COLUMNAS_ORDENES = [
        ['Folio', 14, 'texto'],
        ['Fecha ingreso', 13, 'fecha'],
        ['Estado', 14, 'texto'],
        ['Condición', 12, 'texto'],
        ['Cliente', 30, 'texto'],
        ['RUT', 14, 'texto'],
        ['Teléfono', 14, 'texto'],
        ['Sucursal', 18, 'texto'],
        ['Tipo de equipo', 15, 'texto'],
        ['Código (SKU)', 14, 'texto'],
        ['Equipo del catálogo', 26, 'texto'],
        ['Modelo', 22, 'texto'],
        ['N° de serie', 20, 'texto'],
        ['Categoría', 12, 'texto'],
        ['Causa de la falla', 16, 'texto'],
        ['Falla reportada', 34, 'ajustado'],
        ['Trabajo realizado', 34, 'ajustado'],
        ['Repuestos distintos', 11, 'numero'],
        ['Unidades de repuesto', 11, 'numero'],
        ['Costo repuestos', 14, 'dinero'],
        ['Mano de obra', 13, 'dinero'],
        ['Descuento', 12, 'dinero'],
        ['Total', 13, 'dinero'],
        ['Fecha aviso', 13, 'fecha'],
        ['Fecha retiro', 13, 'fecha'],
        ['Fecha entrega', 13, 'fecha'],
        ['Días en taller', 11, 'numero'],
        ['Origen', 16, 'texto'],
        ['Observaciones', 30, 'ajustado'],
    ];

    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private const COLUMNAS_REPUESTOS = [
        ['Repuesto', 30, 'texto'],
        ['SKU', 16, 'texto'],
        ['Cantidad', 10, 'numero'],
        ['Precio unitario', 14, 'dinero'],
        ['Subtotal', 14, 'dinero'],
        ['Folio', 14, 'texto'],
        ['Fecha ingreso', 13, 'fecha'],
        ['Cliente', 30, 'texto'],
        ['RUT', 14, 'texto'],
        ['Sucursal', 18, 'texto'],
        ['Tipo de equipo', 15, 'texto'],
        ['Modelo', 22, 'texto'],
        ['Condición', 12, 'texto'],
        ['Estado de la orden', 14, 'texto'],
    ];

    /**
     * El .xlsx listo para descargar.
     *
     * @param  Collection<int, OrdenServicio>  $ordenes  las del periodo, con producto/sucursal/repuestos cargados
     */
    public function generar(Collection $ordenes, string $periodoLabel, string $tipoLabel): string
    {
        $lineas = $ordenes->flatMap(fn (OrdenServicio $o) => $o->repuestos);

        $resumen = sprintf(
            'Generado el %s · Período: %s · %s · %d %s · %d líneas de repuesto (%d unidades)',
            Carbon::parse(FechaNegocio::hoy())->format('d-m-Y'),
            $periodoLabel,
            $tipoLabel,
            $ordenes->count(),
            $ordenes->count() === 1 ? 'orden' : 'órdenes',
            $lineas->count(),
            (int) $lineas->sum('cantidad'),
        );

        $hojaOrdenes = new HojaPlanaXlsx('ÓRDENES DE TALLER · DALI', $resumen, self::COLUMNAS_ORDENES);
        foreach ($ordenes as $orden) {
            $hojaOrdenes->fila($this->filaOrden($orden));
        }

        $hojaRepuestos = new HojaPlanaXlsx(
            'REPUESTOS USADOS EN EL TALLER · DALI',
            $resumen.' · Una fila por repuesto usado, con los datos de su orden repetidos para poder cruzarlos',
            self::COLUMNAS_REPUESTOS,
        );
        foreach ($ordenes as $orden) {
            foreach ($orden->repuestos as $repuesto) {
                $hojaRepuestos->fila($this->filaRepuesto($orden, $repuesto));
            }
        }

        return EscritorXlsx::armar([
            'Órdenes' => $hojaOrdenes->xml(),
            'Repuestos' => $hojaRepuestos->xml(),
        ], HojaPlanaXlsx::estilos());
    }

    public static function nombreArchivo(string $periodoLabel): string
    {
        return 'Informe_Taller_DaliGo_'.Str::slug($periodoLabel).'.xlsx';
    }

    /** @return array<int, mixed> */
    private function filaOrden(OrdenServicio $orden): array
    {
        return [
            $orden->folio,
            $orden->fecha_ingreso,
            Str::headline((string) $orden->estado),
            OrdenServicio::etiquetaFacturacion($orden->condicion_efectiva),
            $orden->cliente_nombre,
            $orden->cliente_rut,
            $orden->cliente_telefono,
            $orden->sucursal?->nombre,
            $orden->tipo_equipo_label,
            $orden->producto?->sku,
            $orden->producto?->nombre,
            $orden->modelo,
            $orden->numero_serie,
            $orden->categoria_label,
            $orden->causa_falla_label,
            $orden->falla_reportada,
            $orden->trabajo_realizado,
            $orden->repuestos->count(),
            (int) $orden->repuestos->sum('cantidad'),
            (int) $orden->costo_repuestos,
            (int) $orden->mano_obra,
            (int) $orden->descuento_monto,
            (int) $orden->costo_total,
            $orden->fecha_aviso,
            $orden->fecha_retiro,
            $orden->fecha_entrega,
            $this->diasEnTaller($orden),
            self::ORIGENES[$orden->fuente] ?? Str::headline((string) $orden->fuente),
            $orden->observaciones,
        ];
    }

    /** @return array<int, mixed> */
    private function filaRepuesto(OrdenServicio $orden, mixed $repuesto): array
    {
        return [
            $repuesto->nombre,
            $repuesto->sku,
            (int) $repuesto->cantidad,
            (int) $repuesto->precio_unitario,
            (int) $repuesto->cantidad * (int) $repuesto->precio_unitario,
            $orden->folio,
            $orden->fecha_ingreso,
            $orden->cliente_nombre,
            $orden->cliente_rut,
            $orden->sucursal?->nombre,
            $orden->tipo_equipo_label,
            $orden->modelo,
            OrdenServicio::etiquetaFacturacion($orden->condicion_efectiva),
            Str::headline((string) $orden->estado),
        ];
    }

    /**
     * Dias que el equipo estuvo (o lleva) en el taller. Es la columna que el
     * archivo SI tiene que traer calculada: depende de cual de las tres fechas
     * de salida cerro la orden, y de que una orden abierta se cuenta hasta hoy.
     * Con las fechas sueltas, afuera no hay forma de saber ese criterio.
     */
    private function diasEnTaller(OrdenServicio $orden): ?int
    {
        if (blank($orden->fecha_ingreso)) {
            return null;
        }

        $salida = $orden->fecha_retiro ?? $orden->fecha_entrega ?? Carbon::parse(FechaNegocio::hoy());

        return (int) Carbon::parse($orden->fecha_ingreso)->startOfDay()
            ->diffInDays(Carbon::parse($salida)->startOfDay());
    }
}
