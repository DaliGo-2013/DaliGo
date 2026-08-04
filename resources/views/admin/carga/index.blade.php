{{--
    Simulador de carga (LOGÍSTICA). Responde DOS preguntas distintas:
    · «¿Cuánto entra en tal camión?»  — cupo máximo de un producto.
    · «¿Cabe esta carga?»             — carga mixta: varios productos con cantidad
                                        (el caso textual del pedido del dueño:
                                        200 botellones + 20 cajas + 10 dispensadores).

    El visor 3D NO usa librerías: la silueta del camión y los bultos son prismas
    proyectados a mano sobre un <canvas>, derivados de las medidas. No hay ningún
    modelo 3D que mantener, y no entra ni un byte de dependencia al bundle.

    Los COLORES dentro del lienzo son datos (distinguen un producto de otro y la
    leyenda los traduce) — excepción sancionada tipo D-013; fuera del canvas rige
    la paleta de 4.
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
            @php
                $bultosJson = $bultos->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre, 'unidades' => $b->unidades])->values();
                $lineasIniciales = $lineasSel->isNotEmpty()
                    ? $lineasSel
                    : collect([['tipo' => $bultos->first()?->id, 'cantidad' => 100]]);
            @endphp

            <div x-data="{
                    modo: '{{ $mixta !== null ? 'mixta' : 'maximo' }}',
                    lineas: {{ $lineasIniciales->toJson() }},
                    bultos: {{ $bultosJson->toJson() }},
                    agregar() { if (this.lineas.length < 8) this.lineas.push({ tipo: this.bultos[0]?.id, cantidad: 10 }); },
                    quitar(i) { this.lineas.splice(i, 1); },
                 }" class="space-y-6">

                {{-- Las dos preguntas, como conmutador --}}
                <div class="inline-flex rounded-xl border border-neutral-200 bg-white p-1 shadow-sm" role="tablist">
                    <button type="button" @click="modo = 'maximo'" role="tab" :aria-selected="modo === 'maximo'"
                            :class="modo === 'maximo' ? 'bg-brand-600 text-white shadow-sm' : 'text-neutral-600 hover:text-neutral-900'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition duration-150">
                        ¿Cuánto entra?
                    </button>
                    <button type="button" @click="modo = 'mixta'" role="tab" :aria-selected="modo === 'mixta'"
                            :class="modo === 'mixta' ? 'bg-brand-600 text-white shadow-sm' : 'text-neutral-600 hover:text-neutral-900'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition duration-150">
                        ¿Cabe esta carga?
                    </button>
                </div>

                {{-- MODO 1 · cupo máximo de un producto. El x-cloak va en el form
                     del modo INACTIVO según lo que respondió el servidor: sin él,
                     el form del otro modo destella hasta que Alpine arranca. --}}
                <form x-show="modo === 'maximo'" @if ($mixta !== null) x-cloak @endif
                      method="GET" action="{{ route('admin.carga.index') }}"
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

                {{-- MODO 2 · carga mixta: armá la carga producto por producto --}}
                <form x-show="modo === 'mixta'" @if ($mixta === null) x-cloak @endif
                      method="GET" action="{{ route('admin.carga.index') }}"
                      class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <div class="sm:max-w-md">
                        <x-input-label for="vehiculo_id_mixta" value="Camión" />
                        <x-select id="vehiculo_id_mixta" name="vehiculo_id" class="mt-1.5">
                            @foreach ($vehiculos as $v)
                                <option value="{{ $v->id }}" @selected($vehiculo?->id === $v->id)>
                                    {{ $v->alias ?: trim($v->marca.' '.$v->modelo) ?: $v->ppu }}
                                    — {{ number_format($v->largo_util_cm / 100, 2, ',', '.') }} × {{ number_format($v->ancho_util_cm / 100, 2, ',', '.') }} × {{ number_format($v->alto_util_cm / 100, 2, ',', '.') }} m
                                </option>
                            @endforeach
                        </x-select>
                    </div>

                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">La carga</p>
                        <div class="mt-2 space-y-2">
                            <template x-for="(linea, i) in lineas" :key="i">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <select :name="`lineas[${i}][tipo]`" x-model.number="linea.tipo"
                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base sm:text-sm text-neutral-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 sm:flex-1">
                                        <template x-for="b in bultos" :key="b.id">
                                            <option :value="b.id" x-text="b.nombre" :selected="b.id === linea.tipo"></option>
                                        </template>
                                    </select>
                                    <div class="flex items-center gap-2">
                                        <input type="number" :name="`lineas[${i}][cantidad]`" x-model.number="linea.cantidad"
                                               min="1" max="100000" inputmode="numeric" required
                                               class="block w-32 rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-base sm:text-sm text-neutral-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                        <span class="w-16 text-xs text-neutral-400"
                                              x-text="(bultos.find(b => b.id === linea.tipo)?.unidades ?? 1) > 1 ? 'unidades' : 'bultos'"></span>
                                        <button type="button" @click="quitar(i)" x-show="lineas.length > 1"
                                                class="rounded-lg p-2 text-neutral-400 transition duration-150 hover:bg-red-50 hover:text-red-600"
                                                title="Quitar producto">
                                            <x-icon.trash class="h-4 w-4" /><span class="sr-only">Quitar producto</span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <x-secondary-button type="button" @click="agregar()" x-show="lineas.length < 8">
                                <x-icon.plus class="h-4 w-4" />
                                Agregar producto
                            </x-secondary-button>
                            <x-primary-button>Calcular la carga</x-primary-button>
                        </div>
                        <p class="mt-2 text-xs text-neutral-400">
                            Las cantidades van en unidades sueltas (botellones, cajas, equipos). Los botellones
                            viajan en bolsas de 5: se completa la bolsa.
                        </p>
                    </div>
                </form>

                {{-- RESULTADO · carga mixta --}}
                @if ($mixta !== null)
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="space-y-4">
                            {{-- El veredicto, primero y sin rodeos --}}
                            @if ($mixta['cabeTodo'])
                                <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                                    <p class="text-2xl font-semibold text-brand-600">Cabe todo ✓</p>
                                    <p class="mt-1 text-sm text-neutral-500">
                                        La carga completa entra en {{ $escena['vehiculo']['nombre'] }}.
                                    </p>
                                </div>
                            @else
                                <div class="rounded-2xl border-2 border-red-300 bg-red-50 p-4 sm:p-5">
                                    <p class="text-2xl font-semibold text-red-700">No cabe todo</p>
                                    <p class="mt-1 text-sm text-red-700">
                                        Abajo está qué queda afuera y por qué — con eso se negocia.
                                    </p>
                                </div>
                            @endif

                            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                                <div class="text-sm">
                                    <div class="flex justify-between py-1">
                                        <span class="text-neutral-500">Ocupación</span>
                                        <span class="font-medium tabular-nums text-neutral-900">{{ round($mixta['resultado']['ocupacion'] * 100) }}%</span>
                                    </div>
                                    <div class="mb-2 h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                        <div class="h-1.5 rounded-full bg-brand-600" style="width: {{ min(100, round($mixta['resultado']['ocupacion'] * 100)) }}%"></div>
                                    </div>
                                    <div class="flex justify-between py-1">
                                        <span class="text-neutral-500">Volumen</span>
                                        <span class="font-medium tabular-nums text-neutral-900">{{ number_format($mixta['resultado']['volumen_ocupado_m3'], 1, ',', '.') }} de {{ number_format($mixta['resultado']['volumen_vehiculo_m3'], 1, ',', '.') }} m³</span>
                                    </div>
                                    @if ($mixta['resultado']['peso_kg'] > 0)
                                        <div class="flex justify-between py-1">
                                            <span class="text-neutral-500">Peso</span>
                                            <span class="font-medium tabular-nums text-neutral-900">
                                                {{ number_format($mixta['resultado']['peso_kg'], 0, ',', '.') }} kg{{ $vehiculo->capacidad_carga_kg ? ' de '.number_format($vehiculo->capacidad_carga_kg, 0, ',', '.') : '' }}
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                @if ($mixta['peligrosas'] !== [])
                                    <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                                        <strong>Mercancía peligrosa en la carga
                                            ({{ collect($mixta['peligrosas'])->map(fn ($p) => $p->peligrosa_codigo ?: $p->nombre)->implode(', ') }}).</strong>
                                        El cálculo es solo de espacio: el transporte tiene reglas propias de rotulado y
                                        segregación. Que quepa no significa que se pueda cargar así.
                                    </p>
                                @endif

                                <p class="mt-4 text-xs leading-relaxed text-neutral-400">
                                    Acomodo por zonas, como se estiba de verdad: lo grande al fondo, sin apilar un
                                    producto arriba de otro. Capacidad práctica, no promesa.
                                </p>
                            </div>
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

                    {{-- El detalle por producto: qué entra, qué queda afuera y POR QUÉ.
                         El color de cada fila es la leyenda del visor. --}}
                    <x-list-card title="La carga, producto por producto" :count="count($mixta['lineas'])"
                                 :countLabel="\Illuminate\Support\Str::plural('producto', count($mixta['lineas']))">
                        @foreach ($mixta['lineas'] as $i => $fila)
                            @php
                                $rgb = \App\Http\Controllers\Admin\SimuladorCargaController::COLORES_3D[$i % count(\App\Http\Controllers\Admin\SimuladorCargaController::COLORES_3D)];
                                $pendientes = $fila['pedidas_unidades'] - $fila['cargadas_unidades'];
                                $motivoTexto = [
                                    'espacio' => 'no queda espacio con el resto de la carga',
                                    'peso' => 'se pasa de la carga máxima en kilos',
                                    'largo' => 'no entra por el largo de la caja',
                                    'ancho' => 'no entra por el ancho de la caja',
                                    'alto' => 'no entra por la altura de la caja',
                                ][$fila['motivo']] ?? null;
                            @endphp
                            <x-list-row>
                                <x-slot name="leading">
                                    <span class="mt-1 block h-3.5 w-3.5 rounded"
                                          style="background: rgb({{ implode(',', $rgb) }})" aria-hidden="true"></span>
                                </x-slot>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-neutral-900">{{ $fila['modelo']->nombre }}</p>
                                    @if ($fila['motivo'] === null)
                                        <x-badge variant="brand">Completo</x-badge>
                                    @else
                                        <x-badge variant="danger">Queda afuera</x-badge>
                                    @endif
                                </div>
                                <p class="text-sm text-neutral-500">
                                    Cargadas <span class="font-medium tabular-nums text-neutral-900">{{ number_format($fila['cargadas_unidades'], 0, ',', '.') }}</span>
                                    de {{ number_format($fila['pedidas_unidades'], 0, ',', '.') }}
                                    @if ($fila['modelo']->unidades > 1)
                                        ({{ $fila['bultos_colocados'] }} {{ \Illuminate\Support\Str::plural('bolsa', $fila['bultos_colocados']) }})
                                    @endif
                                    @if ($motivoTexto)
                                        · <span class="text-red-600">quedan {{ number_format($pendientes, 0, ',', '.') }} afuera: {{ $motivoTexto }}</span>
                                    @endif
                                </p>
                            </x-list-row>
                        @endforeach
                    </x-list-card>
                @endif

                {{-- RESULTADO · cupo máximo (igual que siempre) --}}
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

                    <div x-show="modo === 'maximo'" class="grid gap-4 lg:grid-cols-3">
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
                @endif
            </div>

            {{-- Los datos de la escena viajan como JSON inerte; el visor los lee.
                 El layout no tiene @stack('scripts') y vite tiene una sola entrada,
                 asi que el modulo entra por import dinamico desde app.js. --}}
            @if ($escena)
                <script type="application/json" id="carga3d-datos">@json($escena)</script>
            @endif
        @endif
    </div>
</x-app-layout>
