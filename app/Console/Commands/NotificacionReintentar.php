<?php

namespace App\Console\Commands;

use App\Jobs\EnviarNotificacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-encola las notificaciones fallidas cuyo backoff ya venció (PLAN-M15 §1.1).
 *
 * Atómico (ajuste del visto bueno 2026-07-02, regla check-then-act de la
 * bitácora): reclama las filas dentro de una transacción con lockForUpdate y
 * las pasa a `pendiente` en un solo UPDATE; solo re-despacha las que ESTA
 * corrida reclamó. Con `withoutOverlapping()` en el scheduler, dos corridas no
 * pueden pisarse.
 *
 * Robusto a un scheduler degradado: reclama por `programada_para <= now()`, no
 * asume la cadencia del cron. Si el scheduler corriera cada 20 min en vez de 5
 * (incidencia I-01), igual procesa todo lo vencido, solo con más latencia.
 */
class NotificacionReintentar extends Command
{
    protected $signature = 'notificaciones:reintentar';

    protected $description = 'Re-encola las notificaciones fallidas cuyo backoff ya venció (hasta el máximo de intentos).';

    public function handle(): int
    {
        $max = (int) Configuracion::get('notif_reintentos_max', 3);

        // Reclamo atómico: bloquea y marca en una sola transacción; ninguna otra
        // corrida verá esas filas como reclamables (lock + cambio de estado).
        $ids = DB::transaction(function () use ($max) {
            // (a) Fallidas cuyo backoff ya venció (reintento normal). Se limpia
            //     programada_para: se reintenta ahora, la fecha vieja no aplica.
            $fallidas = Notificacion::query()
                ->where('estado', Notificacion::FALLIDA)
                ->whereNotNull('programada_para')
                ->where('programada_para', '<=', now())
                ->where('intentos', '<', $max)
                ->lockForUpdate()
                ->pluck('id');

            if ($fallidas->isNotEmpty()) {
                Notificacion::whereIn('id', $fallidas)
                    ->update(['estado' => Notificacion::PENDIENTE, 'programada_para' => null]);
            }

            // (b) Pendientes huérfanas: quedaron 'pendiente' pero fuera de la cola
            //     (crash entre el commit del claim y el dispatch). queue:work corre
            //     cada minuto, así que una pendiente de >10 min ya no está en vuelo.
            //     Se re-despachan (touch de updated_at para no re-tomarlas cada 5 min);
            //     el guard del job las ignora si en realidad ya se procesaron.
            $huerfanas = Notificacion::query()
                ->where('estado', Notificacion::PENDIENTE)
                ->where('updated_at', '<=', now()->subMinutes(10))
                ->lockForUpdate()
                ->pluck('id');

            if ($huerfanas->isNotEmpty()) {
                Notificacion::whereIn('id', $huerfanas)->update(['updated_at' => now()]);
            }

            return $fallidas->merge($huerfanas);
        });

        // Fuera de la transacción: el job re-procesa cada notificación reclamada.
        foreach ($ids as $id) {
            EnviarNotificacion::dispatch($id);
        }

        $this->info("Reintentos encolados: {$ids->count()}.");

        return self::SUCCESS;
    }
}
