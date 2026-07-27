<?php

namespace App\Services\Notificaciones;

use App\Jobs\EnviarNotificacion;
use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\PreferenciaCanal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Punto unico de despacho de notificaciones (PLAN-M15 §1.1).
 *
 * Uso desde cualquier modulo emisor:
 *   app(NotificacionDispatcher::class)->despachar('sistema.prueba', $origen, $usuario, ['nombre' => '...']);
 *
 * 1. Resuelve la plantilla (clave json `notif_plantilla_{evento}` de Configuracion;
 *    fallback: etiqueta del catalogo EVENTOS) y reemplaza {placeholders} con $datos.
 * 2. Resuelve canales efectivos: database SIEMPRE (registro campanita) para
 *    usuarios internos; mail/whatsapp segun PreferenciaCanal (defaults: mail si,
 *    whatsapp no — stub hasta D-007). Destinatario externo (string email): solo mail.
 * 3. Crea UNA fila `notificaciones` por canal (estado=pendiente) y encola
 *    EnviarNotificacion por cada una (cola database; el cron la procesa).
 */
class NotificacionDispatcher
{
    /**
     * @param  User|string  $destinatario  usuario interno o email externo
     * @param  array<string, mixed>  $datos  placeholders de la plantilla + payload
     * @return Collection<int, Notificacion> las notificaciones creadas
     */
    public function despachar(string $evento, ?Model $origen, User|string $destinatario, array $datos = []): Collection
    {
        if (! array_key_exists($evento, Notificacion::EVENTOS)) {
            throw new InvalidArgumentException("Evento de notificación desconocido: [{$evento}]. Agrégalo a Notificacion::EVENTOS.");
        }

        [$titulo, $cuerpo] = $this->renderizar($evento, $datos);

        $user = $destinatario instanceof User ? $destinatario : null;
        $email = $destinatario instanceof User ? null : $destinatario;

        $creadas = collect();

        foreach ($this->canalesEfectivos($evento, $user) as $canal) {
            // El canal database no tiene transporte externo: nace ENVIADA y la
            // campanita lo muestra AL TIRO (hallazgo #9 del QA 15-07 — viajar
            // por la cola de la grilla le metía hasta 15 min de latencia
            // artificial). mail/whatsapp siguen por la cola como siempre; el
            // job conserva su rama database para filas pendientes previas al
            // deploy (idempotente).
            $esDatabase = $canal === Notificacion::CANAL_DATABASE;

            $notificacion = Notificacion::create([
                'evento' => $evento,
                'notificable_type' => $origen?->getMorphClass(),
                'notificable_id' => $origen?->getKey(),
                'user_id' => $user?->id,
                'destinatario' => $email,
                'canal' => $canal,
                'titulo' => $titulo,
                'cuerpo' => $cuerpo,
                'payload' => $datos,
                'estado' => $esDatabase ? Notificacion::ENVIADA : Notificacion::PENDIENTE,
                'enviada_at' => $esDatabase ? now() : null,
            ]);

            if (! $esDatabase) {
                EnviarNotificacion::dispatch($notificacion->id);
            }

            $creadas->push($notificacion);
        }

        return $creadas;
    }

    /**
     * Canales a usar para este evento/destinatario, respetando preferencias.
     *
     * @return list<string>
     */
    private function canalesEfectivos(string $evento, ?User $user): array
    {
        // Destinatario externo (solo un email): unico canal posible es mail.
        if ($user === null) {
            return [Notificacion::CANAL_MAIL];
        }

        $canales = [Notificacion::CANAL_DATABASE]; // registro campanita, siempre

        foreach ([Notificacion::CANAL_MAIL, Notificacion::CANAL_WHATSAPP] as $canal) {
            if (PreferenciaCanal::habilitadoPara($user, $evento, $canal)) {
                $canales[] = $canal;
            }
        }

        return $canales;
    }

    /**
     * Plantilla del evento → [titulo, cuerpo] con {placeholders} resueltos.
     * La plantilla vive en Configuracion (editable en la UI); si no existe,
     * cae a la etiqueta del catalogo (el sistema nunca se cae por plantilla
     * faltante: se notifica igual, con texto generico).
     *
     * @return array{0: string, 1: string}
     */
    private function renderizar(string $evento, array $datos): array
    {
        $plantilla = Configuracion::get('notif_plantilla_'.str_replace('.', '_', $evento));

        $titulo = is_array($plantilla) && filled($plantilla['asunto'] ?? null)
            ? $plantilla['asunto']
            : Notificacion::EVENTOS[$evento];
        $cuerpo = is_array($plantilla) ? (string) ($plantilla['cuerpo'] ?? '') : '';

        // Fallback nunca-mudo (lote NOTIF-1): un evento sin plantilla degrada
        // a un cuerpo LEGIBLE con los escalares del payload («clave: valor»
        // por línea), no a vacío. 'url' se omite: la fila ya navega por
        // urlDestino() y cruda solo ensucia la campanita.
        if (! is_array($plantilla)) {
            $cuerpo = collect($datos)
                ->filter(fn ($v) => is_scalar($v))
                ->except('url')
                ->map(fn ($v, $k) => $k.': '.$v)
                ->implode("\n");
        }

        $reemplazos = collect($datos)
            ->filter(fn ($v) => is_scalar($v))
            ->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])
            ->all();

        return [strtr($titulo, $reemplazos), strtr($cuerpo, $reemplazos)];
    }

    /** Resuelve la implementacion de un canal (via container: mockeable en tests). */
    public static function canal(string $canal): Canal
    {
        return match ($canal) {
            Notificacion::CANAL_MAIL => app(CanalMail::class),
            Notificacion::CANAL_DATABASE => app(CanalDatabase::class),
            Notificacion::CANAL_WHATSAPP => app(CanalWhatsApp::class),
            default => throw new InvalidArgumentException("Canal desconocido: [{$canal}]"),
        };
    }
}
