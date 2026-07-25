{{-- 402 Payment Required: no aplica al negocio, pero el vendor trae la version
     en ingles y getHttpExceptionView() la preferiria al comodin. Se reusa el 4xx
     en vez de duplicar el copy. --}}
@include('errors.4xx')
