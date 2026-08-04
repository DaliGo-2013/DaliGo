<?php

namespace App\Services\Aprobaciones\Acciones;

use App\Models\Aprobacion;
use App\Models\Devolucion;
use App\Services\Aprobaciones\AccionAprobable;
use App\Services\Aprobaciones\ConflictoAccionException;

/**
 * Handler del reembolso de una devolución (M13, segundo consumidor del motor).
 *
 * Corre DENTRO de la transacción de aprobación (o de la auto-aprobación
 * inline, cuando el monto no supera el umbral): marca la devolución como
 * REEMBOLSADA. M13 registra la DECISIÓN de reembolso — la nota de crédito
 * es de M05 y hoy no se emite (PLAN-M13 §4).
 */
class ReembolsoDevolucion implements AccionAprobable
{
    public function aplicar(Aprobacion $aprobacion): void
    {
        // Lock propio sobre el agregado destino (orden estable de locks:
        // aprobacion → devolucion, igual que AjusteReporteProduccion).
        $devolucion = Devolucion::whereKey($aprobacion->aprobable_id)
            ->lockForUpdate()
            ->first();

        if ($devolucion === null) {
            throw new ConflictoAccionException(
                'la devolución de esta solicitud ya no existe.',
            );
        }

        $snapshot = $aprobacion->datos['objetivo_updated_at'] ?? null;

        if ($snapshot !== null && $devolucion->updated_at?->toJSON() !== $snapshot) {
            throw new ConflictoAccionException(
                'La devolución fue modificada después de la solicitud; vuelve a resolverla.',
            );
        }

        // La devolución pudo resolverse por otro camino (reingreso, rechazo)
        // mientras la solicitud esperaba: aplicar el payload viejo pisaría esa
        // resolución. Conflicto → rechazo automático legible en la bandeja.
        if ($devolucion->esResuelta()) {
            throw new ConflictoAccionException(
                "la devolución {$devolucion->folio} ya fue resuelta ({$devolucion->estado}).",
            );
        }

        $devolucion->update([
            'estado' => Devolucion::REEMBOLSADA,
            'monto_reembolso' => $aprobacion->datos['nuevo']['monto_reembolso'] ?? $aprobacion->monto,
            'resolucion_motivo' => $aprobacion->datos['nuevo']['resolucion_motivo'] ?? $aprobacion->motivo,
            'resuelta_at' => now(),
            'resuelta_por' => $aprobacion->datos['nuevo']['resuelta_por'] ?? null,
        ]);
    }
}
