{{-- 403. A un usuario autenticado que NAVEGA (GET) casi nunca le llega esta
     pagina: el render() de bootstrap/app.php lo manda al Inicio con la
     mini-notificacion. Esta vista cubre lo que ahi NO se redirige: el visitante
     con link firmado caducado, las acciones (POST/PUT/DELETE) rechazadas por
     permiso, y como red de seguridad si el redirect no aplica.
     NUNCA se imprime $exception->getMessage() (spatie lo pone en ingles). --}}
<x-errors.shell titulo="Sin permiso">
    @auth
        <h1>No tienes permiso para entrar ahí</h1>
        <p>Habla con un administrador si necesitas acceso a esta parte del sistema.</p>
        <a class="btn" href="{{ route('dashboard') }}">Ir al Inicio</a>
    @else
        {{-- Cliente sin cuenta (QR / link de cotizacion): no tiene sesion que
             iniciar, mandarlo a /login seria empujarlo a una puerta sin llave. --}}
        <h1>Este enlace no es válido</h1>
        <p>El enlace pudo expirar o quedar incompleto. Pide uno nuevo a tu contacto en Impdali.</p>
    @endauth
</x-errors.shell>
