<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\LimiteSesiones;
use Illuminate\Auth\Events\Login;

/**
 * Al entrar un usuario, recorta sus sesiones al límite vigente
 * (LimiteSesiones — default 3, paramétrico por rol/usuario): se conservan
 * las más nuevas y el login que está naciendo SIEMPRE entra.
 *
 * PRIMER listener del repo: lo registra el auto-descubrimiento de Laravel 12
 * sobre app/Listeners (activo aunque bootstrap/app.php no mencione
 * withEvents(); verificado en el vendor). NO registrarlo además con
 * Event::listen — correría dos veces por evento.
 *
 * El evento Login lo disparan EXACTAMENTE los dos caminos que hay que
 * cubrir, y ningún otro (grep del 01-09): el form de login (Auth::attempt)
 * y el re-ingreso automático por la cookie «recordarme» (recaller).
 * `actingAs` de los tests NO lo dispara (setUser → evento Authenticated).
 *
 * Timing (verificado en SessionGuard/Store): el guard regenera la sesión
 * —destruyendo la fila vieja— ANTES de disparar Login, y la fila nueva se
 * escribe recién al FINAL del request. O sea: cuando esto corre, en la tabla
 * están solo las sesiones de LOS OTROS dispositivos → se dejan (límite − 1)
 * y el total tras el request queda exacto en el límite.
 */
class LimitarSesionesAlEntrar
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web' || ! $event->user instanceof User) {
            return;
        }

        LimiteSesiones::recortar(
            $event->user,
            request()->hasSession() ? request()->session()->getId() : null,
        );
    }
}
