<?php

namespace App\Jobs;

use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Envia UNA notificacion por su canal (PLAN-M15 §1.1).
 *
 * tries=1 a proposito: el reintento NO lo maneja la cola sino el propio motor
 * (estado=fallida + programada_para con backoff + comando notificaciones:reintentar),
 * para que cada intento quede visible en /admin/notificaciones y sea testeable.
 * Por eso el handle() captura Throwable y NUNCA relanza.
 */
class EnviarNotificacion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $notificacionId)
    {
    }

    public function handle(): void
    {
        $notificacion = Notificacion::find($this->notificacionId);

        // Borrada o ya procesada por otra corrida: no hay nada que hacer
        // (idempotencia ante doble encolado).
        if ($notificacion === null || $notificacion->estado !== Notificacion::PENDIENTE) {
            return;
        }

        try {
            NotificacionDispatcher::canal($notificacion->canal)->enviar($notificacion);

            $notificacion->update([
                'estado' => Notificacion::ENVIADA,
                'enviada_at' => now(),
                'ultimo_error' => null,
            ]);
        } catch (Throwable $e) {
            $intentos = $notificacion->intentos + 1;

            $notificacion->update([
                'estado' => Notificacion::FALLIDA,
                'intentos' => $intentos,
                // Integro para diagnosticar (micro-backlog M15-b: el corte viejo
                // de 1000 se comia la cola de los errores SMTP). El cap es solo
                // defensivo: 16k chars caben en TEXT (64KB) aun a 4 bytes/char.
                'ultimo_error' => mb_substr($e->getMessage(), 0, 16000),
                // Backoff configurable; si ya agoto el maximo, queda fallida
                // terminal (sin proxima fecha) y el comando de reintentos la ignora.
                'programada_para' => $intentos < (int) Configuracion::get('notif_reintentos_max', 3)
                    ? now()->addMinutes($this->backoffMinutos($intentos))
                    : null,
            ]);
        }
    }

    /** Minutos de espera antes del reintento N (1-indexed), desde Configuracion. */
    private function backoffMinutos(int $intento): int
    {
        $escala = Configuracion::get('notif_backoff_minutos', [5, 15, 60]);
        $escala = is_array($escala) && $escala !== [] ? array_values($escala) : [5, 15, 60];

        return (int) $escala[min($intento, count($escala)) - 1];
    }
}
