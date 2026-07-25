{{-- 404. Al autenticado que navega DENTRO de una ruta (binding que no resuelve)
     lo atiende el render() de bootstrap/app.php: Inicio + aviso.

     Esta pagina cubre el 404 mas comun: una URI que no matchea NINGUNA ruta. Ese
     caso se resuelve ANTES del grupo `web`, asi que NO corre StartSession: aunque
     el usuario tenga sesion, aca `@auth` es FALSE. Por eso el boton "Ir al Inicio"
     va SIEMPRE (sin @auth): condicionarlo dejaba al usuario real en un callejon
     sin salida — justo lo que este lote venia a eliminar — y el test lo tapaba
     porque actingAs() si deja al usuario en el guard (gate R-31, 2026-07-24).
     Para un invitado el boton es igual de util: /dashboard lo lleva al login.

     NUNCA se imprime $exception->getMessage(): ModelNotFound produce
     "No query results for model [App\Models\...] 5". --}}
<x-errors.shell titulo="No encontrado">
    <h1>No encontramos esa página</h1>
    <p>El enlace puede estar roto o el registro ya no existe.</p>
    <a class="btn" href="{{ route('dashboard') }}">Ir al Inicio</a>
</x-errors.shell>
