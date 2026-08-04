{{--
    Simulador de carga (LOGÍSTICA). Responde «¿cuánto entra en tal camión?» antes
    de que el vendedor prometa.

    El visor 3D NO usa librerías: la silueta del camión y los bultos son prismas
    proyectados a mano sobre un <canvas>, derivados de las medidas. No hay ningún
    modelo 3D que mantener, y no entra ni un byte de dependencia al bundle.
--}}
<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Simulador de carga"
                       subtitle="¿Cuánto entra en cada camión? Sin adivinar." />
    </x-slot>

    <div class="space-y-6 py-6">

        @if ($vehiculos->isEmpty())
            <x-list-card title="Simulador de carga">
                <li class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-neutral-900">Falta medir la caja de los camiones</p>
                    <p class="mx-auto mt-1 max-w-lg text-sm text-neutral-500">
                        El simulador necesita el <strong>largo, ancho y alto útiles</strong> de la caja de carga —
                        medidos por dentro, no los del folleto. Se cargan en la ficha de cada vehículo.
                        @if ($sinMedidas > 0)
                            Hay {{ $sinMedidas }} {{ \Illuminate\Support\Str::plural('vehículo', $sinMedidas) }} sin esas medidas.
                        @endif
                    </p>
                    <p class="mt-3 text-xs">
                        <a href="{{ route('admin.vehiculos.index') }}" class="font-medium text-brand-600 hover:text-brand-700">
                            Ir a la flota →
                        </a>
                    </p>
                </li>
            </x-list-card>
        @else
            {{-- Selección --}}
            <form method="GET" action="{{ route('admin.carga.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:flex-row sm:items-end sm:p-4">
                <div class="flex-1">
                    <x-input-label for="vehiculo_id" value="Camión" />
                    <x-select id="vehiculo_id" name="vehiculo_id" class="mt-1.5" onchange="this.form.submit()">
                        @foreach ($vehiculos as $v)
                            <option value="{{ $v->id }}" @selected($vehiculo?->id === $v->id)>
                                {{ $v->alias ?: trim($v->marca.' '.$v->modelo) ?: $v->ppu }}
                                — {{ number_format($v->largo_util_cm / 100, 2, ',', '.') }} × {{ number_format($v->ancho_util_cm / 100, 2, ',', '.') }} × {{ number_format($v->alto_util_cm / 100, 2, ',', '.') }} m
                            </option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1">
                    <x-input-label for="tipo_bulto_id" value="Qué se carga" />
                    <x-select id="tipo_bulto_id" name="tipo_bulto_id" class="mt-1.5" onchange="this.form.submit()">
                        @foreach ($bultos as $b)
                            <option value="{{ $b->id }}" @selected($bulto?->id === $b->id)>{{ $b->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div><x-primary-button>Calcular</x-primary-button></div>
            </form>

            @if ($resultado)
                @php
                    $lim = [
                        'largo' => 'el largo de la caja',
                        'ancho' => 'el ancho de la caja',
                        'alto' => 'la altura (o el tope de apilado)',
                        'peso' => 'la carga máxima en kilos',
                        'ninguno' => '—',
                    ][$resultado['limite']] ?? '—';
                @endphp

                <div class="grid gap-4 lg:grid-cols-3">
                    {{-- Resultado --}}
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Entran</p>
                        <p class="mt-1 text-4xl font-semibold text-neutral-900 tabular-nums">{{ number_format($resultado['bultos'], 0, ',', '.') }}</p>
                        <p class="text-sm text-neutral-500">{{ \Illuminate\Support\Str::plural('bulto', $resultado['bultos']) }}</p>

                        @if ($bulto->unidades > 1)
                            <p class="mt-3 text-2xl font-semibold text-brand-600 tabular-nums">
                                {{ number_format($resultado['unidades'], 0, ',', '.') }}
                            </p>
                            <p class="text-sm text-neutral-500">unidades ({{ $bulto->unidades }} por bulto)</p>
                        @endif

                        <div class="mt-4 border-t border-neutral-100 pt-3 text-sm">
                            <div class="flex justify-between py-1">
                                <span class="text-neutral-500">Se agota primero</span>
                                <span class="font-medium text-neutral-900">{{ $lim }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-neutral-500">Rejilla</span>
                                <span class="font-medium tabular-nums text-neutral-900">{{ $resultado['rejilla']['largo'] }} × {{ $resultado['rejilla']['ancho'] }} × {{ $resultado['rejilla']['alto'] }}</span>
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-neutral-500">Ocupación</span>
                                <span class="font-medium tabular-nums text-neutral-900">{{ round($resultado['ocupacion'] * 100) }}%</span>
                            </div>
                            @if ($resultado['peso_kg'] > 0)
                                <div class="flex justify-between py-1">
                                    <span class="text-neutral-500">Peso</span>
                                    <span class="font-medium tabular-nums text-neutral-900">{{ number_format($resultado['peso_kg'], 0, ',', '.') }} kg</span>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-200">
                            <div class="h-1.5 rounded-full bg-brand-600" style="width: {{ min(100, round($resultado['ocupacion'] * 100)) }}%"></div>
                        </div>

                        @if ($bulto->peligrosa)
                            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                                <strong>Mercancía peligrosa{{ $bulto->peligrosa_codigo ? ' ('.$bulto->peligrosa_codigo.')' : '' }}.</strong>
                                El cupo es solo de espacio: el transporte tiene reglas propias de rotulado y segregación.
                                Que quepa no significa que se pueda cargar así.
                            </p>
                        @endif

                        <p class="mt-4 text-xs leading-relaxed text-neutral-400">
                            Capacidad práctica, no promesa: la estiba real no es una rejilla perfecta (amarres, hilera del
                            piso girada). Se calibra contando una carga real.
                        </p>
                    </div>

                    {{-- Visor 3D --}}
                    <div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm lg:col-span-2">
                        <canvas id="carga3d" width="1240" height="720" class="block w-full cursor-grab"></canvas>
                        <div class="absolute left-4 top-3 text-xs font-medium text-neutral-500">
                            {{ $escena['vehiculo']['nombre'] }} · arrastrá para girar
                        </div>
                        <div class="absolute bottom-3 left-4 flex items-center gap-3 text-xs">
                            <button type="button" id="carga3dPlay"
                                    class="rounded-lg bg-brand-600 px-2.5 py-1 font-semibold text-white transition hover:bg-brand-700">▶ Cargar</button>
                            <span class="text-neutral-500"><span id="carga3dN">0</span> de {{ $escena['tope'] }}</span>
                        </div>
                    </div>
                </div>

                {{-- Los datos de la escena viajan como JSON inerte; el visor los lee.
                     El layout no tiene @stack('scripts') y vite tiene una sola entrada,
                     asi que el modulo entra por import dinamico desde app.js. --}}
                <script type="application/json" id="carga3d-datos">@json($escena)</script>
            @endif
        @endif
    </div>
</x-app-layout>
