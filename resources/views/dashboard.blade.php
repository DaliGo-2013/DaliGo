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

            {{-- ① Excepciones (M16-v1): solo lo que se desvía, con edad y destino --}}
            @if ($puedeVerExcepciones)
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-100 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Requiere tu atención</h3>
                    </div>
                    @if (count($excepciones))
                        <ul class="divide-y divide-neutral-100">
                            @foreach ($excepciones as $ex)
                                <li>
                                    <a href="{{ $ex['href'] }}" class="flex items-center gap-4 px-6 py-4 transition duration-150 hover:bg-neutral-50 active:scale-[0.98]">
                                        <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-full bg-brand-600 px-2 text-sm font-semibold tabular-nums text-white">{{ $ex['cantidad'] }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-medium text-neutral-900">{{ $ex['label'] }}</span>
                                            @if ($ex['edad'])
                                                <span class="block text-xs text-neutral-500">el más antiguo espera {{ $ex['edad'] }}</span>
                                            @endif
                                        </span>
                                        <x-icon.chevron-down class="h-4 w-4 shrink-0 -rotate-90 text-neutral-400" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        {{-- Andon en verde-sin-verde: lo normal se ve quieto (neutral) --}}
                        <p class="px-6 py-4 text-sm text-neutral-500">Operación al día — nada pendiente de tu lado.</p>
                    @endif
                </div>
            @endif

            {{-- ② Pulso del día: producción y taller (medida directa + contexto) --}}
            @if ($pulsoProduccion || $pulsoTaller)
                <div class="grid gap-6 {{ $pulsoProduccion && $pulsoTaller ? 'lg:grid-cols-2' : '' }}">
                    @if ($pulsoProduccion)
                        <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Producción · hoy</h3>
                                <a href="{{ $pulsoProduccion['href'] }}" class="text-xs font-medium text-brand-700 transition duration-150 hover:text-brand-600">Ver panel</a>
                            </div>
                            <p class="mt-3 text-3xl font-semibold tabular-nums text-neutral-900">
                                {{ number_format($pulsoProduccion['producido'], 0, ',', '.') }}
                                <span class="text-base font-normal text-neutral-500">de {{ number_format($pulsoProduccion['asignadas'], 0, ',', '.') }} asignadas · {{ $pulsoProduccion['avance'] }}%</span>
                            </p>
                            {{-- Medida directa: una barra producido-vs-asignado --}}
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-2 rounded-full bg-brand-600" style="width: {{ min(100, $pulsoProduccion['avance']) }}%"></div>
                            </div>
                            <p class="mt-2 text-sm tabular-nums text-neutral-500">
                                Merma {{ $pulsoProduccion['merma_pct'] }}%@if (! is_null($pulsoProduccion['mermaProm7'])) <span class="text-neutral-400">· prom. 7 días {{ $pulsoProduccion['mermaProm7'] }}%</span>@endif
                                · Tasa 1ª {{ $pulsoProduccion['tasa1'] }}%
                            </p>
                            {{-- Mini-serie de 7 días (hoy destacado); altura por style, Tailwind purga clases dinámicas --}}
                            <div class="mt-4 flex h-12 items-end gap-1">
                                @foreach ($pulsoProduccion['serie'] as $dia)
                                    <div class="flex-1 rounded-t {{ $loop->last ? 'bg-brand-600' : 'bg-brand-200' }}"
                                        style="height: {{ max(4, $dia['pct']) }}%"
                                        title="{{ \Illuminate\Support\Carbon::parse($dia['fecha'])->format('d-m') }}: {{ number_format($dia['producido'], 0, ',', '.') }}"></div>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-neutral-400">Últimos 7 días · hoy destacado</p>
                        </div>
                    @endif

                    @if ($pulsoTaller)
                        <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                            <div class="flex items-center justify-between gap-4">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Taller · equipos activos</h3>
                                <a href="{{ $pulsoTaller['href'] }}" class="text-xs font-medium text-brand-700 transition duration-150 hover:text-brand-600">Ver taller</a>
                            </div>
                            <p class="mt-3 text-3xl font-semibold tabular-nums text-neutral-900">
                                {{ number_format($pulsoTaller['activos'], 0, ',', '.') }}
                                @if ($pulsoTaller['aging']['d30'] > 0)
                                    <span class="text-base font-normal text-neutral-500">— {{ $pulsoTaller['aging']['d30'] }} llevan 30+ días</span>
                                @endif
                            </p>
                            {{-- Antigüedad como barra segmentada: intensidades de un matiz (oscuro = más viejo) --}}
                            @php $totalActivos = max(1, $pulsoTaller['activos']); @endphp
                            <div class="mt-2 flex h-2 w-full overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-2 bg-neutral-300" style="width: {{ round($pulsoTaller['aging']['d0_7'] / $totalActivos * 100) }}%"></div>
                                <div class="h-2 bg-neutral-500" style="width: {{ round($pulsoTaller['aging']['d8_30'] / $totalActivos * 100) }}%"></div>
                                <div class="h-2 bg-neutral-800" style="width: {{ round($pulsoTaller['aging']['d30'] / $totalActivos * 100) }}%"></div>
                            </div>
                            <p class="mt-2 text-sm tabular-nums text-neutral-500">
                                {{ $pulsoTaller['aging']['d0_7'] }} de 0-7 días · {{ $pulsoTaller['aging']['d8_30'] }} de 8-30 · <span class="font-medium text-neutral-900">{{ $pulsoTaller['aging']['d30'] }} de 30+</span>
                            </p>
                            <p class="mt-3 text-sm tabular-nums text-neutral-500">
                                Última semana: entraron {{ $pulsoTaller['entradasSemana'] }} · salieron {{ $pulsoTaller['salidasSemana'] }}
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- ③ Zócalo: accesos directos compactos (bajan de jerarquía, no se botan) --}}
            @if (count($accesos))
                <div class="dg-enter">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Accesos directos</h3>
                    <div class="mt-3 space-y-3">
                        @foreach ($accesos as $grupo => $items)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="w-full text-xs text-neutral-400 sm:w-28 sm:shrink-0">{{ $grupo }}</span>
                                @foreach ($items as $item)
                                    <a href="{{ $item['href'] }}" title="{{ $item['desc'] }}"
                                        class="inline-flex rounded-full bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 ring-1 ring-inset ring-neutral-200 transition duration-150 hover:bg-neutral-50 hover:text-neutral-900 active:scale-[0.98]">{{ $item['label'] }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
