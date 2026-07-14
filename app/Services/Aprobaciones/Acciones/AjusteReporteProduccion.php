<?php

namespace App\Services\Aprobaciones\Acciones;

use App\Models\Aprobacion;
use App\Models\ProduccionReporte;
use App\Services\Aprobaciones\AccionAprobable;
use App\Services\Aprobaciones\ConflictoAccionException;

/**
 * Handler del ajuste de reporte de produccion (primer consumidor, M11).
 * Misma semantica que el ajustar() directo de ProduccionController:
 * actualiza el reporte Y el snapshot `asignadas` de su asignacion.
 */
class AjusteReporteProduccion implements AccionAprobable
{
    public function aplicar(Aprobacion $aprobacion): void
    {
        // Lock propio sobre el agregado destino (la aprobacion ya viene
        // bloqueada por el servicio; orden de locks estable: aprobacion → reporte).
        $reporte = ProduccionReporte::whereKey($aprobacion->aprobable_id)
            ->lockForUpdate()
            ->firstOrFail();

        $snapshot = $aprobacion->datos['objetivo_updated_at'] ?? null;

        if ($snapshot !== null && $reporte->updated_at?->toJSON() !== $snapshot) {
            throw new ConflictoAccionException(
                'El reporte fue modificado después de la solicitud; vuelve a solicitar el ajuste.',
            );
        }

        $nuevo = $aprobacion->datos['nuevo'] ?? [];

        $reporte->update($nuevo);

        if (array_key_exists('asignadas', $nuevo)) {
            $reporte->asignacion?->update(['asignadas' => $nuevo['asignadas']]);
        }
    }
}
