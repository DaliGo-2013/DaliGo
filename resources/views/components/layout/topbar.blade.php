{{-- Barra SOLO MÓVIL (lg:hidden) y mínima (h-12): hamburguesa + título del
     módulo + campana. En desktop no existe — el espacio vertical es área de
     trabajo (pedido del dueño 24-07); campanita y usuario viven en el pie de
     la sidebar. La campana móvil va SIEMPRE visible en la barra (hallazgo QA
     14-07; su aria-label exacto es contrato de CampanitaTest). --}}
<header class="flex h-12 shrink-0 items-center gap-2 border-b border-neutral-200 bg-white px-3 lg:hidden">
    {{-- aria-controls: el aria-expanded solo dice "abierto/cerrado"; sin decir
         DE QUÉ, un lector de pantalla anuncia un estado huérfano. Apunta al id
         del <aside> (gate P-NAV-05). --}}
    <button type="button" @click="menuAbierto = true" :aria-expanded="menuAbierto"
            aria-controls="dg-menu-lateral"
            class="-ms-1 inline-flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none">
        <x-icon.bars-3 class="h-6 w-6" />
        <span class="sr-only">Abrir menú</span>
    </button>

    {{-- h1 único de la página (a11y) y render PEGADO al tag a propósito:
         la forma contigua `text-neutral-900">Label` es el ancla estable de
         SidebarTest (doctrina anti verde-engañoso). --}}
    <h1 class="flex min-w-0 items-center gap-1.5 truncate text-sm font-medium text-neutral-900">{{ $activo['label'] ?? config('app.name', 'DaliGo') }}@if ($badgeActivo > 0) <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white" title="{{ str_replace(':n', $badgeActivo, $activo['badge_title'] ?? ':n') }}">{{ $badgeActivo }}</span>@endif</h1>

    {{-- Campana móvil: link directo a la bandeja (un dropdown en 375px tapa
         la pantalla y la página ya existe). --}}
    <a href="{{ route('notificaciones.index') }}"
        aria-label="Notificaciones{{ $conteo > 0 ? ' ('.$conteo.' sin leer)' : '' }}"
        {{-- Mínimo táctil de 44px: medía 40x40 y esta barra SOLO existe en móvil
             (`lg:hidden`), así que es la campana que se toca de verdad — la del pie
             de la sidebar vive en el drawer. Sin `sm:`: acá nunca hay mouse. --}}
        class="relative ms-auto inline-flex min-h-11 min-w-11 items-center justify-center rounded-md p-2 text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none">
        <x-icon.bell class="h-6 w-6" />
        @if ($conteo > 0)
            <span class="absolute right-0 top-0 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-600 px-1 text-xs font-semibold tabular-nums text-white">{{ $conteo > 9 ? '9+' : $conteo }}</span>
        @endif
        <span class="sr-only">Notificaciones</span>
    </a>
</header>
