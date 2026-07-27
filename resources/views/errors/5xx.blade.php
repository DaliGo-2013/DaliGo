{{-- Comodin 5xx (502 Bad Gateway, 504 Gateway Timeout...). Mismo cuerpo que el
     500 pero SIN codigo de incidente: estos llegan como HttpException (o los
     emite el proxy), no se reportan y por lo tanto no hay excepcion que buscar
     en el log — mostrar un codigo seria mandar a TI a buscar algo inexistente. --}}
<x-errors.shell titulo="Algo falló">
    <h1>Algo falló de nuestro lado</h1>
    <p>No es culpa tuya. Espera un momento y vuelve a intentarlo.</p>
    <a class="btn" href="{{ url('/dashboard') }}">Ir al Inicio</a>
</x-errors.shell>
