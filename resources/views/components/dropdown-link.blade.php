{{-- `min-h-11 sm:min-h-0`: mínimo táctil de 44px en móvil. El porqué completo está en primary-button.blade.php (medición del 28-08). --}}
<a {{ $attributes->merge(['class' => 'flex min-h-11 items-center w-full px-4 py-2 sm:min-h-0 text-start text-sm leading-5 text-neutral-700 transition duration-150 ease-in-out hover:bg-neutral-100 focus:bg-neutral-100 focus:outline-none']) }}>{{ $slot }}</a>
