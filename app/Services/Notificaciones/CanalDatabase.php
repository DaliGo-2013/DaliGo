<?php

namespace App\Services\Notificaciones;

use App\Models\Notificacion;

/**
 * Canal in-app (campanita): la fila en `notificaciones` ES la entrega —
 * la campanita lee la tabla. No hay transporte externo, asi que enviar()
 * no hace nada y nunca falla; el job marcara la fila como enviada.
 */
class CanalDatabase implements Canal
{
    public function enviar(Notificacion $notificacion): void
    {
        // Intencionalmente vacio: la persistencia ya ocurrio en el dispatcher.
    }
}
