<?php

namespace App\Services\Produccion;

use App\Models\Bodega;
use App\Models\ProduccionReporte;
use App\Models\Stock;
use App\Models\User;

/**
 * Semaforo de preformas del soplador (P-M11-22): ¿el stock del ESPEJO Bsale
 * de las bodegas de SU sucursal alcanza para la meta del turno? Solo LECTURA
 * del espejo — jamas escribe, jamas muestra costos (regla Katana §1.3).
 *
 * SILENCIO antes que rojo falso: si falta cualquier pieza del dato (la
 * asignacion no tiene preforma, la preforma no esta enlazada a Bsale, el
 * soplador no tiene sucursal, o la sucursal no tiene bodegas en operacion),
 * el semaforo NO se muestra — un hueco de CONFIGURACION no es un quiebre de
 * stock, y gritarle "sin preformas" a un operario que no puede hacer nada
 * seria un rojo falso (dictado v21).
 *
 * Limitacion conocida y aceptada: se cuentan TODAS las bodegas enOperacion()
 * de la sucursal (el contrato operativo de M04-F1), sin filtrar por proposito
 * — una bodega `transito` en operacion cuenta preformas que aun van en el
 * barco. Es dato del espejo; refinar por proposito seria inventar una regla
 * sin decision del dueño.
 */
class SemaforoPreformas
{
    // El stock visible alcanza la meta completa.
    public const ALCANZA = 'alcanza';

    // Hay stock visible, pero menos que la meta.
    public const PARCIAL = 'parcial';

    // El espejo no muestra stock disponible en la sucursal.
    public const SIN_STOCK = 'sin_stock';

    /**
     * Estado del semaforo para el reporte del soplador, o null (silencio).
     *
     * @return array{estado: string, variante: string, label: string, stock: string}|null
     */
    public function estadoPara(?ProduccionReporte $reporte, User $soplador): ?array
    {
        $preforma = $reporte?->asignacion?->preforma;

        // Sin preforma asignada, sin enlace a Bsale (= sin espejo) o sin
        // sucursal del soplador: no hay dato que leer.
        if ($preforma === null || $preforma->bsale_variant_id === null || $soplador->sucursal_id === null) {
            return null;
        }

        $bodegas = Bodega::enOperacion()
            ->deSucursal($soplador->sucursal_id)
            ->pluck('id');

        // Sucursal sin bodegas operativas = hueco de configuracion, no un
        // quiebre: sin este gate, la suma sobre un whereIn([]) daria 0 y el
        // semaforo gritaria "sin stock" en falso (p. ej. bodega en wizard de
        // baja, que sale del scope TEMPORALMENTE).
        if ($bodegas->isEmpty()) {
            return null;
        }

        // stock_disponible y no stock_real: lo reservado por documentos no
        // esta disponible para soplar (mismo criterio que el filtro con_stock
        // de la ficha de bodega).
        $stock = (float) Stock::whereIn('bodega_id', $bodegas)
            ->where('producto_id', $preforma->id)
            ->sum('stock_disponible');

        $meta = (int) $reporte->asignadas;

        $estado = match (true) {
            $stock <= 0 => self::SIN_STOCK,
            $stock >= $meta => self::ALCANZA,
            default => self::PARCIAL,
        };

        $unidades = Stock::formatear((string) $stock);

        return [
            'estado' => $estado,
            'variante' => self::variante($estado),
            'label' => match ($estado) {
                self::ALCANZA => "Preformas OK ({$unidades} visibles)",
                self::PARCIAL => "Preformas parciales: {$unidades} visibles",
                self::SIN_STOCK => 'Sin preformas visibles en tu sucursal',
            },
            'stock' => $unidades,
        ];
    }

    /**
     * Variante de <x-badge> del semaforo. Paleta ESTRICTA de 4 (CLAUDE.md):
     * el verde no existe en esta app — alcanza = neutro (reposo), parcial =
     * naranjo de marca (atencion), sin stock = rojo (negativo declarado).
     * Mismo criterio que Vehiculo::variante() y CorteSic::variante(), con su
     * candado de paleta.
     */
    public static function variante(string $estado): string
    {
        return match ($estado) {
            self::SIN_STOCK => 'danger',
            self::PARCIAL => 'brand',
            default => 'neutral',
        };
    }
}
