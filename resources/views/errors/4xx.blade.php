{{-- Comodin 4xx: lo que el vendor NO trae y hasta hoy caia en la pantalla
     generica de Symfony (405 Method Not Allowed, 400, 410, 413...).
     getHttpExceptionView() prueba errors::{status} y solo si no existe en NINGUNA
     parte (app ni vendor) cae aca.

     Boton SIN @auth: un 405 lo resuelve el ROUTER, antes del grupo `web`, asi que
     no corre StartSession y @auth seria false incluso con sesion valida (la
     leccion del 404 que cazo el gate R-31). Y url(), no route(). --}}
<x-errors.shell titulo="No pudimos procesar eso">
    <h1>No pudimos procesar eso</h1>
    <p>La página o la acción no llegó como esperábamos. Vuelve atrás e inténtalo de nuevo.</p>
    <a class="btn" href="{{ url('/dashboard') }}">Ir al Inicio</a>
</x-errors.shell>
