@props(['titulo' => null])

{{-- Grupo de campos dentro de una pantalla. ES el reemplazo del patrón
     `<div class="rounded-2xl border ... p-4">` copiado a mano en las vistas.

     EL PUNTO: en móvil NO dibuja marco. Una tarjeta blanca dentro de otra
     tarjeta blanca no se ve —el borde se pierde contra el mismo fondo— pero SÍ
     cobra sus 16px de padding a cada lado. Medido a 375px en el formulario por
     cantidad: el campo quedaba en 217px de ancho útil sobre 375, con 5 capas de
     padding sumando 158px = 42% de la pantalla. Desde sm:, donde el aire sí
     ordena y la pantalla alcanza, vuelve la tarjeta de siempre.

     El título va como prop y no como markup del llamador para que las 3 vistas
     del QR (y las que vengan) no vuelvan a divergir en el tamaño de la
     etiqueta.

     Regla asociada: «Marco horizontal» en las Reglas de diseño de CLAUDE.md, y
     el candado `MarcoHorizontalTest` que impide que el patrón viejo vuelva. --}}
<div {{ $attributes->merge(['class' => 'space-y-4 sm:rounded-2xl sm:border sm:border-neutral-200 sm:bg-white sm:p-4 sm:shadow-sm']) }}>
    @isset($titulo)
        <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $titulo }}</h2>
    @endisset

    {{ $slot }}
</div>
