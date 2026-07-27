{{-- Sidebar V4 (menú Talana): UN SOLO <aside> es la sidebar fija en desktop
     (lg:, 264px) y el drawer off-canvas izquierdo en móvil (300px) — así el
     menú existe UNA vez y no puede driftear entre breakpoints (el nav viejo
     ya había divergido). El estado `menuAbierto` vive en el x-data del shell
     (layouts/app.blade.php). Anti-flash pre-Alpine: la clase estática
     max-lg:-translate-x-full oculta el drawer desde el primer paint; Alpine
     solo la RETIRA al abrir (nunca hay dos utilidades translate en pugna).
     flex-col: el nav crece y el PIE (usuario) queda abajo — en desktop NO
     hay topbar (el espacio vertical es área de trabajo, pedido del dueño
     24-07). La campanita vive en la CABECERA (desktop): pedido del dueño
     24-07 tras QA — junto al nombre en el pie se veía "extraña"; en la
     cabecera es "arriba a la derecha" del panel de nav, sin restar alto ni
     chocar con los botones de acción que cada pantalla ya tiene arriba a
     la derecha (evita el riesgo de un botón flotante sobre el contenido).
     z-40 TAMBIÉN en lg: (antes la reseteaba a auto ahí): el panel mide
     320px y la sidebar 264px, así que al abrirse cruza sobre <main>. `sticky`
     crea contexto de apilamiento aunque el z-index sea auto, y con `auto` la
     sidebar pintaba por DEBAJO de los z-10/z-20/z-30 del contenido (los
     autocompletados, las cabeceras pegajosas de ST). Los z-50 legítimos
     —modal, lightbox de fotos, ayuda-serie— siguen ganando, que es correcto. --}}
<aside
    class="fixed inset-y-0 left-0 z-40 flex w-[300px] max-lg:-translate-x-full flex-col border-r border-neutral-200 bg-white max-lg:transition-transform max-lg:duration-150 lg:sticky lg:top-0 lg:h-screen lg:w-[264px] lg:shrink-0"
    :class="{ 'max-lg:-translate-x-full': ! menuAbierto }">

    <div class="flex shrink-0 items-center justify-between border-b border-neutral-100 px-4 py-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <x-application-logo class="h-9 w-9 text-base" />
            <span class="text-lg font-semibold tracking-tight text-neutral-900">DaliGo</span>
        </a>

        {{-- Campanita: SOLO desktop (móvil ya la tiene, siempre visible, en
             su propia barra — regla QA 14-07). Mutuamente excluyente con el
             botón de cerrar de abajo (lg:hidden): nunca se ven los dos. --}}
        <div class="hidden lg:flex" data-menu-campanita>
            @include('layouts.partials.campanita', ['dgNoLeidas' => $noLeidas, 'dgConteo' => $conteo, 'dgBadges' => $badges])
        </div>

        <button type="button" @click="menuAbierto = false"
                class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none lg:hidden">
            <x-icon.x-mark class="h-5 w-5" />
            <span class="sr-only">Cerrar menú</span>
        </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Menú principal">
        @foreach ($modulos as $key => $modulo)
            @isset($modulo['items'])
                {{-- Suma de pendientes de los ítems visibles: la categoría la
                     muestra cuando está CERRADA (pedido del jefe 24-07). --}}
                @php
                    $badgeHijos = collect($modulo['items'])
                        ->sum(fn ($i) => isset($i['badge']) ? ($badges[$i['badge']] ?? 0) : 0);
                @endphp
                <x-sidebar-group :modulo="$modulo" :clave="$key"
                    :abierto="($activo['key'] ?? null) === $key"
                    :activo="($activo['key'] ?? null) === $key"
                    :badge="isset($modulo['badge']) ? ($badges[$modulo['badge']] ?? 0) : 0"
                    :badge-hijos="$badgeHijos">
                    @foreach ($modulo['items'] as $item)
                        <x-sidebar-item :item="$item" :activo="request()->routeIs(...$item['activo'])"
                            :badge="isset($item['badge']) ? ($badges[$item['badge']] ?? 0) : 0" />
                    @endforeach
                </x-sidebar-group>
            @else
                {{-- Link directo de primer nivel (Dashboard / Mi producción /
                     Aprobaciones): el acceso 1-clic del operario es a propósito. --}}
                @php
                    $esActivo = request()->routeIs(...$modulo['activo']);
                    $badgeDirecto = isset($modulo['badge']) ? ($badges[$modulo['badge']] ?? 0) : 0;
                @endphp
                <a href="{{ route($modulo['route']) }}"@if ($esActivo) aria-current="page"@endif
                   class="{{ $esActivo
                       ? 'flex items-center gap-3 rounded-lg bg-brand-50 px-3 py-3 text-sm font-semibold text-brand-700 lg:py-2.5'
                       : 'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-neutral-900 transition duration-150 hover:bg-neutral-50 lg:py-2.5' }}">
                    <span class="{{ $esActivo
                        ? 'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white'
                        : 'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700' }}">
                        <x-dynamic-component :component="'icon.' . $modulo['icon']" class="h-5 w-5" />
                    </span>
                    {{ $modulo['label'] }}
                    @if ($badgeDirecto > 0)
                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white"
                              title="{{ str_replace(':n', $badgeDirecto, $modulo['badge_title'] ?? ':n') }}">{{ $badgeDirecto }}</span>
                    @endif
                </a>
            @endisset
        @endforeach
    </nav>

    {{-- PIE: usuario (la campanita se mudó a la cabecera, pulido 24-07). Sin
         avatar de iniciales a propósito (pedido del dueño: "es ruido, no
         aporta") — el chevron es la señal de "esto abre un menú". En móvil
         este mismo pie va al fondo del drawer. --}}
    <div class="shrink-0 border-t border-neutral-100 p-3" data-menu-usuario>
        <div class="flex items-center gap-1">
            <x-dropdown align="left" width="w-48" direction="up">
                <x-slot name="trigger">
                    <button type="button" title="{{ Auth::user()->name }}"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2.5 transition duration-150 hover:bg-neutral-100 focus:bg-neutral-100 focus:outline-none">
                        <span class="min-w-0 flex-1 text-start">
                            <span class="block truncate text-sm font-medium text-neutral-800">{{ Auth::user()->name }}</span>
                            <span class="block truncate text-xs text-neutral-500">{{ Auth::user()->email }}</span>
                        </span>
                        <x-icon.chevron-down class="h-4 w-4 shrink-0 text-neutral-400" />
                    </button>
                </x-slot>

                <x-slot name="content">
                    <x-dropdown-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-dropdown-link>

                    {{-- Ítems del área de cuenta (ej. Configuración): links
                         separados de Perfil a propósito (pedido del dueño
                         24-07) — son cosas distintas: autoservicio del
                         usuario vs. parámetros del negocio. --}}
                    @foreach ($cuenta as $item)
                        <x-dropdown-link :href="route($item['route'])">
                            {{ $item['label'] }}
                        </x-dropdown-link>
                    @endforeach

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-dropdown-link>
                    </form>
                </x-slot>
            </x-dropdown>
        </div>
    </div>
</aside>
