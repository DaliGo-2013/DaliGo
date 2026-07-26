@props(['align' => 'right', 'width' => 'w-48', 'contentClasses' => 'py-1 bg-white', 'direction' => 'down'])

@php
/**
 * Menú anclado a un disparador.
 *
 * La POSICIÓN FINAL no la deciden estos props: la mide `x-dg-anclar`
 * (resources/js/app.js) cuando el panel se abre, y corre hacia adentro lo que
 * no quepa en la pantalla. `align` y `direction` son sólo la PREFERENCIA — de
 * qué lado nace el panel y desde qué esquina crece la animación.
 *
 * Antes eran load-bearing, y por eso la campanita perdía 72px por la izquierda:
 * `w-80` (320px) anclada `end-0` dentro de una sidebar de 264px no cabe, y
 * nadie puede acertar el lado desde la vista porque depende de dónde caiga el
 * disparador y del ancho de pantalla de quien mira. Misma causa que el globo ⓘ
 * de la bitácora [2026-07-01].
 */
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

// direction=up: el panel NACE hacia arriba (triggers al pie de la sidebar). Si
// aun asi no cabe, x-dg-anclar lo voltea o lo deja scrollear.
$directionClasses = $direction === 'up' ? 'bottom-full mb-2 origin-bottom' : 'mt-2';

// Los anchos válidos se declaran ACÁ —un .blade.php, que es lo único que
// Tailwind v4 escanea— y un token desconocido REVIENTA, mismo criterio que
// AppLayout::ANCHOS. Antes esto era `match ($width) { '48' => 'w-48', default
// => $width }`: `width="48"` y `width="w-80"` funcionaban, pero cualquier otro
// número suelto (`width="56"`) emitía la clase inválida `56` y el panel se
// quedaba SIN ANCHO, en silencio. Agregar un ancho = una entrada más acá.
$anchos = ['w-48', 'w-56', 'w-80'];

if (! in_array($width, $anchos, true)) {
    throw new InvalidArgumentException(
        "Ancho de menú desconocido [{$width}]. Válidos: ".implode(' · ', $anchos).'.'
    );
}
@endphp

<div class="relative" x-data="{ open: false }"
     @click.outside="open = false"
     @keydown.escape.window="open = false"
     @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    {{-- max-w-[calc(100vw-1rem)]: red de seguridad ESTÁTICA, por si el JS aún no
         corrió o falló. Con eso el panel nunca es más ancho que la pantalla, y
         x-dg-anclar se encarga de que además caiga entero dentro de ella. --}}
    <div x-show="open"
            x-dg-anclar
            data-dg-panel
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 {{ $directionClasses }} {{ $width }} max-w-[calc(100vw-1rem)] rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-md ring-1 ring-neutral-200 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
