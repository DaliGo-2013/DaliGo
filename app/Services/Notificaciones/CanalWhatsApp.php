<?php

namespace App\Services\Notificaciones;

use App\Models\Notificacion;
use Illuminate\Support\Facades\Log;

/**
 * Canal WhatsApp — STUB hasta D-007 (sin API de WhatsApp Business aun).
 * Loguea el envio y "tiene exito": deja el enchufe listo para reemplazar
 * este cuerpo por la llamada real (Meta Cloud API o BSP) sin tocar nada mas.
 */
class CanalWhatsApp implements Canal
{
    public function enviar(Notificacion $notificacion): void
    {
        Log::info('[M15][stub] Notificación WhatsApp (canal sin API real hasta D-007).', [
            'notificacion_id' => $notificacion->id,
            'evento' => $notificacion->evento,
            'destinatario' => $notificacion->destinatario ?? $notificacion->user?->email,
            'titulo' => $notificacion->titulo,
        ]);
    }
}
