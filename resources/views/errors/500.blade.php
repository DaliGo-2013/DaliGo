{{-- 500. Esta pagina se muestra CUANDO LA APP YA ESTA ROTA, asi que no puede
     depender de casi nada. Tres reglas duras, cada una por un modo de falla real:

     1) url('/dashboard'), NUNCA route(): si lo que fallo fue el ruteo (o el
        route:cache quedo a medias), route() lanzaria DENTRO de esta vista y
        Handler::renderHttpException se come el throw en produccion => vuelve la
        pantalla generica de Symfony. Mismo criterio que errors/419.blade.php.
     2) SIN @auth / Auth::: en produccion la sesion vive en MySQL
        (SESSION_DRIVER=database), que es la causa mas probable de un 500 —
        preguntar por el usuario tocaria justo lo que esta caido.
     3) NUNCA $exception->getMessage(): para un 500 ese mensaje es el ORIGINAL
        verbatim (prepareResponse hace new HttpException(500, $e->getMessage()))
        => "SQLSTATE[42S02] ... Table 'impdali_daligo.x' doesn't exist".

     El codigo de incidente lo genera el callback de $exceptions->context() en
     bootstrap/app.php y viaja en la misma linea de log de la excepcion. Si no
     hay (p. ej. un abort(500) explicito, que no se reporta), el bloque no se
     muestra: no inventamos un codigo que TI no podria buscar. --}}
@php($incidente = \App\Support\CodigoIncidente::actual())

<x-errors.shell titulo="Algo falló">
    <h1>Algo falló de nuestro lado</h1>
    <p>No es culpa tuya. El error quedó registrado y lo vamos a revisar. Espera un momento y vuelve a intentarlo.</p>

    @if ($incidente)
        <p class="codigo-label">Código del incidente</p>
        <p class="codigo" data-incidente>{{ $incidente }}</p>
        <p>Si el problema sigue, dicta ese código a soporte: con él ubicamos exactamente qué pasó.</p>
    @endif

    <a class="btn" href="{{ url('/dashboard') }}">Ir al Inicio</a>
</x-errors.shell>
