{{-- 429 (demasiados intentos). Quien mas lo ve es un CLIENTE del QR: las 15 rutas
     publicas llevan throttle:6,1 y hasta hoy leia "429 | TOO MANY REQUESTS".

     EL COPY NO PUEDE PROMETER NADA SOBRE LOS DATOS (bloqueante del gate R-31).
     Tres cosas verificadas que tumbaron la primera version:
      - `ThrottleRequests::resolveRequestSignature` para invitados usa
        sha1(dominio|ip), SIN la ruta: las 15 rutas publicas comparten UN bucket de
        6/min por IP y las paginas de "listo" estan DENTRO. O sea que se puede caer
        en 429 DESPUES de haber enviado bien; decirle "no enviamos nada" e invitarlo
        a "enviar el formulario otra vez" le hacia DUPLICAR la orden en el taller.
      - de las 17 rutas con throttle, 11 son GET: ahi no hay formulario ninguno.
      - los dos adjuntos del ingreso por QR son <input type="file" capture>, y
        ningun navegador los restaura al volver atras.
     Por eso: mensaje neutral + advertencia explicita de NO reenviar.

     Salida solo para autenticados (hay 2 rutas con throttle dentro de web+auth:
     reenviar el correo de verificacion) — un cliente del QR no tiene cuenta y
     mandarlo al login seria una puerta sin llave. Aca `@auth` SI es seguro, a
     diferencia de la pagina del 500: un 429 no implica que la app este rota, y
     `url()` no lanza nunca.

     NO se usa <meta http-equiv="refresh">: recargar consumiria el limitador otra
     vez (bucle) y en un POST no reenvia nada. --}}
@php($espera = (int) (($exception ?? null)?->getHeaders()['Retry-After'] ?? 0))

<x-errors.shell titulo="Demasiados intentos">
    <h1>Vas muy rápido</h1>

    @if ($espera > 0 && $espera <= 300)
        <p>Recibimos demasiados intentos seguidos desde tu conexión. Espera {{ $espera }} segundos y vuelve a intentarlo.</p>
    @elseif ($espera > 300)
        {{-- No decir "un minuto" cuando la espera es larga: mentiria hacia el lado peligroso. --}}
        <p>Recibimos demasiados intentos seguidos desde tu conexión. Vuelve a intentarlo más tarde.</p>
    @else
        <p>Recibimos demasiados intentos seguidos desde tu conexión. Espera un minuto y vuelve a intentarlo.</p>
    @endif

    <p>Si acabas de enviar un formulario, <strong>no lo envíes de nuevo</strong>: puede que ya haya quedado registrado. Espera y vuelve a cargar la página.</p>

    @auth
        <a class="btn" href="{{ url('/dashboard') }}">Ir al Inicio</a>
    @endauth
</x-errors.shell>
