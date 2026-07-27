{{-- 419 (sesion / token CSRF expirado). Red de seguridad: en peticiones web el
     render() de bootstrap/app.php devuelve al formulario con un flash, asi que
     esta pagina solo aparece si ese redirect no aplica.

     Migrada al shell comun (antes duplicaba su bloque <style> textualmente, de
     cuando el shell todavia no existia): asi las reglas del shell —y cualquier
     arreglo futuro, como el de contraste que pidio el gate R-31— llegan tambien
     aca en vez de quedar derivando en silencio. --}}
<x-errors.shell titulo="Sesión expirada">
    <h1>Tu sesión expiró</h1>
    <p>Por seguridad, el formulario caducó (o estuvo abierto demasiado tiempo). Vuelve atrás e inténtalo nuevamente.</p>
    <a class="btn" href="{{ url('/login') }}">Volver a iniciar sesión</a>
</x-errors.shell>
