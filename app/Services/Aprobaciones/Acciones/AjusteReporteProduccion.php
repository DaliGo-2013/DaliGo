<?php

namespace App\Services\Aprobaciones\Acciones;

use App\Models\Aprobacion;
use App\Models\ProduccionAjuste;
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
            ->first();

        // El jefe pudo borrar el reporte (produccion.reporte.destroy) mientras la
        // solicitud estaba pendiente: con firstOrFail el aprobador recibia un 404
        // crudo al apretar "Aprobar". Como conflicto, la solicitud se rechaza sola
        // y el aprobador lee "No se pudo aplicar: ..." en su bandeja.
        if ($reporte === null) {
            throw new ConflictoAccionException(
                'el reporte de esta solicitud ya no existe (fue eliminado).',
            );
        }

        $snapshot = $aprobacion->datos['objetivo_updated_at'] ?? null;

        if ($snapshot !== null && $reporte->updated_at?->toJSON() !== $snapshot) {
            throw new ConflictoAccionException(
                'El reporte fue modificado después de la solicitud; vuelve a solicitar el ajuste.',
            );
        }

        $nuevo = $aprobacion->datos['nuevo'] ?? [];
        $nuevo = is_array($nuevo) ? $nuevo : [];

        // El "antes" se lee del reporte BLOQUEADO, no del snapshot del payload:
        // es lo que de verdad había en el momento de aplicar (el snapshot de
        // updated_at ya garantiza que coinciden, pero la fila fresca es la
        // fuente). Una fila por campo que cambió (dueño 09-09): el soplador
        // ve quién, qué ítem y cuánto; el reporte queda «Modificado».
        $anterior = $reporte->only(array_keys(ProduccionAjuste::ETIQUETAS));

        $reporte->update($nuevo);

        if (array_key_exists('asignadas', $nuevo)) {
            $reporte->asignacion?->update(['asignadas' => $nuevo['asignadas']]);
        }

        ProduccionAjuste::registrar($reporte, $anterior, $nuevo, $aprobacion);
    }
}
