<?php

namespace App\Services\Mensajes;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use App\Services\Notificaciones\NotificacionDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Envio de mensajes del chat interno (MSG-1, PLAN-MENSAJES §5.1/§5.3).
 *
 * Todo pasa por aqui (el controller de MSG-2 solo delega): la transaccion
 * con lock sobre la conversacion serializa envios concurrentes al mismo
 * hilo, y el AVISO M15 sale con el anti-spam de RAFAGA — se despacha
 * `mensaje.recibido` SOLO si el contador de no-leidos del receptor estaba
 * en 0 antes de este mensaje. Mientras no lea, los siguientes callan (ya
 * tiene la campanita encendida por ese hilo); al leer, el proximo vuelve a
 * avisar. Un chat activo de 40 mensajes = 1 campanita + 1 mail.
 */
class Mensajeria
{
    public function __construct(private NotificacionDispatcher $dispatcher)
    {
    }

    public function enviar(User $emisor, User $receptor, string $texto): Mensaje
    {
        $texto = trim($texto);

        if ($texto === '') {
            throw new InvalidArgumentException('El mensaje no puede ir vacío.');
        }

        if (mb_strlen($texto) > Mensaje::TEXTO_MAX) {
            throw new InvalidArgumentException('El mensaje no puede superar los '.Mensaje::TEXTO_MAX.' caracteres.');
        }

        // Canonicaliza el par y crea el hilo si no existe (rechaza emisor ==
        // receptor). El unique compuesto es la red final ante una carrera.
        $conversacion = Conversacion::entre($emisor, $receptor);

        $avisar = false;

        $mensaje = DB::transaction(function () use ($conversacion, $emisor, $receptor, $texto, &$avisar) {
            // Lock de la fila del hilo: serializa dos envios simultaneos al
            // mismo par (los contadores y ultimo_mensaje_at se mueven aqui).
            $fila = Conversacion::whereKey($conversacion->getKey())->lockForUpdate()->first();

            $columna = $fila->columnaContadorDe($receptor);

            // RAFAGA: se avisa solo al pasar de 0 no-leidos (leido bajo lock,
            // ANTES del +1 — el corazon del anti-spam del anexo §5.3).
            $avisar = (int) $fila->{$columna} === 0;

            $mensaje = $fila->mensajes()->create([
                'emisor_id' => $emisor->id,
                'texto' => $texto,
            ]);

            $fila->update([
                $columna => (int) $fila->{$columna} + 1,
                'ultimo_mensaje_at' => now(),
            ]);

            return $mensaje;
        });

        // El aviso va FUERA de la transaccion: el mensaje ya esta commiteado
        // y un canal caido no lo revierte (molde del fan-out del corte SIC).
        if ($avisar) {
            try {
                $this->dispatcher->despachar('mensaje.recibido', $conversacion, $receptor, [
                    'emisor' => $emisor->name,
                    'extracto' => Str::limit($texto, 80),
                ]);
            } catch (Throwable $e) {
                Log::warning('Mensajeria: aviso de mensaje recibido no despachado', [
                    'conversacion_id' => $conversacion->id,
                    'receptor_id' => $receptor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $mensaje;
    }
}
