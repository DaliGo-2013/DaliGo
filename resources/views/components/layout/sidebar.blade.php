{{-- Sidebar V4 (menú Talana): UN SOLO <aside> es la sidebar fija en desktop
     (lg:, 264px) y el drawer off-canvas izquierdo en móvil (300px) — así el
     menú existe UNA vez y no puede driftear entre breakpoints (el nav viejo
     ya había divergido). El estado `menuAbierto` vive en el x-data del shell
     (layouts/app.blade.php). Anti-flash pre-Alpine: la clase estática
     max-lg:-translate-x-full oculta el drawer desde el primer paint; Alpine
     solo la RETIRA al abrir (nunca hay dos utilidades translate en pugna). --}}
<aside
    class="fixed inset-y-0 left-0 z-40 w-[300px] max-lg:-translate-x-full overflow-y-auto border-r border-neutral-200 bg-white max-lg:transition-transform max-lg:duration-150 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:w-[264px] lg:shrink-0"
    :class="{ 'max-lg:-translate-x-full': ! menuAbierto }">

    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <x-application-logo class="h-9 w-9 text-base" />
            <span class="text-lg font-semibold tracking-tight text-neutral-900">DaliGo</span>
        </a>
        <button type="button" @click="menuAbierto = false"
                class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-neutral-500 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 focus:bg-neutral-100 focus:text-neutral-700 focus:outline-none lg:hidden">
            <x-icon.x-mark class="h-5 w-5" />
            <span class="sr-only">Cerrar menú</span>
        </button>
    </div>

    <nav class="space-y-1 px-3 py-4" aria-label="Menú principal">
        @foreach ($modulos as $key => $modulo)
            @isset($modulo['items'])
                <x-sidebar-group :modulo="$modulo" :clave="$key"
                    :abierto="($activo['key'] ?? null) === $key"
                    :badge="isset($modulo['badge']) ? ($badges[$modulo['badge']] ?? 0) : 0">
                    @foreach ($modulo['items'] as $item)
                        <x-sidebar-item :item="$item" :activo="request()->routeIs(...$item['activo'])" />
                    @endforeach
                </x-sidebar-group>
            @else
                {{-- Link directo de primer nivel (Dashboard / Mi producción /
                     Aprobaciones): el acceso 1-clic del operario es a propósito. --}}
                @php $esActivo = request()->routeIs(...$modulo['activo']); @endphp
                <a href="{{ route($modulo['route']) }}"@if ($esActivo) aria-current="page"@endif
                   class="{{ $esActivo
                       ? 'flex items-center gap-3 rounded-lg bg-brand-50 px-3 py-3 text-sm font-medium text-brand-700 lg:py-2.5'
                       : 'flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-neutral-900 transition duration-150 hover:bg-neutral-50 lg:py-2.5' }}">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                        <x-dynamic-component :component="'icon.' . $modulo['icon']" class="h-5 w-5" />
                    </span>
                    {{ $modulo['label'] }}
                </a>
            @endisset
        @endforeach
    </nav>
</aside>
