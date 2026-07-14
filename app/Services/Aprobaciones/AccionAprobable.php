<?php

namespace App\Services\Aprobaciones;

use App\Models\Aprobacion;

/**
 * Contrato de la accion diferida de una aprobacion (PLAN-M14 §1.1).
 *
 * aplicar() corre DENTRO de la transaccion de aprobacion (o de la
 * auto-aprobacion inline): debe tomar su PROPIO lock sobre el agregado
 * destino (lockForUpdate) y re-validar vigencia del payload comparando
 * `datos['objetivo_updated_at']` contra el updated_at actual — si el
 * objetivo cambio desde la solicitud, lanza ConflictoAccionException
 * (jamas se aplica un payload obsoleto sobre datos nuevos).
 */
interface AccionAprobable
{
    /**
     * @throws ConflictoAccionException si el objetivo cambio desde la solicitud
     */
    public function aplicar(Aprobacion $aprobacion): void;
}
