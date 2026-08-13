<?php

namespace App\Services\ServicioTecnico;

use App\Models\AgendaTrabajo;
use App\Services\Excel\EscritorXlsx;
use App\Services\Excel\HojaPlanaXlsx;
use App\Support\FechaNegocio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El informe del servicio INDUSTRIAL (agenda de terreno) como Excel de datos
 * crudos. Hermano de InformeTallerExcel y del mismo pedido del gerente general
 * (13-08-2026): la tabla plana del periodo para cruzarla afuera.
 *
 * Dos hojas por el mismo motivo que el del taller: «Trabajos» (una fila por
 * trabajo agendado o realizado) y «Repuestos» (una fila por repuesto usado, con
 * el contexto de su trabajo repetido para poder cruzarlo solo).
 *
 * Diferencia HONESTA con el Excel del taller, y por eso queda dicha en la propia
 * hoja: los repuestos de terreno solo guardan nombre y cantidad. No hay SKU ni
 * precio porque `agenda_trabajo_repuestos` no los tiene —a diferencia de
 * `orden_servicio_repuestos`, que si—, asi que la hoja no puede valorizarlos. Sin
 * esa nota, un lector compararia las dos planillas y concluiria que en terreno no
 * se gasta en repuestos.
 */
class InformeTerrenoExcel
{
    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private const COLUMNAS_TRABAJOS = [
        ['Fecha', 13, 'fecha'],
        ['Hora', 10, 'texto'],
        ['Tipo', 16, 'texto'],
        ['Estado', 12, 'texto'],
        ['Servicio del catálogo', 28, 'texto'],
        ['Cliente', 30, 'texto'],
        ['RUT', 14, 'texto'],
        ['Teléfono', 14, 'texto'],
        ['Email', 24, 'texto'],
        ['Dirección', 30, 'texto'],
        ['Ciudad', 18, 'texto'],
        ['Técnico', 20, 'texto'],
        ['Confirmó el cliente', 14, 'texto'],
        ['Repuestos distintos', 11, 'numero'],
        ['Unidades de repuesto', 11, 'numero'],
        ['Descripción', 34, 'ajustado'],
        ['Notas del técnico', 34, 'ajustado'],
        ['Agendado por', 18, 'texto'],
    ];

    /** @var array<int, array{0: string, 1: int, 2: string}> */
    private const COLUMNAS_REPUESTOS = [
        ['Repuesto', 30, 'texto'],
        ['Cantidad', 10, 'numero'],
        ['Fecha', 13, 'fecha'],
        ['Tipo', 16, 'texto'],
        ['Estado', 12, 'texto'],
        ['Servicio del catálogo', 28, 'texto'],
        ['Cliente', 30, 'texto'],
        ['RUT', 14, 'texto'],
        ['Ciudad', 18, 'texto'],
        ['Técnico', 20, 'texto'],
    ];

    /**
     * @param  Collection<int, AgendaTrabajo>  $trabajos  los del periodo, con servicio/tecnico/repuestos cargados
     */
    public function generar(Collection $trabajos, string $periodoLabel): string
    {
        $lineas = $trabajos->flatMap(fn (AgendaTrabajo $t) => $t->repuestos);

        $resumen = sprintf(
            'Generado el %s · Período: %s · %d %s (agendados y realizados) · %d líneas de repuesto (%d unidades)',
            Carbon::parse(FechaNegocio::hoy())->format('d-m-Y'),
            $periodoLabel,
            $trabajos->count(),
            $trabajos->count() === 1 ? 'trabajo' : 'trabajos',
            $lineas->count(),
            (int) $lineas->sum('cantidad'),
        );

        $hojaTrabajos = new HojaPlanaXlsx('TRABAJOS EN TERRENO · DALI', $resumen, self::COLUMNAS_TRABAJOS);
        foreach ($trabajos as $trabajo) {
            $hojaTrabajos->fila($this->filaTrabajo($trabajo));
        }

        $hojaRepuestos = new HojaPlanaXlsx(
            'REPUESTOS USADOS EN TERRENO · DALI',
            $resumen.' · En terreno el repuesto se registra solo por nombre y cantidad: no hay SKU ni precio para valorizarlo',
            self::COLUMNAS_REPUESTOS,
        );
        foreach ($trabajos as $trabajo) {
            foreach ($trabajo->repuestos as $repuesto) {
                $hojaRepuestos->fila($this->filaRepuesto($trabajo, $repuesto));
            }
        }

        return EscritorXlsx::armar([
            'Trabajos' => $hojaTrabajos->xml(),
            'Repuestos' => $hojaRepuestos->xml(),
        ], HojaPlanaXlsx::estilos());
    }

    public static function nombreArchivo(string $periodoLabel): string
    {
        return 'Informe_Terreno_DaliGo_'.Str::slug($periodoLabel).'.xlsx';
    }

    /** @return array<int, mixed> */
    private function filaTrabajo(AgendaTrabajo $trabajo): array
    {
        return [
            $trabajo->fecha,
            $trabajo->hora_corta,
            $trabajo->tipo_label,
            Str::headline((string) $trabajo->estado),
            $trabajo->servicio?->nombre,
            $trabajo->cliente_nombre,
            $trabajo->cliente_rut,
            $trabajo->cliente_telefono,
            $trabajo->cliente_email,
            $trabajo->direccion,
            $trabajo->ciudad,
            $trabajo->tecnico?->name,
            $trabajo->cliente_confirmacion_label,
            $trabajo->repuestos->count(),
            (int) $trabajo->repuestos->sum('cantidad'),
            $trabajo->descripcion,
            $trabajo->notas_tecnico,
            $trabajo->creado_por,
        ];
    }

    /** @return array<int, mixed> */
    private function filaRepuesto(AgendaTrabajo $trabajo, mixed $repuesto): array
    {
        return [
            $repuesto->nombre,
            (int) $repuesto->cantidad,
            $trabajo->fecha,
            $trabajo->tipo_label,
            Str::headline((string) $trabajo->estado),
            $trabajo->servicio?->nombre,
            $trabajo->cliente_nombre,
            $trabajo->cliente_rut,
            $trabajo->ciudad,
            $trabajo->tecnico?->name,
        ];
    }
}
