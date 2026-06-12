<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Saludo --}}
            <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="p-6 text-neutral-600">
                    <p>{{ __("You're logged in!") }} Bienvenido {{ explode(' ', auth()->user()->name)[0] }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-sm text-neutral-500">Tu rol:</span>
                        @forelse (auth()->user()->roles as $role)
                            <x-badge>{{ \Illuminate\Support\Str::headline($role->name) }}</x-badge>
                        @empty
                            <span class="text-xs text-neutral-400">sin rol</span>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- CTA del operario: su pantalla de trabajo, a un clic --}}
            @can('report production')
                <div class="dg-enter rounded-2xl border border-brand-100 bg-brand-50 p-6 shadow-sm sm:flex sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-neutral-900">Tu reporte de producción</h3>
                        <p class="mt-1 text-sm text-neutral-600">Registra o revisa el soplado de hoy.</p>
                    </div>
                    <div class="mt-4 sm:mt-0">
                        <x-button-link :href="route('produccion.mi.index')">Ir a Mi producción</x-button-link>
                    </div>
                </div>
            @endcan

            {{-- Indicadores accionables --}}
            @if (count($indicadores))
                <div class="dg-enter grid grid-cols-2 gap-4 lg:grid-cols-4">
                    @foreach ($indicadores as $ind)
                        <x-stat-card :label="$ind['label']" :valor="$ind['valor']" :href="$ind['href']" :alerta="$ind['alerta']" />
                    @endforeach
                </div>
            @endif

            {{-- Accesos rápidos por área (mismos grupos que la navegación) --}}
            @foreach ($accesos as $grupo => $items)
                <div class="dg-enter">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $grupo }}</h3>
                    <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($items as $item)
                            <a href="{{ $item['href'] }}"
                                class="block rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm transition duration-150 hover:border-neutral-300 hover:shadow active:scale-[0.98]">
                                <p class="font-medium text-neutral-900">{{ $item['label'] }}</p>
                                <p class="mt-1 text-sm text-neutral-500">{{ $item['desc'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

        </div>
    </div>
</x-app-layout>
