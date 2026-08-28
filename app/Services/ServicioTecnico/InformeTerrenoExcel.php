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
 * hoja: el repuesto de terreno trae codigo y cantidad pero NO precio, porque al
 * tecnico industrial le pagan por arreglar e instalar y no maneja precios (dueno
 * 14-08-2026) — la cotizacion formal la hacen el vendedor y el jefe de ventas.
 * Asi que esta hoja cuenta el USO y no lo valoriza. Sin esa nota, un lector
 * compararia las dos planillas y concluiria que en terreno no se gasta en
 * repuestos, cuando lo que pasa es que el precio se pone en otra parte.
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
        // Vacio cuando el tecnico lo escribio a mano (no estaba en el catalogo):
        // null significa «no vino del catalogo», no «falta el dato».
        ['Código', 16, 'texto'],
        // USADO vs POR COTIZAR. En una visita tecnica el tecnico anota lo que se va
        // a NECESITAR (con eso ventas cotiza la segunda visita) y no instala nada,
        // asi que sumar las dos cosas cuenta el mismo repuesto dos veces. Se
        // exportan ambas ROTULADAS en vez de dejar una afuera: es una tabla de
        // datos, y el que la filtra decide — pero tiene que poder distinguirlas.
        ['Registro', 14, 'texto'],
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
            $resumen.' · SIN precio: el técnico industrial no maneja precios (la cotización la hacen el vendedor y el jefe de ventas), así que esta hoja cuenta el uso y no lo valoriza. Filtra la columna «Registro»: «Usado» salió de bodega; «Por cotizar» es lo que el técnico estimó en la visita de revisión para el trabajo que sigue — sumar las dos cuenta el mismo repuesto dos veces',
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
            $repuesto->sku,
            $trabajo->repuestosSonPronostico() ? 'Por cotizar' : 'Usado',
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
