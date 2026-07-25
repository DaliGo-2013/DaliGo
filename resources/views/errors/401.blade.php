{{-- 401. Tapa la del vendor (en ingles). En la practica el middleware `auth` ya
     redirige al login antes de llegar aca, asi que esto cubre un abort(401)
     explicito o un guard sin redirect. --}}
<x-errors.shell titulo="Sesión no activa">
    <h1>Necesitas iniciar sesión</h1>
    <p>Tu sesión no está activa o no alcanza para ver esa página.</p>
    <a class="btn" href="{{ url('/login') }}">Iniciar sesión</a>
</x-errors.shell>
