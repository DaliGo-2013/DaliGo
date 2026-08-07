<?php

namespace App\Services\Inventario;

use App\Models\Bodega;
use App\Models\BodegaTraslado;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * La baja de bodegas (M04-F2, P-M04-20): «eliminar» jamás pierde stock ni
 * borra la fila — o la bodega está vacía y muere al tiro, o se crea una
 * ORDEN DE TRASLADO (a dónde va lo que contiene) y queda `pendiente_traslado`
 * hasta que un sync confirme stock 0. El traslado físico hoy se ejecuta en
 * Bsale (D-005): la orden es el puente; NUNCA se escribe `stocks`/`bodegas`
 * espejadas — solo la capa local.
 *
 * Los mensajes de InvalidArgumentException son de cara al usuario (el
 * controller los vuelve flash, patrón M13).
 */
class BajaDeBodegas
{
    /** Umbral de flotantes del espejo (decimal 14,4). */
    private const EPSILON = 0.0001;

    /**
     * Pide la baja. Devuelve la Bodega (baja inmediata, estaba vacía) o la
     * BodegaTraslado creada (tenía stock): el llamador bifurca el flash según
     * lo que el dominio decidió, nunca lo presume (patrón M14).
     */
    public function solicitar(Bodega $bodega, User $usuario, ?int $destinoId): Bodega|BodegaTraslado
    {
        return DB::transaction(function () use ($bodega, $usuario, $destinoId) {
            $fresca = Bodega::whereKey($bodega->id)->lockForUpdate()->firstOrFail();

            if ($fresca->enBaja()) {
                throw new InvalidArgumentException("La bodega {$fresca->nombre} ya está en proceso de baja.");
            }

            // La FOTO: lo que hay ahora mismo según el espejo. La reserva vive
            // dentro del real, así que `stock_real ≠ 0` es el criterio de vacío.
            $conStock = $fresca->stocks()
                ->where('stocks.stock_real', '!=', 0)
                ->join('productos', 'productos.id', '=', 'stocks.producto_id')
                ->orderBy('productos.nombre')
                ->get(['stocks.*', 'productos.nombre AS producto_nombre', 'productos.sku AS producto_sku']);

            if ($conStock->isEmpty()) {
                // Vacía: baja inmediata, sin orden. en_operacion se apaga
                // porque la baja es FINAL (PLAN §F2 textual).
                $fresca->update([
                    'estado_baja' => Bodega::BAJA_DADA_DE_BAJA,
                    'en_operacion' => false,
                ]);

                return $fresca;
            }

            // Con stock: la regla dura vive ACÁ (espejo de validación en el
            // controller solo para el mensaje amable) — sin destino no hay baja.
            $destino = $destinoId !== null
                ? Bodega::enOperacion()->whereKey($destinoId)->first()
                : null;

            if ($destino === null || $destino->id === $fresca->id) {
                throw new InvalidArgumentException(
                    "La bodega {$fresca->nombre} tiene existencias: elige una bodega de destino en operación (distinta de ella misma) para crear la orden de traslado."
                );
            }

            $orden = BodegaTraslado::create([
                'bodega_id' => $fresca->id,
                'bodega_destino_id' => $destino->id,
                'estado' => BodegaTraslado::PENDIENTE,
                'solicitante_id' => $usuario->id,
                'solicitante_nombre' => $usuario->name,
            ]);

            foreach ($conStock as $stock) {
                $orden->items()->create([
                    'producto_id' => $stock->producto_id,
                    'nombre' => $stock->producto_nombre,
                    'sku' => $stock->producto_sku,
                    'cantidad' => $stock->stock_real,
                ]);
            }

            // Fuera de los selectores operativos vía el scope (estado_baja);
            // en_operacion NO se toca: si la orden se anula, vuelve sola.
            $fresca->update(['estado_baja' => Bodega::BAJA_PENDIENTE_TRASLADO]);

            return $orden;
        });
    }

    /**
     * La llama StockSync DESPUÉS de refrescar el espejo: cierra las bajas
     * cuyo origen quedó en 0 (la orden se completa SOLA + aviso al
     * solicitante) y detecta stock NUEVO en bodegas en baja (aviso una sola
     * vez por orden; la bodega NO revive). Idempotente por estado: la orden
     * completada no se vuelve a mirar.
     *
     * @return array{completadas: int, avisos_stock: int}
     */
    public function conciliarConEspejo(): array
    {
        $resultado = ['completadas' => 0, 'avisos_stock' => 0];
        $porAvisar = [];

        foreach (BodegaTraslado::pendientes()->with('bodega')->get() as $orden) {
            $aviso = DB::transaction(function () use ($orden) {
                $fresca = BodegaTraslado::whereKey($orden->id)->lockForUpdate()->firstOrFail();

                // Recheck bajo lock: otra corrida pudo completarla (cinturón
                // además del withoutOverlapping del cron).
                if (! $fresca->esPendiente()) {
                    return null;
                }

                $vacia = ! $fresca->bodega->stocks()->where('stock_real', '!=', 0)->exists();

                if ($vacia) {
                    $fresca->update(['estado' => BodegaTraslado::COMPLETADO, 'completado_at' => now()]);
                    $fresca->bodega->update([
                        'estado_baja' => Bodega::BAJA_DADA_DE_BAJA,
                        'en_operacion' => false,
                    ]);

                    return ['evento' => 'bodega.baja_completada', 'orden' => $fresca];
                }

                // Drenar BAJA el stock; SUBIR sobre la foto (o aparecer un
                // producto que no estaba) = llegó stock nuevo. Una vez por orden.
                if ($fresca->aviso_stock_nuevo_at === null && $this->llegoStockNuevo($fresca)) {
                    $fresca->update(['aviso_stock_nuevo_at' => now()]);

                    return ['evento' => 'bodega.stock_en_baja', 'orden' => $fresca];
                }

                return null;
            });

            if ($aviso !== null) {
                $porAvisar[] = $aviso;
                $aviso['evento'] === 'bodega.baja_completada'
                    ? $resultado['completadas']++
                    : $resultado['avisos_stock']++;
            }
        }

        // Los avisos van DESPUÉS de las transacciones (M13: primero el estado,
        // después el correo — un SMTP caído no puede deshacer la baja).
        foreach ($porAvisar as $aviso) {
            $this->avisar($aviso['evento'], $aviso['orden']);
        }

        return $resultado;
    }

    /** Anula una orden pendiente: la bodega sale de la baja y vuelve a operar. */
    public function anular(BodegaTraslado $orden, User $usuario): BodegaTraslado
    {
        return DB::transaction(function () use ($orden) {
            $fresca = BodegaTraslado::whereKey($orden->id)->lockForUpdate()->firstOrFail();

            if (! $fresca->esPendiente()) {
                throw new InvalidArgumentException('Esta orden ya no está pendiente: no se puede anular.');
            }

            $fresca->update(['estado' => BodegaTraslado::ANULADO, 'anulado_at' => now()]);
            $fresca->bodega->update(['estado_baja' => null]);

            return $fresca;
        });
    }

    /** ¿Hay stock POR ENCIMA de la foto de la orden (o de un producto que no estaba)? */
    private function llegoStockNuevo(BodegaTraslado $orden): bool
    {
        $foto = $orden->items()->pluck('cantidad', 'producto_id')
            ->map(fn ($cantidad) => (float) $cantidad);

        return $orden->bodega->stocks()
            ->where('stock_real', '>', 0)
            ->get(['producto_id', 'stock_real'])
            ->contains(fn ($stock) => (float) $stock->stock_real > ($foto[$stock->producto_id] ?? 0.0) + self::EPSILON);
    }

    /**
     * Aviso M15 al SOLICITANTE (quien pidió la baja espera el cierre); si su
     * usuario ya no existe, fallback a quienes administran sucursales. Patrón
     * F1: try/catch por destinatario — un correo malo no tumba la sync.
     */
    private function avisar(string $evento, BodegaTraslado $orden): void
    {
        try {
            $destinatarios = $orden->solicitante !== null
                ? collect([$orden->solicitante])
                : User::permission('manage sucursales')->get()->unique('id')->values();
            $dispatcher = app(NotificacionDispatcher::class);
        } catch (\Throwable $e) {
            Log::warning('Aviso de baja de bodega no despachado (setup)', ['orden' => $orden->id, 'error' => $e->getMessage()]);

            return;
        }

        $datos = [
            'bodega' => $orden->bodega->nombre ?: '—',
            'destino' => $orden->destino->nombre ?: '—',
            'orden' => $orden->id,
            'url' => route('admin.bodegas.traslados.show', $orden),
        ];

        foreach ($destinatarios as $destinatario) {
            try {
                $dispatcher->despachar($evento, $orden, $destinatario, $datos);
            } catch (\Throwable $e) {
                Log::warning('Aviso de baja de bodega no despachado', [
                    'orden' => $orden->id,
                    'evento' => $evento,
                    'user_id' => $destinatario->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
