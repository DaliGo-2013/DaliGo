{{--
    Simulador de carga (LOGÍSTICA). Responde DOS preguntas distintas:
    · «¿Cuánto entra en tal camión?»  — cupo máximo de un producto.
    · «¿Cabe esta carga?»             — carga mixta: varios productos con cantidad
                                        (el caso textual del pedido del dueño:
                                        200 botellones + 20 cajas + 10 dispensadores).

    ORDEN DE LA PANTALLA (pedido del dueño 05-08): el camión en 3D ARRIBA y a todo el
    ancho, debajo dos columnas —el camión en números | el veredicto y el detalle— y el
    formulario al final. El visor iba antes en una grilla al lado de la columna de
    datos: como esa columna es más alta, el recuadro se estiraba y el lienzo, que tiene
    proporción fija, quedaba pegado arriba con un hueco blanco abajo. A todo el ancho no
    hay nada que lo estire.

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

        @if ($camiones->isEmpty())
            {{-- Solo puede pasar si alguien desactivó todos los camiones del
                 catálogo: el seeder los crea en cada deploy. --}}
            <x-list-card title="Simulador de carga">
                <li class="px-6 py-10 text-center">
                    <p class="text-sm font-medium text-neutral-900">No hay camiones de simulación activos</p>
                    <p class="mx-auto mt-1 max-w-lg text-sm text-neutral-500">
                        El catálogo del simulador viene sembrado con el próximo deploy
                        (Contenedor 40', HINO 500 y HD35). Si esto aparece, se
                        desactivaron todos: hay que reactivar alguno.
                    </p>
                </li>
            </x-list-card>
        @else
            @php
                // «acuesta»: si a este producto tiene sentido ofrecerle la estiba. Solo
                // los de orientación fija — al resto el motor ya le prueba las 6
                // rotaciones y elige la mejor.
                //
                // Comentario de PHP y no de Blade: dentro de @php un {{-- --}} NO se
                // procesa y sale tal cual al PHP compilado.
                $bultosJson = $bultos->map(fn ($b) => [
                    'id' => $b->id, 'nombre' => $b->nombre, 'unidades' => $b->unidades,
                    'acuesta' => $b->puedeAcostarse(),
                ])->values();
                $lineasIniciales = $lineasSel->isNotEmpty()
                    ? $lineasSel
                    : collect([['tipo' => $bultos->first()?->id, 'cantidad' => 100, 'estiba' => 'pie']]);
            @endphp

            <div x-data="{
                    modo: '{{ $mixta !== null ? 'mixta' : ($enPallet !== null ? 'pallet' : 'maximo') }}',
                    lineas: {{ $lineasIniciales->toJson() }},
                    bultos: {{ $bultosJson->toJson() }},
                    agregar() { if (this.lineas.length < 8) this.lineas.push({ tipo: this.bultos[0]?.id, cantidad: 10, estiba: 'pie' }); },
                    acuesta(tipo) { return this.bultos.find(b => b.id === tipo)?.acuesta ?? false; },
                    quitar(i) { this.lineas.splice(i, 1); },
                    // Mover un producto en la lista es la forma honesta de «mover la carga»:
                    // con el orden en «Como armé la lista», el primero va al FONDO. Se
                    // recalcula todo, así que el acomodo sigue siendo uno que el motor
                    // verificó — a diferencia de arrastrar bloques a mano.
                    mover(i, d) {
                        const j = i + d;
                        if (j < 0 || j >= this.lineas.length) return;
                        const t = this.lineas[i]; this.lineas[i] = this.lineas[j]; this.lineas[j] = t;
                    },

                    /* ── IMPORTAR DE EXCEL (pedido del dueño 06-08) ──────────────────
                       Se PEGA lo copiado de la planilla en vez de subir un archivo: al
                       copiar de Excel las columnas llegan separadas por tabuladores, así
                       que se puede leer sin parsear .xlsx ni pedirle al usuario que
                       guarde y busque el archivo. Es el camino más corto a lo que él
                       quiere hacer con esto — «cargar y hacer una prueba si alcanza todo
                       o no». */
                    impAbierto: false, impTexto: '', impNoLeidas: [], impLeidas: 0,
                    importar() {
                        // Sin tildes, sin mayúsculas y sin dobles espacios: lo que se
                        // tipea en una planilla nunca coincide carácter a carácter con
                        // el catálogo.
                        const norm = (s) => (s || '').toLowerCase().normalize('NFD')
                            .replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
                        const nuevas = [], noLeidas = [];

                        for (const fila of this.impTexto.split(/\r?\n/)) {
                            if (!fila.trim()) continue;
                            // Tabulador (Excel), punto y coma, coma o dos espacios.
                            const partes = fila.split(/\t|;|,|\s{2,}/).map((p) => p.trim()).filter(Boolean);
                            const cant = parseInt((partes[partes.length - 1] || '').replace(/[^\d]/g, ''), 10);
                            const nombre = partes.slice(0, -1).join(' ');
                            const b = this.bultos.find((x) => norm(x.nombre) === norm(nombre))
                                || this.bultos.find((x) => norm(x.nombre).includes(norm(nombre)) && norm(nombre).length > 3);

                            if (!b || !(cant > 0)) { noLeidas.push(fila.trim()); continue; }
                            nuevas.push({ tipo: b.id, cantidad: cant, estiba: 'pie' });
                        }

                        this.impNoLeidas = noLeidas;
                        this.impLeidas = nuevas.length;
                        if (!nuevas.length) return;

                        // El tope de 8 líneas es el del formulario y del validador.
                        this.lineas = nuevas.slice(0, 8);
                        this.modo = 'mixta';
                        this.impAbierto = false;
                        // `$nextTick`: los inputs de las líneas nuevas todavía no existen
                        // en el DOM cuando esto corre, y enviar antes mandaría los viejos.
                        this.$nextTick(() => this.$refs.formMixta?.requestSubmit());
                    },
                 }" x-on:abrir-importar="impAbierto = true" class="space-y-6">

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
                    {{-- Tercer modo: armar un pallet y subirlo. Es una pregunta DISTINTA de
                         las otras dos («¿cuántas unidades me llevo paletizadas?») y tiene su
                         propio flujo, así que va como modo y no como una casilla. --}}
                    <button type="button" @click="modo = 'pallet'" role="tab" :aria-selected="modo === 'pallet'"
                            :class="modo === 'pallet' ? 'bg-brand-600 text-white shadow-sm' : 'text-neutral-600 hover:text-neutral-900'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold transition duration-150">
                        Sobre pallet
                    </button>
                </div>

                {{-- Traer la carga desde una planilla. Vive acá y no en el partial del visor
                     porque escribe en `lineas`, que es estado de esta pantalla. --}}
                @include('admin.carga._importar')

                @if ($escena)
                    {{-- ① EL CAMIÓN, a todo el ancho y arriba de todo. --}}
                    @include('admin.carga._visor')

                    {{-- ② Los datos: el camión en números | el veredicto y el detalle. --}}
                    <div class="grid gap-4 lg:grid-cols-3">

                        {{-- Columna 1 · el camión en números. Antes estas medidas solo
                             existían dentro del selector del formulario, que ahora quedó
                             al final de la pantalla. --}}
                        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">El camión</p>
                            <p class="mt-1 text-lg font-semibold leading-tight text-neutral-900">{{ $camion->nombre }}</p>

                            <div class="mt-3 border-t border-neutral-100 pt-3 text-sm">
                                <div class="flex justify-between gap-3 py-1">
                                    <span class="text-neutral-500">Medidas útiles</span>
                                    <span class="text-right font-medium tabular-nums text-neutral-900">
                                        {{ number_format($camion->largo_cm / 100, 2, ',', '.') }} ×
                                        {{ number_format($camion->ancho_cm / 100, 2, ',', '.') }} ×
                                        {{ number_format($camion->alto_cm / 100, 2, ',', '.') }} m
                                    </span>
                                </div>
                                <div class="flex justify-between gap-3 py-1">
                                    <span class="text-neutral-500">Volumen</span>
                                    <span class="font-medium tabular-nums text-neutral-900">{{ number_format($camion->volumenM3(), 1, ',', '.') }} m³</span>
                                </div>
                                <div class="flex justify-between gap-3 py-1">
                                    <span class="text-neutral-500">Carga máxima</span>
                                    <span class="font-medium tabular-nums text-neutral-900">
                                        @if ($camion->peso_max_kg)
                                            {{ number_format($camion->peso_max_kg, 0, ',', '.') }} kg
                                        @else
                                            <span class="font-normal text-neutral-400">sin dato</span>
                                        @endif
                                    </span>
                                </div>
                                @if ($camion->pasillo_cm > 0)
                                    <div class="flex justify-between gap-3 py-1">
                                        <span class="text-neutral-500">Pasillo reservado</span>
                                        <span class="font-medium tabular-nums text-neutral-900">{{ $camion->pasillo_cm }} cm</span>
                                    </div>
                                @endif
                                {{-- Los metros de piso que quedan contra la puerta: el «Free meters» de
                                     EasyCargo. Dice más que el porcentaje de ocupación para la pregunta
                                     que se hace todos los días («¿le sumo algo más a este viaje?»). --}}
                                <div class="flex justify-between gap-3 py-1">
                                    <span class="text-neutral-500">Piso libre en la puerta</span>
                                    <span class="font-medium tabular-nums text-neutral-900">
                                        {{ number_format($escena['libre_m'], 2, ',', '.') }} m
                                    </span>
                                </div>
                            </div>

                            <p class="mt-4 text-xs leading-relaxed text-neutral-400">
                                Medidas por DENTRO de la caja, no la ficha del fabricante: entre exterior e
                                interior hay 10 a 20% de volumen, que es la diferencia entre que la carga
                                entre o quede en el andén.
                            </p>
                        </div>

                        {{-- Columna 2 · el veredicto, la ocupación y el detalle. --}}
                        <div class="space-y-4 lg:col-span-2">

                            {{-- RESULTADO · carga mixta --}}
                            @if ($mixta !== null)
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
                                                    {{ number_format($mixta['resultado']['peso_kg'], 0, ',', '.') }} kg{{ $camion->peso_max_kg ? ' de '.number_format($camion->peso_max_kg, 0, ',', '.') : '' }}
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
                                            {{-- La LETRA del producto sobre su color: la misma que va escrita
                                                 sobre las cajas en el lienzo. Antes era solo un cuadradito de
                                                 color, y un color no se puede nombrar en voz alta («cargá el
                                                 verde» con dos verdes al lado no sirve) ni se distingue bien
                                                 con ocho productos. --}}
                                            <x-slot name="leading">
                                                <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded text-[11px] font-bold text-white"
                                                      style="background: rgb({{ implode(',', $rgb) }})"
                                                      title="Esta letra va escrita sobre sus cajas en el visor">{{ \App\Http\Controllers\Admin\SimuladorCargaController::letra($i) }}</span>
                                            </x-slot>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-medium text-neutral-900">{{ $fila['modelo']->nombre }}</p>
                                                @if ($fila['motivo'] === null)
                                                    <x-badge variant="brand">Completo</x-badge>
                                                @else
                                                    <x-badge variant="danger">Queda afuera</x-badge>
                                                @endif
                                                {{-- La estiba cambia el número, así que el resultado tiene que
                                                     decir con cuál se calculó: leer «entran 270» sin saber que
                                                     fue acostado invita a compararlo con los 420 de pie. --}}
                                                @if ($fila['estiba'] !== 'pie')
                                                    <x-badge>{{ \App\Models\TipoBulto::ESTIBAS[$fila['estiba']] }}</x-badge>
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

                            {{-- RESULTADO · cupo máximo --}}
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

                                <div x-show="modo === 'maximo'" class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
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
                                        @if ($bulto->puedeAcostarse())
                                            {{-- Con qué estiba salió este número: sin decirlo, «entran 270»
                                                 se compara contra los 420 de pie y parece un error. --}}
                                            <div class="flex justify-between py-1">
                                                <span class="text-neutral-500">Cómo viaja</span>
                                                <span class="font-medium text-neutral-900">{{ \App\Models\TipoBulto::ESTIBAS[$estiba] ?? 'De pie' }}</span>
                                            </div>
                                        @endif
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
                            @endif

                            {{-- RESULTADO · SOBRE PALLET. Se lee de arriba abajo como se arma:
                                 primero lo que entra en UN pallet, después cuántos pallets entran
                                 en el camión, y al final el total. --}}
                            @if ($enPallet !== null)
                                <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Sobre pallet</p>
                                    <p class="mt-1 text-4xl font-semibold tabular-nums text-brand-600">
                                        {{ number_format($enPallet['unidadesTotales'], 0, ',', '.') }}
                                    </p>
                                    <p class="text-sm text-neutral-500">
                                        unidades en total, en {{ $enPallet['cabenPallets'] }}
                                        {{ \Illuminate\Support\Str::plural('pallet', $enPallet['cabenPallets']) }}
                                    </p>

                                    <div class="mt-4 border-t border-neutral-100 pt-3 text-sm">
                                        <div class="flex justify-between gap-3 py-1">
                                            <span class="text-neutral-500">En un pallet</span>
                                            <span class="text-right font-medium tabular-nums text-neutral-900">
                                                {{ number_format($enPallet['unidadesPorPallet'], 0, ',', '.') }} unidades
                                                ({{ $enPallet['porPallet']['bultos'] }} {{ \Illuminate\Support\Str::plural('bulto', $enPallet['porPallet']['bultos']) }})
                                            </span>
                                        </div>
                                        <div class="flex justify-between gap-3 py-1">
                                            <span class="text-neutral-500">Rejilla en el pallet</span>
                                            <span class="font-medium tabular-nums text-neutral-900">{{ $enPallet['porPallet']['rejilla']['largo'] }} × {{ $enPallet['porPallet']['rejilla']['ancho'] }} × {{ $enPallet['porPallet']['rejilla']['alto'] }}</span>
                                        </div>
                                        <div class="flex justify-between gap-3 py-1">
                                            <span class="text-neutral-500">Pallet armado</span>
                                            <span class="font-medium tabular-nums text-neutral-900">
                                                {{ $pallet->largo_cm }} × {{ $pallet->ancho_cm }} × {{ $pallet->alto_cm }} cm
                                            </span>
                                        </div>
                                        <div class="flex justify-between gap-3 py-1">
                                            <span class="text-neutral-500">Peso por pallet</span>
                                            <span class="font-medium tabular-nums text-neutral-900">{{ number_format($enPallet['pesoArmadoKg'], 0, ',', '.') }} kg</span>
                                        </div>
                                        <div class="flex justify-between gap-3 py-1">
                                            <span class="text-neutral-500">Pallets en el camión</span>
                                            <span class="font-medium tabular-nums text-neutral-900">{{ $enPallet['enCamion']['rejilla']['largo'] }} × {{ $enPallet['enCamion']['rejilla']['ancho'] }}</span>
                                        </div>
                                    </div>

                                    @if (! $enPallet['entraEnPallet'])
                                        {{-- Pasa de verdad y no es un error del cálculo: la bolsa de
                                             botellones mide 130 cm y un pallet estándar tiene 120, así
                                             que sobresale. Hay que decir POR QUÉ y qué hacer, porque un
                                             «0» pelado se lee como que la app se equivocó. --}}
                                        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs leading-relaxed text-red-600">
                                            <strong>«{{ $bulto->nombre }}» no entra sobre este pallet.</strong>
                                            Mide {{ $bulto->largo_cm }} × {{ $bulto->ancho_cm }} × {{ $bulto->alto_cm }} cm
                                            y el pallet da {{ $pallet->largo_cm }} × {{ $pallet->ancho_cm }} ×
                                            {{ $pallet->altoUtilCm() }} cm útiles. Agrandá el pallet en «Ajustar medidas»
                                            @if ($bulto->largo_cm > $pallet->largo_cm)
                                                (necesita al menos {{ $bulto->largo_cm }} cm de largo)
                                            @endif
                                            o subí el alto total.
                                        </p>
                                    @elseif ($enPallet['cabenPallets'] === 0)
                                        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                                            <strong>El pallet armado no entra en {{ $camion->nombre }}.</strong>
                                            Con {{ $pallet->alto_cm }} cm de alto no pasa: probá bajarlo en «Ajustar medidas».
                                        </p>
                                    @endif

                                    <p class="mt-4 text-xs leading-relaxed text-neutral-400">
                                        Un pallet es una caja de carga: el mismo cálculo que dice cuánto entra en el
                                        camión dice cuánto entra encima del pallet. No se apila un pallet sobre otro —
                                        la estiba real a veces lo hace, pero prometerlo sin una regla de soporte por
                                        kilo sería exagerar.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ③ El formulario, al final: el dueño quiso el 3D lo más grande
                     posible y arriba de todo (05-08). --}}

                {{-- MODO 1 · cupo máximo de un producto. El x-cloak va en el form
                     del modo INACTIVO según lo que respondió el servidor: sin él,
                     el form del otro modo destella hasta que Alpine arranca. --}}
                <form x-show="modo === 'maximo'" @if ($mixta !== null) x-cloak @endif
                      method="GET" action="{{ route('admin.carga.index') }}"
                      class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:flex-row sm:items-end sm:p-4">
                    <div class="flex-1">
                        <x-input-label for="camion_id" value="Camión" />
                        <x-select id="camion_id" name="camion_id" class="mt-1.5" onchange="this.form.submit()">
                            @foreach ($camiones as $c)
                                <option value="{{ $c->id }}" @selected($camion?->id === $c->id)>
                                    {{ $c->nombre }}
                                    — {{ number_format($c->largo_cm / 100, 2, ',', '.') }} × {{ number_format($c->ancho_cm / 100, 2, ',', '.') }} × {{ number_format($c->alto_cm / 100, 2, ',', '.') }} m
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
                    @if ($bulto?->puedeAcostarse())
                        {{-- La misma elección de estiba que en la carga mixta: sin esto, la
                             pregunta «¿cuánto entra?» solo se podría responder de pie. --}}
                        <div class="sm:w-36">
                            <x-input-label for="estiba" value="Cómo viaja" />
                            <x-select id="estiba" name="estiba" class="mt-1.5" onchange="this.form.submit()">
                                @foreach (\App\Models\TipoBulto::ESTIBAS as $clave => $nombre)
                                    <option value="{{ $clave }}" @selected($estiba === $clave)>{{ $nombre }}</option>
                                @endforeach
                            </x-select>
                        </div>
                    @endif
                    {{-- TOPE DE APILADO. Es lo que dejaba el hueco arriba de la carga que el
                         dueño marcó (06-08): el catálogo dice 6 y el motor no sube más aunque
                         quede altura. Cuántas aguanta la de abajo es dato de terreno, no de
                         geometría, así que la decisión es suya. --}}
                    @if ($bulto)
                        <div class="sm:w-32">
                            <x-input-label for="apilado" value="Apilar hasta" />
                            <x-text-input id="apilado" name="apilado" type="number" min="1" max="30"
                                          class="mt-1.5 w-full" inputmode="numeric"
                                          value="{{ $apilado ?: $bulto->apilable_max }}"
                                          title="Cuántos se apilan uno sobre otro. El catálogo dice {{ $bulto->apilable_max }}." />
                        </div>
                    @endif
                    <div><x-primary-button>Calcular</x-primary-button></div>
                </form>

                {{-- MODO 2 · carga mixta: armá la carga producto por producto --}}
                <form x-show="modo === 'mixta'" @if ($mixta === null) x-cloak @endif
                      x-ref="formMixta"
                      method="GET" action="{{ route('admin.carga.index') }}"
                      class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <div class="sm:max-w-md">
                        <x-input-label for="camion_id_mixta" value="Camión" />
                        <x-select id="camion_id_mixta" name="camion_id" class="mt-1.5">
                            @foreach ($camiones as $c)
                                <option value="{{ $c->id }}" @selected($camion?->id === $c->id)>
                                    {{ $c->nombre }}
                                    — {{ number_format($c->largo_cm / 100, 2, ',', '.') }} × {{ number_format($c->ancho_cm / 100, 2, ',', '.') }} × {{ number_format($c->alto_cm / 100, 2, ',', '.') }} m
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
                                        {{-- CÓMO VIAJA el pack: de pie, acostado de costado o acostado con
                                             el pico a la puerta (pedido del dueño 05 y 06-08). Solo aparece
                                             donde cambia algo; en el resto el motor ya elige la orientación
                                             que más entra. --}}
                                        <select :name="`lineas[${i}][estiba]`" x-model="linea.estiba"
                                                x-show="acuesta(linea.tipo)" x-cloak
                                                title="Cómo viaja el pack"
                                                class="block w-40 rounded-lg border border-neutral-300 bg-white px-2.5 py-2.5 text-base sm:text-sm text-neutral-900 shadow-sm transition duration-150 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                            @foreach (\App\Models\TipoBulto::ESTIBAS as $clave => $nombre)
                                                <option value="{{ $clave }}">{{ $nombre }}</option>
                                            @endforeach
                                        </select>
                                        {{-- Mover el producto en la lista. Con el orden en «Como armé la
                                             lista», el primero es el que va al FONDO del camión: es
                                             mover la carga sin dejar de recalcular. --}}
                                        <div class="flex items-center" x-show="lineas.length > 1">
                                            <button type="button" @click="mover(i, -1)" :disabled="i === 0"
                                                    class="rounded-lg px-1.5 py-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 disabled:opacity-30"
                                                    title="Mover hacia el fondo del camión">▲<span class="sr-only">Mover hacia el fondo</span></button>
                                            <button type="button" @click="mover(i, 1)" :disabled="i === lineas.length - 1"
                                                    class="rounded-lg px-1.5 py-2 text-neutral-400 transition duration-150 hover:bg-neutral-100 hover:text-neutral-700 disabled:opacity-30"
                                                    title="Mover hacia la puerta">▼<span class="sr-only">Mover hacia la puerta</span></button>
                                        </div>
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
                            {{-- QUIÉN DECIDE el orden de estiba. En automático el motor pone lo
                                 grande al fondo, que es como se carga en la práctica; en «Como
                                 armé la lista» manda el orden de arriba y las flechas ▲▼ pasan a
                                 mover la carga de verdad. El automático sigue siendo el
                                 predeterminado: es el que reproduce las cargas verificadas. --}}
                            <div class="flex items-center gap-2" x-show="lineas.length > 1">
                                <label for="orden" class="text-xs text-neutral-500">Orden de estiba</label>
                                <x-select id="orden" name="orden" class="w-52">
                                    <option value="auto" @selected($orden === 'auto')>Automático (lo grande al fondo)</option>
                                    <option value="lista" @selected($orden === 'lista')>Como armé la lista</option>
                                </x-select>
                            </div>
                            <x-primary-button>Calcular la carga</x-primary-button>
                        </div>
                        <p class="mt-2 text-xs text-neutral-400">
                            Las cantidades van en unidades sueltas (botellones, cajas, equipos). Los botellones
                            viajan en bolsas de 5: se completa la bolsa.
                        </p>
                    </div>
                </form>

                {{-- MODO 3 · SOBRE PALLET. Se arma un pallet con un producto y se sube al
                     camión (pedido del dueño 06-08). TODAS las medidas son editables: las dos
                     estándar que dictó son el punto de partida, no una jaula. --}}
                <form x-show="modo === 'pallet'" @if ($enPallet === null) x-cloak @endif
                      method="GET" action="{{ route('admin.carga.index') }}"
                      class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4"
                      x-data="{
                          medidas: false,
                          tipos: {{ json_encode(\App\Services\Carga\PalletSimulado::TIPOS) }},
                          tipo: '{{ $pallet->largo_cm === 120 && $pallet->ancho_cm === 80 ? 'epal' : 'industrial' }}',
                          largo: {{ $pallet->largo_cm }}, ancho: {{ $pallet->ancho_cm }},
                          alto: {{ $pallet->alto_cm }}, base: {{ $pallet->base_cm }},
                          elegir() { const t = this.tipos[this.tipo]; if (t) { this.largo = t.largo; this.ancho = t.ancho; } },
                      }">
                    <input type="hidden" name="sobre_pallet" value="1">

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <x-input-label for="camion_id_pallet" value="Camión" />
                            <x-select id="camion_id_pallet" name="camion_id" class="mt-1.5">
                                @foreach ($camiones as $c)
                                    <option value="{{ $c->id }}" @selected($camion?->id === $c->id)>{{ $c->nombre }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div>
                            <x-input-label for="tipo_bulto_id_pallet" value="Qué se paletiza" />
                            <x-select id="tipo_bulto_id_pallet" name="tipo_bulto_id" class="mt-1.5">
                                @foreach ($bultos as $b)
                                    <option value="{{ $b->id }}" @selected($bulto?->id === $b->id)>{{ $b->nombre }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div>
                            <x-input-label for="pallet_tipo" value="Pallet" />
                            <x-select id="pallet_tipo" name="pallet_tipo" class="mt-1.5" x-model="tipo" @change="elegir()">
                                @foreach (\App\Services\Carga\PalletSimulado::TIPOS as $clave => $t)
                                    <option value="{{ $clave }}">{{ $t['nombre'] }} cm</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div>
                            <x-input-label for="pallet_alto" value="Alto total (cm)" />
                            <x-text-input id="pallet_alto" name="pallet_alto" type="number" x-model.number="alto"
                                          min="{{ \App\Services\Carga\PalletSimulado::ALTO_MIN }}"
                                          max="{{ \App\Services\Carga\PalletSimulado::ALTO_MAX }}"
                                          class="mt-1.5 w-full" inputmode="numeric" />
                        </div>
                    </div>

                    {{-- «Un botón para ajustar medidas», textual del pedido: las medidas finas
                         viven detrás de un botón para no llenar la pantalla de campos que casi
                         nunca se tocan, pero están. --}}
                    <div>
                        <x-secondary-button type="button" @click="medidas = !medidas">
                            <x-icon.plus class="h-4 w-4" />
                            <span x-text="medidas ? 'Listo' : 'Ajustar medidas'"></span>
                        </x-secondary-button>

                        <div x-show="medidas" x-cloak class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div>
                                <x-input-label for="pallet_largo" value="Largo del pallet (cm)" />
                                <x-text-input id="pallet_largo" name="pallet_largo" type="number" x-model.number="largo"
                                              min="40" max="300" class="mt-1.5 w-full" inputmode="numeric" />
                            </div>
                            <div>
                                <x-input-label for="pallet_ancho" value="Ancho del pallet (cm)" />
                                <x-text-input id="pallet_ancho" name="pallet_ancho" type="number" x-model.number="ancho"
                                              min="40" max="300" class="mt-1.5 w-full" inputmode="numeric" />
                            </div>
                            <div>
                                <x-input-label for="pallet_base" value="Alto de la tarima (cm)" />
                                <x-text-input id="pallet_base" name="pallet_base" type="number" x-model.number="base"
                                              min="5" max="30" class="mt-1.5 w-full" inputmode="numeric" />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @if ($bulto)
                            <div class="flex items-center gap-2">
                                <label for="apilado_pallet" class="text-xs text-neutral-500">Apilar hasta</label>
                                <x-text-input id="apilado_pallet" name="apilado" type="number" min="1" max="30"
                                              class="w-20" inputmode="numeric"
                                              value="{{ $apilado ?: $bulto->apilable_max }}" />
                            </div>
                        @endif
                        @if ($bulto?->puedeAcostarse())
                            <div class="flex items-center gap-2">
                                <label for="estiba_pallet" class="text-xs text-neutral-500">Cómo viaja</label>
                                <x-select id="estiba_pallet" name="estiba" class="w-44">
                                    @foreach (\App\Models\TipoBulto::ESTIBAS as $clave => $nombre)
                                        <option value="{{ $clave }}" @selected($estiba === $clave)>{{ $nombre }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                        @endif
                        <x-primary-button>Armar el pallet</x-primary-button>
                    </div>

                    <p class="text-xs leading-relaxed text-neutral-400">
                        La tarima estándar mide 14,4 cm y acá se usan 15 enteros: redondear la base
                        hacia arriba deja menos altura útil, y todo error tiene que ir hacia abajo. En la
                        práctica el pallet armado va entre 1,60 y 2,20 m de alto total.
                    </p>
                </form>
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
