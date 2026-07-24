{{-- Topbar V4: hamburguesa (solo móvil), título del módulo activo derivado
     de MenuPrincipal, campanita M15 y avatar con menú de usuario. La campana
     móvil va SIEMPRE visible en la barra (hallazgo QA 14-07; su aria-label
     exacto es contrato de CampanitaTest) — corrige el gap del boceto v4. --}}
<header class="flex h-14 items-center gap-2 border-b border-neutral-200 bg-white px-4 lg:px-6">
    <button type="button" @click="menuAbierto = true" :aria-expanded="menuAbierto"
            class="-ms-2 inline-flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none lg:hidden">
        <x-icon.bars-3 class="h-6 w-6" />
        <span class="sr-only">Abrir menú</span>
    </button>

    <span class="flex items-center gap-1.5 text-sm font-medium text-neutral-900">
        {{ $activo['label'] ?? config('app.name', 'DaliGo') }}
        @if ($badgeActivo > 0)
            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded bg-brand-600 px-1 text-xs font-semibold text-white"
                  title="{{ str_replace(':n', $badgeActivo, $activo['badge_title'] ?? ':n') }}">{{ $badgeActivo }}</span>
        @endif
    </span>

    <div class="ms-auto flex items-center gap-1">
        {{-- Campanita dropdown (desktop) — el partial M15 se reusa sin cambios. --}}
        <div class="hidden lg:block">
            @include('layouts.partials.campanita', ['dgNoLeidas' => $noLeidas, 'dgConteo' => $conteo])
        </div>

        {{-- Campana móvil: link directo a la bandeja (un dropdown en 375px
             tapa la pantalla y la página ya existe). --}}
        <a href="{{ route('notificaciones.index') }}"
            aria-label="Notificaciones{{ $conteo > 0 ? ' ('.$conteo.' sin leer)' : '' }}"
            class="relative inline-flex items-center justify-center rounded-md p-2 text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none lg:hidden">
            <x-icon.bell class="h-6 w-6" />
            @if ($conteo > 0)
                <span class="absolute right-0 top-0 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-600 px-1 text-xs font-semibold tabular-nums text-white">{{ $conteo > 9 ? '9+' : $conteo }}</span>
            @endif
            <span class="sr-only">Notificaciones</span>
        </a>

        {{-- Usuario: avatar con iniciales → Perfil / Cerrar sesión (todos los
             anchos; reemplaza el bloque de perfil del hamburguesa viejo). --}}
        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button type="button" title="{{ Auth::user()->name }}"
                        class="inline-flex items-center justify-center rounded-full p-1.5 transition duration-150 hover:bg-neutral-100 focus:bg-neutral-100 focus:outline-none">
                    <x-avatar class="h-8 w-8 text-xs">{{ $iniciales }}</x-avatar>
                    <span class="sr-only">Menú de usuario</span>
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="border-b border-neutral-100 px-4 py-2">
                    <div class="truncate text-sm font-medium text-neutral-800">{{ Auth::user()->name }}</div>
                    <div class="truncate text-xs text-neutral-500">{{ Auth::user()->email }}</div>
                </div>

                <x-dropdown-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-dropdown-link>

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
</header>
