{{-- 429 (demasiados intentos). El caso REAL no es un usuario interno: son las 15
     rutas publicas del QR con throttle:6,1 (routes/web.php), asi que quien ve
     esto es un CLIENTE llenando el formulario que reintento muy rapido. Hasta hoy
     leia "429 | TOO MANY REQUESTS" en ingles.

     Sin boton a proposito: no tiene cuenta, mandarlo al Inicio/login seria
     empujarlo a una puerta sin llave (mismo criterio que la rama de invitado del
     403). Lo que necesita es saber cuanto esperar y que sus datos no se perdieron.

     Los segundos salen del header Retry-After que pone ThrottleRequests en la
     propia excepcion. Es una cota superior (solo baja mientras el usuario lee) y
     viene del mismo limitador que va a decidir. Se acota a 300 y degrada a "un
     minuto" si alguien hace un abort(429) a mano, sin header.

     NO se usa <meta http-equiv="refresh">: recargar consumiria el limitador otra
     vez (bucle) y en un POST no reenvia nada. --}}
@php($espera = (int) ($exception->getHeaders()['Retry-After'] ?? 0))

<x-errors.shell titulo="Demasiados intentos">
    <h1>Vas muy rápido</h1>
    @if ($espera > 0 && $espera <= 300)
        <p>Recibimos demasiados intentos seguidos. Espera {{ $espera }} segundos, vuelve atrás en el navegador y envía el formulario otra vez.</p>
    @else
        <p>Recibimos demasiados intentos seguidos. Espera un minuto, vuelve atrás en el navegador y envía el formulario otra vez.</p>
    @endif
    <p>No enviamos nada todavía: tus datos siguen en el formulario.</p>
</x-errors.shell>
