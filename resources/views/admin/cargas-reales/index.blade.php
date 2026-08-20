{{--
    CARGAS REALES · lo que entró de verdad contra lo que el simulador dijo.

    La pantalla se lee de arriba abajo como se usa: primero el FACTOR por combinación
    —que es la conclusión y lo que alguien viene a mirar—, después el formulario para
    sumar una carga, y al final el historial fila por fila.

    El orden importa: si el formulario fuera primero, esto sería un cuaderno de anotar.
    Con el resumen arriba es una herramienta que responde algo.
--}}
<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Cargas reales"
                       subtitle="Lo que entró de verdad, contra lo que dijo el simulador." />
    </x-slot>

    <div class="space-y-6 py-6">

        <x-status-alert :status="session('status')" />

        @include('admin.carga._tabs')

        {{-- ① EL FACTOR, que es la conclusión --}}
        @if ($resumen !== [])
            <x-list-card title="Factor por combinación" :count="count($resumen)"
                         :countLabel="\Illuminate\Support\Str::plural('combinación', count($resumen))">
                @foreach ($resumen as $r)
                    @php
                        $pct = round($r['factor'] * 100);
                        // Por encima de 1 el simulador se quedó CORTO, y eso no es un
                        // error de tipeo: es la señal más valiosa de la tabla, porque
                        // significa que alguna medida del catálogo está corta. Fue
                        // exactamente el caso del HD35 el 11-08.
                        $seQuedoCorto = $r['factor'] > 1;
                    @endphp
                    <x-list-row>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-neutral-900">{{ $r['bulto'] }}</p>
                            <x-badge variant="neutral">{{ $r['camion'] }}</x-badge>
                            @if ($r['estiba'] !== 'auto')
                                <x-badge>{{ \App\Models\TipoBulto::ESTIBAS_ELEGIBLES[$r['estiba']] ?? $r['estiba'] }}</x-badge>
                            @endif
                        </div>
                        <p class="text-sm text-neutral-500">
                            Entró el
                            <span class="font-medium tabular-nums {{ $seQuedoCorto ? 'text-red-600' : 'text-neutral-900' }}">{{ $pct }}%</span>
                            de lo prometido, sobre {{ $r['veces'] }} {{ \Illuminate\Support\Str::plural('carga', $r['veces']) }}
                            @if (! $r['confiable'])
                                · <span class="text-neutral-400">todavía no alcanza para promediar
                                    ({{ \App\Http\Controllers\Admin\CargaRealController::MINIMO_PARA_PROMEDIAR }} mínimo)</span>
                            @endif
                            @if ($seQuedoCorto)
                                · <span class="text-red-600">entró MÁS de lo calculado: alguna medida del catálogo puede estar corta</span>
                            @endif
                        </p>
                    </x-list-row>
                @endforeach
            </x-list-card>
        @endif

        {{-- ② ANOTAR UNA CARGA --}}
        <x-seccion titulo="Anotar una carga que ya se hizo">
            <form method="POST" action="{{ route('admin.cargas-reales.store') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="fecha" value="Fecha de la carga" />
                        <x-text-input id="fecha" name="fecha" type="date" class="mt-1.5 w-full"
                                      :value="old('fecha', now()->toDateString())" max="{{ now()->toDateString() }}" required />
                        <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="camion_simulacion_id" value="Camión" />
                        <x-select id="camion_simulacion_id" name="camion_simulacion_id" class="mt-1.5 w-full" required>
                            @foreach ($camiones as $c)
                                <option value="{{ $c->id }}" @selected(old('camion_simulacion_id') == $c->id)>{{ $c->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('camion_simulacion_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tipo_bulto_id" value="Producto" />
                        <x-select id="tipo_bulto_id" name="tipo_bulto_id" class="mt-1.5 w-full" required>
                            @foreach ($bultos as $b)
                                <option value="{{ $b->id }}" @selected(old('tipo_bulto_id') == $b->id)>{{ $b->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('tipo_bulto_id')" class="mt-1" />
                    </div>
                    <div>
                        {{-- La estiba NO es opcional acá aunque en el simulador tenga
                             default: un «entraron 400» sin decir cómo iba la bolsa no se
                             puede comparar contra nada. En el HD35 la misma bolsa da 420
                             de pie y 360 acostada. --}}
                        <x-input-label for="estiba" value="Cómo viajaba" />
                        <x-select id="estiba" name="estiba" class="mt-1.5 w-full" required>
                            @foreach (\App\Models\TipoBulto::ESTIBAS_ELEGIBLES as $clave => $nombre)
                                <option value="{{ $clave }}" @selected(old('estiba', 'auto') === $clave)>{{ $nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('estiba')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="simulado" value="Lo que dijo el simulador" />
                        <x-text-input id="simulado" name="simulado" type="number" min="1" max="100000"
                                      class="mt-1.5 w-full tabular-nums" inputmode="numeric"
                                      :value="old('simulado')" required />
                        <x-input-hint>En unidades sueltas: botellones, cajas, equipos.</x-input-hint>
                        <x-input-error :messages="$errors->get('simulado')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="real" value="Lo que entró de verdad" />
                        <x-text-input id="real" name="real" type="number" min="0" max="100000"
                                      class="mt-1.5 w-full tabular-nums" inputmode="numeric"
                                      :value="old('real')" required />
                        <x-input-hint>Contado al terminar de cargar, no estimado.</x-input-hint>
                        <x-input-error :messages="$errors->get('real')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="observaciones" value="Qué pasó (opcional)" />
                    <x-textarea id="observaciones" name="observaciones" rows="2" maxlength="300"
                                class="mt-1.5 w-full"
                                placeholder="Ej.: iba media carga de pie y media acostada; o quedó lugar para pasar al fondo.">{{ old('observaciones') }}</x-textarea>
                    <x-input-hint>
                        Lo más útil de todo cuando los números no cuadran: explica el POR QUÉ, que el
                        número solo nunca dice.
                    </x-input-hint>
                    <x-input-error :messages="$errors->get('observaciones')" class="mt-1" />
                </div>

                <x-form-footer>
                    <x-primary-button>Anotar la carga</x-primary-button>
                </x-form-footer>
            </form>
        </x-seccion>

        {{-- ③ EL HISTORIAL --}}
        <x-list-card title="Historial" :count="$cargas->count()"
                     :countLabel="\Illuminate\Support\Str::plural('carga', $cargas->count())">
            @forelse ($cargas as $carga)
                @php
                    $f = $carga->factor();
                    $dif = $carga->diferencia();
                @endphp
                <x-list-row>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium text-neutral-900">{{ $carga->bulto?->nombre ?? '—' }}</p>
                        <x-badge variant="neutral">{{ $carga->camion?->nombre ?? '—' }}</x-badge>
                        @if ($carga->estiba !== 'auto')
                            <x-badge>{{ \App\Models\TipoBulto::ESTIBAS_ELEGIBLES[$carga->estiba] ?? $carga->estiba }}</x-badge>
                        @endif
                    </div>
                    <p class="text-sm text-neutral-500">
                        {{ $carga->fecha->format('d/m/Y') }} ·
                        simulado <span class="font-medium tabular-nums text-neutral-900">{{ number_format($carga->simulado, 0, ',', '.') }}</span>,
                        entraron <span class="font-medium tabular-nums text-neutral-900">{{ number_format($carga->real, 0, ',', '.') }}</span>
                        @if ($f !== null)
                            · <span class="tabular-nums {{ $f > 1 ? 'text-red-600' : '' }}">{{ round($f * 100) }}%</span>
                            <span class="text-neutral-400">({{ $dif > 0 ? '+' : '' }}{{ number_format($dif, 0, ',', '.') }})</span>
                        @endif
                        @if ($carga->usuario)
                            · <span class="text-neutral-400">{{ $carga->usuario->name }}</span>
                        @endif
                    </p>
                    @if ($carga->observaciones)
                        <p class="mt-1 text-sm text-neutral-600">{{ $carga->observaciones }}</p>
                    @endif

                    <x-slot name="actions">
                        <form method="POST" action="{{ route('admin.cargas-reales.destroy', $carga) }}"
                              onsubmit="return confirm('¿Borrar esta carga del historial?')">
                            @csrf @method('DELETE')
                            <x-icon-button variant="danger" label="Borrar del historial" type="submit">
                                <x-icon.trash class="h-4 w-4" />
                            </x-icon-button>
                        </form>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    <p class="font-medium text-neutral-700">Todavía no hay ninguna carga anotada.</p>
                    <p class="mx-auto mt-1 max-w-lg">
                        El simulador calcula un TECHO: la estiba real tiene amarres, hileras giradas y
                        gente que necesita pasar. Cuánto queda por debajo no se deduce — se cuenta.
                        Anotá una carga y el factor aparece solo.
                    </p>
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
