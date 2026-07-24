{{-- El tamaño va en el prop `size` (NO en class): $attributes->merge CONCATENA
     clases y con h-8/h-10 a la vez gana la que venga después en el CSS, no la
     del llamador (gotcha bitácora 2026-07-24 del avatar de la topbar). --}}
@props(['size' => 'h-10 w-10 text-sm'])
<div {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center rounded-full bg-neutral-100 font-semibold uppercase text-neutral-600 '.$size]) }}>{{ $slot }}</div>
