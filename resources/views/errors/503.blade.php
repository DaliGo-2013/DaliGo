{{-- 503 (mantencion). Lo emite PreventRequestsDuringMaintenance cuando alguien
     corre `php artisan down` (el repo no lo usa en el deploy, pero se puede a
     mano en el servidor) o un abort(503).

     Sin boton: cualquier enlace de la app devolveria otro 503.

     No cubre el 503 que emite LiteSpeed cuando se queda sin workers: ese lo
     genera el servidor y jamas llega a Laravel. Si algun dia se usa `down`,
     conviene `--render="errors::503"` para que la pagina exista aunque el arbol
     este a medias. --}}
<x-errors.shell titulo="En mantención">
    <h1>Estamos en mantención</h1>
    <p>Volvemos en unos minutos. No tienes que hacer nada: vuelve a cargar la página más tarde.</p>
</x-errors.shell>
