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
                // Comentario de PHP y no de Blade: dentro de @php un {{-- --}} NO se
                // procesa y sale tal cual al PHP compilado.
                $bultosJson = $bultos->map(fn ($b) => [
                    'id' => $b->id, 'nombre' => $b->nombre, 'unidades' => $b->unidades,
                    // Para filtrar el selector de una línea EN PALLET: ahí solo van cajas.
                    'categoria' => $b->categoria,
                    // Para el marcador del campo «Apilar hasta»: el tope del catálogo es
                    // lo que se aplica si no se escribe nada, así que hay que poder verlo
                    // sin abrir la ficha del producto.
                    'apilable_max' => $b->apilable_max,
                ])->values();
                $lineasIniciales = $lineasSel->isNotEmpty()
                    ? $lineasSel
                    : collect([['tipo' => $bultos->first()?->id, 'cantidad' => 100, 'estiba' => 'auto']]);
                // Los MISMOS colores que el lienzo (COLORES_3D). El chip de cada línea
                // tiene que ser el color de su bloque en el dibujo: si divergieran, la
                // lista mentiría sobre cuál es cuál — que es justo lo que el color
                // resuelve. Misma razón por la que la constante es pública.
                $coloresPanel = collect(\App\Http\Controllers\Admin\SimuladorCargaController::COLORES_3D)
                    ->map(fn ($c) => sprintf('#%02x%02x%02x', ...$c));
            @endphp

            <div x-data="{
                    modo: '{{ $mixta !== null ? 'mixta' : ($enPallet !== null ? 'pallet' : 'maximo') }}',
                    lineas: {{ $lineasIniciales->toJson() }},
                    bultos: {{ $bultosJson->toJson() }},
                    colores: {{ $coloresPanel->toJson() }},
                    pallets: {{ Js::from(collect(\App\Services\Carga\PalletSimulado::TIPOS)->map(fn ($t) => $t['nombre'])) }},

                    /* PANEL DE CARGA (pedido del dueño 10-08, sobre la referencia de
                       EasyCargo): la lista deja de ser una fila plana y pasa a ser una
                       tarjeta por producto que se ABRE para editarla. Lo que se gana no
                       es estética: en la fila plana no cabía nada más, y por eso el
                       bulto a medida —que el motor soporta desde el 07-08— no tenía
                       dónde vivir. Acá sí. */
                    expandido: null,

                    /* ESTADO SUCIO. Editar NO recalcula: mientras haya cambios sin
                       enviar, el botón se pone en ámbar. Es deliberado — el dibujo de
                       al lado sigue mostrando el último resultado del servidor, y sin
                       marcarlo alguien leería un dibujo que ya no corresponde a los
                       números que está viendo. Antes disimularlo que mentir. */
                    sucio: false,
                    ensuciar() { this.sucio = true; },

                    /* Una línea es «a medida» cuando no apunta al catálogo: tipo 0.
                       Es el mismo contrato que ya valida el controlador — una línea
                       vale si trae UNA de las dos cosas, producto o medidas. */
                    aMedida(l) { return ! l.tipo && ! this.enPallet(l); },

                    /* LA CAJA A MEDIDA, DIBUJADA. Proyección isométrica a mano: el largo
                       va hacia la derecha-abajo, el ancho hacia la izquierda-abajo y el
                       alto hacia arriba. Se devuelven las tres caras visibles —techo,
                       frente y costado— y se pintan con la misma opacidad decreciente que
                       usa el lienzo 3D, así el bulto se reconoce como el mismo objeto.
                       Devuelve null mientras falte una medida: media caja dibujada
                       mentiría sobre la forma. */
                    vistaPrevia(l) {
                        const L = +l.medida_largo, W = +l.medida_ancho, H = +l.medida_alto;
                        if (!(L > 0 && W > 0 && H > 0)) return null;

                        const cos = 0.866, sen = 0.5;
                        // Escala para que la pieza más grande entre en el cuadro con aire.
                        const k = 84 / Math.max((L + W) * cos, H + (L + W) * sen);
                        const ux = L * k * cos, uy = L * k * sen;   // eje largo
                        const vx = -W * k * cos, vy = W * k * sen;  // eje ancho
                        const h = H * k;                            // eje alto

                        // Origen: la esquina de arriba-adelante, centrada en el cuadro.
                        const ox = 50 + (W * k * cos - L * k * cos) / 2;
                        const oy = 50 - (h + (L + W) * k * sen) / 2 + h;
                        const p = (x, y) => `${(ox + x).toFixed(1)},${(oy + y).toFixed(1)}`;

                        const m3 = (L * W * H) / 1e6;
                        return {
                            techo:   [p(0, -h), p(ux, uy - h), p(ux + vx, uy + vy - h), p(vx, vy - h)].join(' '),
                            frente:  [p(0, -h), p(ux, uy - h), p(ux, uy), p(0, 0)].join(' '),
                            costado: [p(0, -h), p(vx, vy - h), p(vx, vy), p(0, 0)].join(' '),
                            medidas: `${L} × ${W} × ${H} cm`,
                            volumen: m3 >= 0.01
                                ? m3.toFixed(2).replace('.', ',') + ' m³ por bulto'
                                : Math.round(m3 * 1e6 / 1e3) + ' litros por bulto',
                        };
                    },

                    /* ── LÍNEAS SOBRE PALLET ──────────────────────────────────────────
                       Una línea puede ir SUELTA o sobre un pallet armado. Con pallet, el
                       producto es lo que va ENCIMA y la cantidad cuenta PALLETS. */
                    enPallet(l) { return !! l.pallet; },
                    /* Solo cajas van en pallet. Al cambiar a pallet hay que CORREGIR el
                       producto elegido, no solo esconderlo: sin esto la línea queda
                       apuntando a la bolsa de botellones —que no entra en un pallet— y el
                       resultado sería un «no cabe» que el usuario no pidió. */
                    opciones(l) {
                        return this.enPallet(l) ? this.bultos.filter(b => b.categoria === 'cajas') : this.bultos;
                    },
                    alCambiarPallet(i) {
                        const l = this.lineas[i], ops = this.opciones(l);
                        if (! ops.some(b => b.id === l.tipo)) l.tipo = ops[0]?.id ?? 0;
                        this.ensuciar();
                    },
                    letra(i) { return String.fromCharCode(65 + (i % 26)); },
                    color(i) { return this.colores[i % this.colores.length]; },
                    resumen(l) {
                        if (this.aMedida(l)) {
                            const d = [l.medida_largo, l.medida_ancho, l.medida_alto].filter(Boolean);
                            return d.length === 3 ? d.join(' × ') + ' cm' : 'Falta alguna medida';
                        }
                        const b = this.bultos.find(b => b.id === l.tipo);
                        if (this.enPallet(l)) return 'Pallet ' + this.pallets[l.pallet] + ' · ' + (b ? b.nombre : '—');
                        return b ? b.nombre : '—';
                    },

                    /* El tope de apilado que se aplica si el campo queda vacío. Se muestra
                       como marcador porque es el número que MANDA cuando no se toca nada, y
                       hasta ahora había que abrir la ficha del producto para conocerlo. Un
                       bulto a medida no tiene ficha: ahí el tope lo pone la altura del
                       camión (el controlador le pasa 30). */
                    topeDeCatalogo(l) {
                        if (this.aMedida(l)) return 'lo que dé el alto';
                        return String(this.bultos.find(b => b.id === l.tipo)?.apilable_max ?? 1);
                    },
                    /* Subir el tope DESDE EL RESULTADO, que es donde se ve el hueco. Sin
                       esto el camino era: leer el aviso, buscar la tarjeta, abrirla, tipear
                       el número. El botón lo hace de un toque y recalcula, así que lo que
                       queda en pantalla lo sigue verificando el motor. */
                    apilarHasta(i, n) {
                        if (! this.lineas[i]) return;
                        this.lineas[i].apilado = n;
                        this.ensuciar();
                        this.$nextTick(() => this.$refs.formMixta?.requestSubmit());
                    },

                    agregar() { if (this.lineas.length < 8) { this.lineas.push({ tipo: this.bultos[0]?.id, cantidad: 10, estiba: 'auto', pallet: '' }); this.expandido = this.lineas.length - 1; this.ensuciar(); } },
                    agregarMedida() { if (this.lineas.length < 8) { this.lineas.push({ tipo: 0, cantidad: 1, estiba: 'auto', pallet: '', medida_nombre: '', medida_largo: '', medida_ancho: '', medida_alto: '', medida_peso: '' }); this.expandido = this.lineas.length - 1; this.ensuciar(); } },
                    /* Un botón propio y no «elegí pallet en el desplegable de la tarjeta»:
                       la lección de la pestaña que nadie encontró (10-08) es que una
                       función que existe pero no se ve, no existe. Arranca con la primera
                       caja del catálogo, que es lo único que va en pallet. */
                    agregarPallet() {
                        if (this.lineas.length >= 8) return;
                        const caja = this.bultos.find(b => b.categoria === 'cajas');
                        this.lineas.push({ tipo: caja?.id ?? 0, cantidad: 2, estiba: 'auto', pallet: Object.keys(this.pallets)[0], pallet_alto: '' });
                        this.expandido = this.lineas.length - 1;
                        this.ensuciar();
                    },
                    duplicar(i) { if (this.lineas.length < 8) { this.lineas.splice(i + 1, 0, { ...this.lineas[i] }); this.ensuciar(); } },
                    quitar(i) { this.lineas.splice(i, 1); if (this.expandido === i) this.expandido = null; this.ensuciar(); },
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
                            nuevas.push({ tipo: b.id, cantidad: cant, estiba: 'auto' });
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

                {{-- Las dos preguntas, como conmutador.

                     CADA PESTAÑA DICE CUÁNTOS PRODUCTOS ACEPTA (10-08). El dueño
                     preguntó «¿y dónde agrego otro bulto?» estando en «¿Cuánto entra?»,
                     que es de UN producto. El nombre decía la PREGUNTA pero no la
                     CAPACIDAD, así que desde acá no había forma de saber que lo de
                     varios productos existía en la pestaña de al lado. Dos palabras
                     debajo del título lo resuelven sin agregar un control. --}}
                <div class="inline-flex rounded-xl border border-neutral-200 bg-white p-1 shadow-sm" role="tablist">
                    <button type="button" @click="modo = 'maximo'" role="tab" :aria-selected="modo === 'maximo'"
                            :class="modo === 'maximo' ? 'bg-brand-600 text-white shadow-sm' : 'text-neutral-600 hover:text-neutral-900'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold leading-tight transition duration-150">
                        ¿Cuánto entra?
                        <span class="block text-[10px] font-normal opacity-75">un producto</span>
                    </button>
                    <button type="button" @click="modo = 'mixta'" role="tab" :aria-selected="modo === 'mixta'"
                            :class="modo === 'mixta' ? 'bg-brand-600 text-white shadow-sm' : 'text-neutral-600 hover:text-neutral-900'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold leading-tight transition duration-150">
                        ¿Cabe esta carga?
                        <span class="block text-[10px] font-normal opacity-75">varios productos</span>
                    </button>
                    {{-- Tercer modo: armar un pallet y subirlo. Es una pregunta DISTINTA de
                         las otras dos («¿cuántas unidades me llevo paletizadas?») y tiene su
                         propio flujo, así que va como modo y no como una casilla. --}}
                    <button type="button" @click="modo = 'pallet'" role="tab" :aria-selected="modo === 'pallet'"
                            :class="modo === 'pallet' ? 'bg-brand-600 text-white shadow-sm' : 'text-neutral-600 hover:text-neutral-900'"
                            class="rounded-lg px-4 py-2 text-sm font-semibold leading-tight transition duration-150">
                        Sobre pallet
                        <span class="block text-[10px] font-normal opacity-75">solo cajas</span>
                    </button>
                </div>

                {{-- Traer la carga desde una planilla. Vive acá y no en el partial del visor
                     porque escribe en `lineas`, que es estado de esta pantalla. --}}
                @include('admin.carga._importar')

                @if ($escena)
                    {{-- ① EL CAMIÓN, a todo el ancho y arriba de todo. --}}
                    @include('admin.carga._visor')

                    {{-- ② El veredicto, la ocupación y el detalle, a todo el ancho.

                         LOS DATOS DEL CAMIÓN YA NO ESTÁN ACÁ. Eran una franja horizontal
                         propia —con su borde, su sombra y el hueco entre tarjetas— justo
                         debajo del visor, y desde el 10-08 viven DENTRO del recuadro del
                         visor: «la descripción del camión la quiero adentro del cuadrado
                         donde está el camión, para poder usar mejor el espacio» (dueño,
                         dibujado sobre la pantalla). Ver `_visor.blade.php`. --}}
                        <div class="space-y-4">

                            {{-- RESULTADO · carga mixta --}}
                            @if ($mixta !== null)
                                {{-- EL VEREDICTO YA NO ESTÁ ACÁ. Era esta tarjeta, debajo del
                                     visor: había que mirar el camión, bajar, y recién ahí
                                     enterarse de que no entraba. Desde el 12-08 va pegado al
                                     borde de arriba del lienzo (`_visor.blade.php`), donde el
                                     dueño lo dibujó. No se repite: una respuesta por pantalla. --}}

                                {{-- ═══ SE PASA DE PESO ═══
                                     Pedido del dueño (11-08): «que cuando se pase el límite de
                                     carga aparezca un cartel de advertencia, aunque el camión no
                                     esté lleno completamente».

                                     El «aunque no esté lleno» es EL punto: con carga pesada el
                                     camión se llena de kilos mucho antes que de metros, y la
                                     pantalla mostraba 30% de ocupación y un renglón de peso que
                                     no gritaba nada. Un vendedor mira el dibujo medio vacío y
                                     promete el resto.

                                     Va SEPARADO del veredicto y antes de los números, porque no
                                     es lo mismo «no te entra» que «no lo podés llevar»: lo
                                     primero se negocia partiendo el viaje, lo segundo es una
                                     multa. --}}
                                @php $p = $mixta['peso']; @endphp
                                @if ($p['se_pasa'] || $p['recorto'])
                                    <div class="rounded-2xl border-2 border-red-300 bg-red-50 p-4 sm:p-5">
                                        <p class="text-lg font-semibold text-red-700">⚠ Se pasa de la carga máxima</p>
                                        <p class="mt-1 text-sm text-red-700">
                                            Lo que pediste pesa
                                            <span class="font-semibold tabular-nums">{{ number_format($p['pedido_kg'], 0, ',', '.') }} kg</span>
                                            y {{ $escena['vehiculo']['nombre'] }} aguanta
                                            <span class="font-semibold tabular-nums">{{ number_format($p['tope_kg'], 0, ',', '.') }} kg</span>:
                                            <span class="font-semibold tabular-nums">{{ number_format($p['pedido_kg'] - $p['tope_kg'], 0, ',', '.') }} kg de más</span>.
                                        </p>
                                        <p class="mt-2 text-sm text-red-700">
                                            El límite lo pone el PESO, no el espacio — por eso queda camión libre
                                            ({{ round($mixta['resultado']['ocupacion'] * 100) }}% ocupado). Cargar hasta
                                            arriba igual es ir sobrecargado.
                                        </p>
                                    </div>
                                @elseif ($p['tope_kg'] && $p['cargado_kg'] >= $p['tope_kg'] * 0.9)
                                    {{-- Todavía entra, pero al filo. Vale avisar: una caja más y
                                         el viaje pasa a ser ilegal, y eso no se ve en el dibujo. --}}
                                    <div class="rounded-2xl border border-red-200 bg-white p-4 shadow-sm sm:p-5">
                                        <p class="text-sm font-semibold text-red-700">Al filo de la carga máxima</p>
                                        <p class="mt-1 text-sm text-neutral-600">
                                            Va con <span class="font-semibold tabular-nums text-neutral-900">{{ number_format($p['cargado_kg'], 0, ',', '.') }} kg</span>
                                            de los {{ number_format($p['tope_kg'], 0, ',', '.') }} que aguanta
                                            ({{ round($p['cargado_kg'] / $p['tope_kg'] * 100) }}%). Queda poco margen.
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
                                            {{-- El peso también con BARRA, como la ocupación. Antes era el
                                                 único número sin una: dos cifras juntas no dicen si vas al
                                                 30% o al 95%, y con carga pesada ese es el dato que manda.
                                                 En rojo cuando pasa el 90%, que es donde deja de haber
                                                 margen para un error de tara. --}}
                                            @if ($p['tope_kg'])
                                                @php $usoPeso = min(100, round($p['cargado_kg'] / $p['tope_kg'] * 100)); @endphp
                                                <div class="h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                                    <div class="h-1.5 rounded-full {{ $usoPeso >= 90 ? 'bg-red-500' : 'bg-brand-600' }}"
                                                         style="width: {{ $usoPeso }}%"></div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- ═══ CÓMO CAE EL PESO ENTRE LOS EJES ═══
                                         Lote 5, con los datos de ejes del 12-08. Solo aparece en
                                         los camiones que tienen las DOS medidas; en el resto no se
                                         muestra nada y las notas del catálogo dicen qué falta.

                                         Va junto al peso porque responde la otra mitad de la misma
                                         pregunta: los kilos totales dicen si te pasás de la carga
                                         máxima, y esto dice si están puestos donde corresponde. Un
                                         camión puede ir por debajo del tope y aun así llevar el eje
                                         trasero pasado. --}}
                                    @if ($mixta['ejes'] !== null)
                                        @php $ej = $mixta['ejes']; @endphp
                                        <div class="mt-4 rounded-lg border border-neutral-200 p-3">
                                            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cómo cae el peso</p>
                                            <div class="mt-2 flex items-baseline gap-4 text-sm" @if ($ej['total_kg'] <= 0) hidden @endif>
                                                <span class="text-neutral-500">Eje delantero
                                                    <span class="font-semibold tabular-nums text-neutral-900">{{ number_format($ej['delantero_kg'], 0, ',', '.') }} kg</span>
                                                    <span class="tabular-nums text-neutral-400">({{ $ej['delantero_pct'] }}%)</span>
                                                </span>
                                                <span class="text-neutral-500">Eje trasero
                                                    <span class="font-semibold tabular-nums text-neutral-900">{{ number_format($ej['trasero_kg'], 0, ',', '.') }} kg</span>
                                                    <span class="tabular-nums text-neutral-400">({{ $ej['trasero_pct'] }}%)</span>
                                                </span>
                                            </div>
                                            {{-- Una barra que se lee de un vistazo: el reparto entre los
                                                 dos apoyos. Los porcentajes negativos se acotan solo en la
                                                 barra —no en el número— porque un ancho negativo no existe;
                                                 el caso lo grita el aviso de abajo. --}}
                                            @if ($ej['total_kg'] > 0)
                                                <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                                    <div class="h-1.5 bg-brand-600" style="width: {{ max(0, min(100, $ej['delantero_pct'])) }}%"></div>
                                                    <div class="h-1.5 bg-neutral-500" style="width: {{ max(0, min(100, $ej['trasero_pct'])) }}%"></div>
                                                </div>
                                            @endif

                                            {{-- Lo que quedó fuera del reparto, con nombre y apellido. La
                                                 mitad del catálogo todavía no tiene el peso cargado —está
                                                 en null a propósito, no se inventa— y antes eso hacía
                                                 desaparecer la sección entera sin decir por qué. --}}
                                            @if ($ej['sin_peso'] !== [])
                                                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800">
                                                    @if ($ej['total_kg'] <= 0)
                                                        <strong>No se puede repartir esta carga.</strong>
                                                    @else
                                                        <strong>Falta peso.</strong> El reparto de arriba deja afuera
                                                    @endif
                                                    {{ implode(', ', $ej['sin_peso']) }}: no {{ count($ej['sin_peso']) === 1 ? 'tiene' : 'tienen' }}
                                                    el peso cargado en el catálogo. Con ese dato el número sale solo.
                                                </p>
                                            @endif

                                            @if ($ej['aliviana_el_delantero'])
                                                <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs leading-relaxed text-red-700">
                                                    <strong>La carga está toda detrás del eje trasero.</strong>
                                                    En vez de apoyar sobre el delantero, lo LEVANTA: se pierde dirección y freno.
                                                    Hay que correr carga hacia la cabina.
                                                </p>
                                            @endif

                                            <p class="mt-2 text-xs leading-relaxed text-neutral-400">
                                                Reparte solo la CARGA, no el peso del camión vacío. Sirve para comparar
                                                dos formas de acomodar lo mismo; para avisar que un eje se pasa falta
                                                cuánto aguanta cada uno.
                                            </p>
                                        </div>
                                    @endif

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

                                {{-- ═══ EL ORDEN DE DESCARGA ═══
                                     Lote 6: multi-drop LIFO. Solo aparece si alguien declaró
                                     paradas; con una sola entrega esta sección no existe.

                                     Va en orden de ENTREGA (parada 1 primero) y no de carga,
                                     que es el inverso: esta lista la lee el CHOFER, y él las
                                     recorre en el orden en que maneja. El orden de carga —el
                                     del andén, del fondo hacia la puerta— ya lo dice el Excel. --}}
                                @if ($mixta['paradas'] !== null)
                                    <x-seccion titulo="El reparto, parada por parada">
                                        <p class="text-xs leading-relaxed text-neutral-500">
                                            Lo que baja primero se carga último. La parada 1 queda contra la
                                            <span class="font-medium text-neutral-700">puerta</span> y la última contra la cabina,
                                            así no hay que bajar mercadería a la vereda para llegar a la de atrás.
                                        </p>

                                        <ol class="space-y-2">
                                            @foreach ($mixta['paradas']['grupos'] as $grupo)
                                                <li class="flex gap-3 rounded-xl border border-neutral-200 bg-white p-3">
                                                    <span @class([
                                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                                        'bg-brand-600 text-white' => $grupo['parada'] > 0,
                                                        'bg-neutral-200 text-neutral-600' => $grupo['parada'] === 0,
                                                    ])>{{ $grupo['parada'] > 0 ? $grupo['parada'] : '—' }}</span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-medium text-neutral-900">
                                                            {{ $grupo['parada'] > 0 ? 'Parada '.$grupo['parada'] : 'Sin parada asignada' }}
                                                        </p>
                                                        <ul class="mt-0.5 space-y-0.5">
                                                            @foreach ($grupo['lineas'] as $fila)
                                                                <li class="text-sm text-neutral-600">
                                                                    {{ $fila['modelo']->nombre }}
                                                                    <span class="tabular-nums text-neutral-400">·
                                                                        {{ number_format($fila['cargadas_unidades'], 0, ',', '.') }} de
                                                                        {{ number_format($fila['pedidas_unidades'], 0, ',', '.') }}</span>
                                                                    @if ($fila['motivo'] !== null)
                                                                        <span class="font-medium text-red-600">· queda carga afuera</span>
                                                                    @endif
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ol>

                                        @if ($mixta['paradas']['sin_asignar'] > 0)
                                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800">
                                                Hay {{ $mixta['paradas']['sin_asignar'] }} producto(s) sin parada asignada. Se cargan
                                                junto a la puerta, o sea que salen en la primera entrega — si van a otra, ponéles el número.
                                            </p>
                                        @endif
                                    </x-seccion>
                                @endif

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
                                                // Un pallet en el que no entra ni una caja no se sube vacío
                                                // (§3.3.5): pasa de verdad con la bolsa de botellones, que
                                                // mide 130 cm contra los 120 del pallet.
                                                'pallet_vacio' => 'no entra ni una encima del pallet',
                                            ][$fila['motivo']] ?? null;
                                            $pal = $fila['pallet'];
                                            // En una línea EN PALLET la cuenta va en pallets, no en unidades
                                            // sueltas: «3 de 3 pallets», y las cajas se dicen aparte.
                                            $sustantivo = $pal ? \Illuminate\Support\Str::plural('pallet', $fila['pedidas_unidades']) : null;
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
                                                {{-- `pie` queda afuera a propósito: es lo que hace el motor
                                                     por su cuenta con un pack de orientación fija, así que
                                                     mostrarlo sería ruido. `horizontal` sí entra — cambia
                                                     el número igual que las acostadas. --}}
                                                @if (in_array($fila['estiba'], ['horizontal', 'costado', 'pico'], true))
                                                    <x-badge>{{ \App\Models\TipoBulto::ESTIBAS_ELEGIBLES[$fila['estiba']] }}</x-badge>
                                                @endif
                                            </div>
                                            <p class="text-sm text-neutral-500">
                                                {{ $pal ? 'Cargados' : 'Cargadas' }}
                                                <span class="font-medium tabular-nums text-neutral-900">{{ number_format($fila['cargadas_unidades'], 0, ',', '.') }}</span>
                                                de {{ number_format($fila['pedidas_unidades'], 0, ',', '.') }}{{ $pal ? ' '.$sustantivo : '' }}
                                                @if ($pal)
                                                    {{-- Cuántas cajas lleva cada uno y cuántas son en total: es la
                                                         cuenta que el vendedor necesita para cotizar, y la que se
                                                         perdía cuando el pallet era un modo aparte. --}}
                                                    {{-- Sin pluralizar el nombre del producto: «18 caja de tapas»
                                                         se lee mal y pluralizarlo a mano acierta en unos nombres
                                                         y falla en otros. El producto ya está en el título de la
                                                         fila, así que acá alcanza con el número. --}}
                                                    · <span class="tabular-nums">{{ number_format($pal['por_pallet'], 0, ',', '.') }}</span> por pallet
                                                    @if ($fila['cargadas_unidades'] > 0)
                                                        = <span class="font-medium tabular-nums text-neutral-900">{{ number_format($fila['cargadas_unidades'] * $pal['por_pallet'], 0, ',', '.') }}</span> en total
                                                    @endif
                                                @elseif ($fila['modelo']->unidades > 1)
                                                    ({{ $fila['bultos_colocados'] }} {{ \Illuminate\Support\Str::plural('bolsa', $fila['bultos_colocados']) }})
                                                @endif
                                                @if ($motivoTexto)
                                                    · <span class="text-red-600">quedan {{ number_format($pendientes, 0, ',', '.') }} afuera: {{ $motivoTexto }}</span>
                                                @endif
                                            </p>

                                            {{-- EL AIRE QUE QUEDA ARRIBA DE ESTE PRODUCTO.
                                                 Pedido del dueño (10-08): «necesito que los bidones también
                                                 lleguen hasta el techo». El hueco no era del dibujo ni del
                                                 acomodo — era el tope de apilado del catálogo. Y no se
                                                 explicaba solo: dos productos apilados los MISMOS 6 llegan a
                                                 alturas distintas según cuánto mida cada uno, así que en
                                                 pantalla parecía un error. Se dice, y se arregla de un toque. --}}
                                            @if ($fila['apiladas'] && $fila['apilables_por_alto'] > $fila['apiladas'])
                                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-neutral-500">
                                                    <span>
                                                        Van <span class="font-medium tabular-nums text-neutral-700">{{ $fila['apiladas'] }}</span> de alto
                                                        y {{ $pal ? 'el pallet' : 'la caja' }} da para
                                                        <span class="font-medium tabular-nums text-neutral-700">{{ $fila['apilables_por_alto'] }}</span>.
                                                    </span>
                                                    <button type="button"
                                                            @click="apilarHasta({{ $i }}, {{ $fila['apilables_por_alto'] }})"
                                                            class="min-h-8 rounded-lg bg-brand-50 px-2 py-1 font-medium text-brand-700 ring-1 ring-inset ring-brand-200 transition hover:bg-brand-100"
                                                            title="Apilar hasta donde llega la altura del camión y recalcular. Cuántas aguanta la de abajo lo sabés vos.">
                                                        Apilar {{ $fila['apilables_por_alto'] }}
                                                    </button>
                                                </p>
                                            @endif
                                        </x-list-row>
                                    @endforeach
                                </x-list-card>
                            @endif

                            {{-- RESULTADO · cupo máximo. DOS TARJETAS, no una columna larga
                                 (pedido del dueño 10-08, dibujado sobre la pantalla): a la
                                 izquierda EL NÚMERO que se vino a buscar, a la derecha DE DÓNDE
                                 SALE. En una sola tarjeta el «550 bultos» encabezaba seis filas
                                 etiqueta-valor: el dato y su letra chica compartían una columna
                                 angosta, y al lado de un dibujo que ocupa todo el ancho la
                                 tarjeta se veía como una tira de texto.

                                 La OCUPACIÓN se fue con el número y no con el detalle: la barra
                                 es el camión llenándose, se lee junto al «entran». Estaba
                                 escrita dos veces —una fila con el % y abajo una barra sin
                                 rótulo—; ahora es un solo bloque. --}}
                            @if ($resultado)
                                @php
                                    $lim = [
                                        'largo' => 'el largo de la caja',
                                        'ancho' => 'el ancho de la caja',
                                        'alto' => 'la altura (o el tope de apilado)',
                                        'peso' => 'la carga máxima en kilos',
                                        'ninguno' => '—',
                                    ][$resultado['limite']] ?? '—';
                                    $ocupacionCupo = round($resultado['ocupacion'] * 100);
                                    // EL AIRE QUE QUEDA ARRIBA. El mismo aviso que la carga mixta,
                                    // por el mismo motivo: el tope de apilado corta antes que la
                                    // altura y el hueco no se explica solo. Acá el campo ya está a
                                    // la vista con el número del catálogo, pero cuántas CABRÍAN no
                                    // se decía en ninguna parte.
                                    $apiladasCupo = $resultado['rejilla']['alto'];
                                    $techoCupo = $resultado['orientacion']['alto'] > 0
                                        ? intdiv($camion->alto_cm, $resultado['orientacion']['alto'])
                                        : 0;
                                    // EL PESO CORTÓ ANTES QUE EL ESPACIO. `cupo()` calcula primero
                                    // la rejilla y después recorta por kilos, así que la rejilla
                                    // que devuelve sigue siendo la del ESPACIO: multiplicarla da
                                    // cuántos habrían entrado si el camión aguantara. Es el número
                                    // que convierte «entran 154» en «entran 154 de los 324 que
                                    // caben, porque te quedaste sin kilos».
                                    $porEspacioCupo = $resultado['rejilla']['largo'] * $resultado['rejilla']['ancho'] * $resultado['rejilla']['alto'];
                                    $cortoPorPeso = $resultado['limite'] === 'peso' && $porEspacioCupo > $resultado['bultos'];
                                @endphp

                                {{-- ═══ SE LLENA DE KILOS ANTES QUE DE METROS ═══
                                     El mismo aviso que la carga mixta (pedido del dueño 11-08), acá
                                     con la comparación que este modo permite: cuántos habrían
                                     entrado por espacio contra cuántos deja el peso. Va ARRIBA de
                                     las dos tarjetas y a todo el ancho, porque el número grande de
                                     al lado —«entran 154»— es justo el que se lee sin contexto. --}}
                                @if ($cortoPorPeso)
                                    <div x-show="modo === 'maximo'" class="rounded-2xl border-2 border-red-300 bg-red-50 p-4 sm:p-5">
                                        <p class="text-lg font-semibold text-red-700">⚠ Se llena de kilos antes que de espacio</p>
                                        <p class="mt-1 text-sm text-red-700">
                                            Por espacio entrarían
                                            <span class="font-semibold tabular-nums">{{ number_format($porEspacioCupo, 0, ',', '.') }}</span>,
                                            pero la carga máxima de
                                            <span class="font-semibold tabular-nums">{{ number_format($camion->peso_max_kg, 0, ',', '.') }} kg</span>
                                            deja solo
                                            <span class="font-semibold tabular-nums">{{ number_format($resultado['bultos'], 0, ',', '.') }}</span>.
                                            El camión va a quedar <strong>por la mitad y aun así al tope</strong>: lo que
                                            sobra es lugar, no capacidad.
                                        </p>
                                    </div>
                                @endif

                                <div x-show="modo === 'maximo'" class="grid gap-4 lg:grid-cols-2">

                                    {{-- ① EL NÚMERO --}}
                                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                                        {{-- LA PRUEBA («¿me entran 50?») ya no se responde acá: desde
                                             el 12-08 el veredicto va pegado al borde de arriba del
                                             lienzo, CON los números («de tus 50 entran 42, quedan 8»).
                                             Repetirlo acá sería decir dos veces lo mismo en la misma
                                             pantalla. Ver `_visor.blade.php`. --}}
                                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Entran</p>
                                        <p class="mt-1 text-4xl font-semibold text-neutral-900 tabular-nums">{{ number_format($resultado['bultos'], 0, ',', '.') }}</p>
                                        <p class="text-sm text-neutral-500">{{ \Illuminate\Support\Str::plural('bulto', $resultado['bultos']) }}</p>

                                        @if ($bulto->unidades > 1)
                                            <p class="mt-3 text-2xl font-semibold text-brand-600 tabular-nums">
                                                {{ number_format($resultado['unidades'], 0, ',', '.') }}
                                            </p>
                                            <p class="text-sm text-neutral-500">unidades ({{ $bulto->unidades }} por bulto)</p>
                                        @endif

                                        <div class="mt-4 border-t border-neutral-100 pt-3">
                                            <div class="flex items-baseline justify-between text-sm">
                                                <span class="text-neutral-500">Ocupación</span>
                                                <span class="font-medium tabular-nums text-neutral-900">{{ $ocupacionCupo }}%</span>
                                            </div>
                                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                                <div class="h-1.5 rounded-full bg-brand-600" style="width: {{ min(100, $ocupacionCupo) }}%"></div>
                                            </div>
                                        </div>

                                        @if ($bulto->peligrosa)
                                            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                                                <strong>Mercancía peligrosa{{ $bulto->peligrosa_codigo ? ' ('.$bulto->peligrosa_codigo.')' : '' }}.</strong>
                                                El cupo es solo de espacio: el transporte tiene reglas propias de rotulado y segregación.
                                                Que quepa no significa que se pueda cargar así.
                                            </p>
                                        @endif

                                        {{-- ═══ LO QUE ENTRÓ DE VERDAD ═══
                                             El lazo de vuelta del historial (lote 4). Esta tarjeta
                                             viene diciendo desde el día uno que su número es un
                                             TECHO y que «se calibra contando una carga real»; acá
                                             aparece esa carga, cuando existe.

                                             NO corrige el cupo, lo acompaña. Reemplazarlo por el
                                             medido sería cambiar un número verificable por un
                                             promedio de dos anécdotas; mostrar los dos deja ver el
                                             hueco, que es la información. --}}
                                        @if (! empty($medido))
                                            <div class="mt-4 rounded-lg bg-neutral-50 px-3 py-2 text-sm">
                                                <p class="font-medium text-neutral-900">
                                                    En terreno entraron
                                                    <span class="tabular-nums">{{ number_format($medido['promedio'], 0, ',', '.') }}</span>
                                                    <span class="font-normal text-neutral-500">
                                                        ({{ round($medido['factor'] * 100) }}% de lo calculado)
                                                    </span>
                                                </p>
                                                <p class="mt-0.5 text-xs text-neutral-500">
                                                    {{ $medido['veces'] === 1 ? 'Una sola carga anotada,' : 'Promedio de '.$medido['veces'].' cargas anotadas,' }}
                                                    la última el {{ $medido['ultima'] }} ·
                                                    <a href="{{ route('admin.cargas-reales.index') }}"
                                                       class="font-medium text-brand-700 hover:text-brand-600">ver el historial</a>
                                                </p>
                                            </div>
                                        @endif

                                        {{-- El enlace va dentro del párrafo y sin `@if` en línea: una
                                             directiva partida entre dos líneas de texto rompe el parser
                                             («unexpected token endif»), y el punto final pegado al
                                             `@endif` era lo que dejaba « real .» con el espacio de más. --}}
                                        <p class="mt-4 text-xs leading-relaxed text-neutral-400">
                                            Capacidad práctica, no promesa: la estiba real no es una rejilla perfecta (amarres, hilera del
                                            piso girada). Se calibra contando una carga real{!! empty($medido)
                                                ? ' — <a href="'.route('admin.cargas-reales.index').'" class="font-medium text-neutral-500 hover:text-neutral-700">anotá una en Cargas reales</a>'
                                                : '' !!}.
                                        </p>
                                    </div>

                                    {{-- ② DE DÓNDE SALE ESE NÚMERO. Las filas van separadas por
                                         línea (`divide-y`) y no por aire: son pares
                                         etiqueta-valor, y con el ojo entrenado en la tabla de
                                         un Excel se leen más rápido así. --}}
                                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">De dónde sale ese número</p>

                                        <div class="mt-2 divide-y divide-neutral-100 text-sm">
                                            @if ($bulto->puedeAcostarse())
                                                {{-- Con qué estiba salió este número: sin decirlo, «entran 270»
                                                     se compara contra los 420 de pie y parece un error. --}}
                                                <div class="flex justify-between gap-3 py-2">
                                                    <span class="text-neutral-500">Cómo viaja</span>
                                                    <span class="text-right font-medium text-neutral-900">{{ \App\Models\TipoBulto::ESTIBAS_ELEGIBLES[$estiba] ?? 'Automático' }}</span>
                                                </div>
                                            @endif
                                            <div class="flex justify-between gap-3 py-2">
                                                <span class="text-neutral-500">Se agota primero</span>
                                                <span class="text-right font-medium text-neutral-900">{{ $lim }}</span>
                                            </div>
                                            <div class="flex justify-between gap-3 py-2">
                                                <span class="text-neutral-500">Rejilla</span>
                                                <span class="text-right font-medium tabular-nums text-neutral-900">{{ $resultado['rejilla']['largo'] }} × {{ $resultado['rejilla']['ancho'] }} × {{ $resultado['rejilla']['alto'] }}</span>
                                            </div>
                                            @if ($resultado['peso_kg'] > 0)
                                                <div class="flex justify-between gap-3 py-2">
                                                    <span class="text-neutral-500">Peso</span>
                                                    <span class="text-right font-medium tabular-nums text-neutral-900">{{ number_format($resultado['peso_kg'], 0, ',', '.') }} kg</span>
                                                </div>
                                            @endif
                                            @if ($apiladasCupo > 0 && $techoCupo > $apiladasCupo)
                                                <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                                                    <span class="text-neutral-500">Queda aire arriba</span>
                                                    <span class="flex items-center gap-2">
                                                        <span class="text-xs text-neutral-500">la caja da para <span class="tabular-nums">{{ $techoCupo }}</span></span>
                                                        <button type="button"
                                                                @click="$refs.apilado.value = {{ $techoCupo }}; $refs.apilado.form.requestSubmit()"
                                                                class="min-h-8 rounded-lg bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-200 transition hover:bg-brand-100"
                                                                title="Apilar hasta donde llega la altura del camión y recalcular. Cuántas aguanta la de abajo lo sabés vos.">
                                                            Apilar {{ $techoCupo }}
                                                        </button>
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
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
                                @foreach (\App\Models\TipoBulto::ESTIBAS_ELEGIBLES as $clave => $nombre)
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
                                          class="mt-1.5 w-full" inputmode="numeric" x-ref="apilado"
                                          value="{{ $apilado ?: $bulto->apilable_max }}"
                                          title="Cuántos se apilan uno sobre otro. El catálogo dice {{ $bulto->apilable_max }}." />
                        </div>
                    @endif
                    {{-- CANTIDAD A PROBAR (pedido del dueño 06-08: «me falta la opción de
                         cuánto cargo, 1, 20, 50, para realizar la prueba»). Vacío = el
                         máximo, que era el único comportamiento hasta ahora. --}}
                    <div class="sm:w-36">
                        <x-input-label for="cantidad" value="Cantidad a probar" />
                        <x-text-input id="cantidad" name="cantidad" type="number" min="1" max="100000"
                                      class="mt-1.5 w-full" inputmode="numeric"
                                      value="{{ $cantidad }}" placeholder="Máximo"
                                      title="¿Te entran 50? Escribí 50 y calculá. Vacío calcula el máximo." />
                    </div>
                    <div><x-primary-button>Calcular</x-primary-button></div>
                    {{-- El atajo desde donde falta. Este modo es de UN producto, y el
                         dueño buscó acá el botón para agregar otro (10-08). En vez de
                         duplicar el panel en los dos modos —que sería tener dos listas
                         que se contradicen— se ofrece el camino: cambiar de pregunta
                         llevándose el camión y el producto ya elegidos, para no volver
                         a armar la pantalla del otro lado. --}}
                    <div class="basis-full">
                        <button type="button" @click="modo = 'mixta'"
                                class="text-xs text-neutral-500 underline-offset-2 transition hover:text-brand-700 hover:underline">
                            ¿Necesitás cargar más de un producto? Pasá a «¿Cabe esta carga?»
                        </button>
                    </div>
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
                            {{-- UNA TARJETA POR PRODUCTO, que se abre para editarla.
                                 Cerrada muestra lo que hace falta para reconocerla (letra,
                                 color del bloque en el dibujo, qué es y cuántos); abierta,
                                 todo lo editable. La fila plana de antes no tenía dónde
                                 poner las medidas del bulto a medida, y por eso esa
                                 función existía solo en el motor. --}}
                            <template x-for="(linea, i) in lineas" :key="i">
                                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">

                                    {{-- Cabecera: clic para abrir --}}
                                    <div class="flex cursor-pointer items-center gap-2.5 px-3 py-2.5 transition hover:bg-neutral-50"
                                         @click="expandido = expandido === i ? null : i">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[11px] font-bold text-white"
                                              :style="`background:${color(i)}`" x-text="letra(i)"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-neutral-900" x-text="resumen(linea)"></p>
                                            <p class="text-xs text-neutral-500">
                                                <span x-text="linea.cantidad"></span>
                                                <span x-text="enPallet(linea)
                                                    ? (linea.cantidad == 1 ? ' pallet' : ' pallets')
                                                    : ((bultos.find(b => b.id === linea.tipo)?.unidades ?? 1) > 1 ? ' unidades' : ' bultos')"></span>
                                                <template x-if="linea.estiba && linea.estiba !== 'auto'">
                                                    <span> · <span x-text="{{ Js::from(\App\Models\TipoBulto::ESTIBAS_ELEGIBLES) }}[linea.estiba]"></span></span>
                                                </template>
                                            </p>
                                        </div>
                                        <span class="shrink-0 text-neutral-400 transition" :class="expandido === i && 'rotate-180'">▾</span>
                                    </div>

                                    {{-- Cuerpo editable --}}
                                    <div x-show="expandido === i" x-cloak
                                         class="space-y-3 border-t border-neutral-100 bg-neutral-50/60 px-3 py-3">

                                        {{-- SUELTO O SOBRE PALLET, por línea (pedido del dueño 10-08:
                                             «en la vida real cargamos a veces pallets y de paso
                                             bidones o dispensadores»). Antes «Sobre pallet» era un
                                             MODO que se comía el camión entero: para ver tres pallets
                                             de tapas y cien botellones sueltos había que elegir uno
                                             de los dos. --}}
                                        <div>
                                            <label class="text-xs font-medium text-neutral-600">Cómo va</label>
                                            <select :name="`lineas[${i}][pallet]`" x-model="linea.pallet" @change="alCambiarPallet(i)"
                                                    class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-2 py-2 text-base sm:text-sm text-neutral-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                                <option value="">Suelto</option>
                                                @foreach (\App\Services\Carga\PalletSimulado::TIPOS as $clave => $tipo)
                                                    <option value="{{ $clave }}">Sobre pallet {{ $tipo['nombre'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label class="text-xs font-medium text-neutral-600"
                                                   x-text="enPallet(linea) ? 'Qué va encima del pallet' : 'Qué se carga'"></label>
                                            <select :name="`lineas[${i}][tipo]`" x-model.number="linea.tipo" @change="ensuciar()"
                                                    class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-base sm:text-sm text-neutral-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                                {{-- EN PALLET SOLO VAN CAJAS (§3.3.6, dueño 07-08). No es un
                                                     límite del motor sino cómo se trabaja en bodega, y
                                                     ofrecer la bolsa de botellones acá devolvería «0 cajas
                                                     por pallet» —mide 130 cm y el pallet 120— que se lee
                                                     como que la app falló. --}}
                                                <template x-for="b in opciones(linea)" :key="b.id">
                                                    <option :value="b.id" x-text="b.nombre" :selected="b.id === linea.tipo"></option>
                                                </template>
                                                {{-- Valor 0 = bulto a medida. Es el contrato que el
                                                     controlador ya valida desde el 07-08. Sobre pallet no
                                                     se ofrece: un pallet se arma con un producto medido. --}}
                                                <template x-if="! enPallet(linea)">
                                                    <option :value="0">— Bulto a medida —</option>
                                                </template>
                                            </select>
                                        </div>

                                        {{-- CUBICAR: las medidas a mano. Solo aparecen cuando la
                                             línea es «a medida», porque para un producto del
                                             catálogo serían campos que contradicen su ficha. --}}
                                        <div x-show="aMedida(linea)" x-cloak class="space-y-2 rounded-lg border border-brand-200 bg-brand-50/50 p-2.5">
                                            <input type="text" :name="`lineas[${i}][medida_nombre]`" x-model="linea.medida_nombre" @input="ensuciar()"
                                                   maxlength="60" placeholder="Nombre (ej. Heladera exhibidora)"
                                                   class="block w-full rounded-lg border-neutral-300 px-3 py-2 text-base sm:text-sm shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            <div class="grid grid-cols-3 gap-2">
                                                @foreach (['largo' => 'Largo', 'ancho' => 'Ancho', 'alto' => 'Alto'] as $campo => $rotulo)
                                                    <div>
                                                        <label class="text-[11px] text-neutral-500">{{ $rotulo }} (cm)</label>
                                                        <input type="number" :name="`lineas[${i}][medida_{{ $campo }}]`"
                                                               x-model.number="linea.medida_{{ $campo }}" @input="ensuciar()"
                                                               min="1" max="{{ $campo === 'largo' ? 1500 : 300 }}" inputmode="numeric"
                                                               class="mt-0.5 block w-full rounded-lg border-neutral-300 px-2 py-1.5 text-base sm:text-sm tabular-nums shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div>
                                                <label class="text-[11px] text-neutral-500">Peso por bulto (kg, opcional)</label>
                                                <input type="number" :name="`lineas[${i}][medida_peso]`" x-model.number="linea.medida_peso" @input="ensuciar()"
                                                       min="0" max="30000" step="0.1" inputmode="decimal"
                                                       class="mt-0.5 block w-full rounded-lg border-neutral-300 px-2 py-1.5 text-base sm:text-sm tabular-nums shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            </div>
                                            {{-- LA CAJA, DIBUJADA MIENTRAS SE TIPEA (pedido del dueño 11-08,
                                                 sobre las capturas de EasyCargo: «un tablero donde se pueda
                                                 simular el tamaño de una caja para agregarla al camión»).
                                                 Tres números sueltos no dan idea de la forma; el dibujo sí, y
                                                 delata al toque un cero de más o dos medidas cambiadas. Es
                                                 SVG isométrico calculado acá mismo — no toca el lienzo 3D ni
                                                 el motor: es la misma caja que se va a cubicar, nada más. --}}
                                            <template x-if="vistaPrevia(linea)">
                                                <div class="mt-1 flex items-center gap-3 rounded-lg bg-white p-2">
                                                    {{-- Tamaño por ATRIBUTO y no por clase de utilidad: la que
                                                         correspondía no está en el bundle compilado y hoy no se
                                                         puede recompilar (hay trabajo de otra sesión sin
                                                         commitear en el árbol). Un thumbnail de 80 px no
                                                         necesita ser responsive.

                                                         Y ojo: Tailwind escanea TEXTO PLANO, así que nombrar la
                                                         clase acá adentro —aunque sea un comentario de Blade que
                                                         nunca llega al HTML— la vuelve a meter en el bundle. --}}
                                                    <svg viewBox="0 0 100 100" width="80" height="80" class="shrink-0" aria-hidden="true">
                                                        <polygon :points="vistaPrevia(linea).techo" :fill="color(i)" opacity="0.95" />
                                                        <polygon :points="vistaPrevia(linea).frente" :fill="color(i)" opacity="0.75" />
                                                        <polygon :points="vistaPrevia(linea).costado" :fill="color(i)" opacity="0.55" />
                                                    </svg>
                                                    <div class="min-w-0 text-[11px] leading-snug text-neutral-600">
                                                        <p class="font-medium text-neutral-900" x-text="vistaPrevia(linea).medidas"></p>
                                                        <p x-text="vistaPrevia(linea).volumen"></p>
                                                        <p class="text-neutral-400">Vive solo en esta simulación: no se guarda en el catálogo.</p>
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="! vistaPrevia(linea)">
                                                <p class="text-[11px] leading-snug text-neutral-500">
                                                    Escribí las tres medidas y la caja se dibuja acá. Vive solo en
                                                    esta simulación: no se guarda en el catálogo.
                                                </p>
                                            </template>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2">
                                            <div>
                                                <label class="text-xs font-medium text-neutral-600"
                                                       x-text="enPallet(linea) ? 'Cuántos pallets' : 'Cuántos'"></label>
                                                <div class="mt-1 flex items-stretch rounded-lg border border-neutral-300 bg-white">
                                                    <button type="button" @click="linea.cantidad = Math.max(1, (linea.cantidad || 1) - 1); ensuciar()"
                                                            class="px-2.5 text-neutral-500 transition hover:text-neutral-900" aria-label="Uno menos">−</button>
                                                    <input type="number" :name="`lineas[${i}][cantidad]`" x-model.number="linea.cantidad" @input="ensuciar()"
                                                           min="1" max="100000" inputmode="numeric" required
                                                           class="w-full min-w-0 border-0 bg-transparent px-1 py-2 text-center text-base sm:text-sm tabular-nums focus:ring-0">
                                                    <button type="button" @click="linea.cantidad = (linea.cantidad || 0) + 1; ensuciar()"
                                                            class="px-2.5 text-neutral-500 transition hover:text-neutral-900" aria-label="Uno más">+</button>
                                                </div>
                                            </div>
                                            <div>
                                                {{-- CÓMO VIAJA: automático o una estiba forzada. Está en TODOS los
                                                     productos (pedido del dueño 06-08) — en la práctica un
                                                     dispensador viaja parado aunque el motor pudiera tumbarlo.
                                                     El default `auto` es lo que protege el número verificado. --}}
                                                <label class="text-xs font-medium text-neutral-600">Cómo viaja</label>
                                                <select :name="`lineas[${i}][estiba]`" x-model="linea.estiba" @change="ensuciar()"
                                                        class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-2 py-2 text-base sm:text-sm text-neutral-900 shadow-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                                    @foreach (\App\Models\TipoBulto::ESTIBAS_ELEGIBLES as $clave => $nombre)
                                                        <option value="{{ $clave }}">{{ $nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            {{-- APILAR HASTA, por línea. Es el control que faltaba acá: el modo
                                                 de un producto lo tiene desde el 06-08, y en la carga mixta cada
                                                 producto se quedaba con el tope de su catálogo. Se nota cuando
                                                 dos productos apilan los MISMOS 6: seis cajas de 42 cm tocan el
                                                 techo y seis bolsas acostadas de 26 llegan a media caja.
                                                 Cuántas aguanta la de abajo es dato de terreno, así que la
                                                 decisión es del dueño y el vacío deja el del catálogo. --}}
                                            {{-- La altura del pallet ARMADO. Decide cuántas cajas entran
                                                 encima y cuántos pallets entran a lo alto del camión, así
                                                 que no puede ser un valor fijo: el rango real es 1,60 a
                                                 2,20 m. Vive en esta grilla —y no arriba, al lado de
                                                 «Cómo va»— porque acá cae en su propia celda sin pedirle
                                                 al layout una clase de span. --}}
                                            <div x-show="enPallet(linea)" x-cloak>
                                                <label class="text-xs font-medium text-neutral-600">Alto del pallet (cm)</label>
                                                <input type="number" :name="`lineas[${i}][pallet_alto]`" x-model="linea.pallet_alto" @input="ensuciar()"
                                                       min="{{ \App\Services\Carga\PalletSimulado::ALTO_MIN }}" max="{{ \App\Services\Carga\PalletSimulado::ALTO_MAX }}" inputmode="numeric"
                                                       placeholder="{{ \App\Services\Carga\PalletSimulado::ALTO_DEFECTO }}"
                                                       class="mt-1 block w-full rounded-lg border-neutral-300 px-3 py-2 text-base sm:text-sm tabular-nums shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            </div>
                                            <div>
                                                <label class="text-xs font-medium text-neutral-600"
                                                       x-text="enPallet(linea) ? 'Apilar en el pallet' : 'Apilar hasta'"></label>
                                                <input type="number" :name="`lineas[${i}][apilado]`" x-model="linea.apilado" @input="ensuciar()"
                                                       min="1" max="30" inputmode="numeric"
                                                       :placeholder="topeDeCatalogo(linea)"
                                                       :title="enPallet(linea)
                                                           ? `Cuántas cajas se apilan ENCIMA del pallet. Un pallet no se apila sobre otro. Vacío deja el tope de siempre: ${topeDeCatalogo(linea)}.`
                                                           : `Cuántos se apilan uno sobre otro. Vacío deja el tope de siempre: ${topeDeCatalogo(linea)}.`"
                                                       class="mt-1 block w-full rounded-lg border-neutral-300 px-3 py-2 text-base sm:text-sm tabular-nums shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            </div>
                                            {{-- LA PARADA EN LA QUE BAJA (lote 6: multi-drop LIFO).
                                                 Vacío = una sola entrega, que es el caso de siempre.

                                                 Es un NÚMERO de orden de entrega y no el nombre del
                                                 cliente: lo que el motor necesita es la secuencia
                                                 —quién baja antes que quién— y un nombre no se
                                                 ordena. El nombre vive en la hoja de ruta. --}}
                                            <div>
                                                <label class="text-xs font-medium text-neutral-600">Baja en la parada</label>
                                                <input type="number" :name="`lineas[${i}][parada]`" x-model="linea.parada" @input="ensuciar()"
                                                       min="1" max="20" inputmode="numeric" placeholder="—"
                                                       title="El número de parada del reparto: 1 es la primera que se entrega. Lo que baja primero se carga último, contra la puerta. Vacío = una sola entrega."
                                                       class="mt-1 block w-full rounded-lg border-neutral-300 px-3 py-2 text-base sm:text-sm tabular-nums shadow-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1 border-t border-neutral-200 pt-2.5 text-xs">
                                            {{-- Mover el producto en la lista. Con el orden en «Como armé la
                                                 lista», el primero es el que va al FONDO del camión: es
                                                 mover la carga sin dejar de recalcular. --}}
                                            <template x-if="lineas.length > 1">
                                                <div class="flex items-center gap-1">
                                                    <button type="button" @click="mover(i, -1); ensuciar()" :disabled="i === 0"
                                                            class="rounded-lg px-2 py-1.5 text-neutral-500 transition hover:bg-neutral-200 disabled:opacity-30"
                                                            title="Mover hacia el fondo del camión">▲ Al fondo</button>
                                                    <button type="button" @click="mover(i, 1); ensuciar()" :disabled="i === lineas.length - 1"
                                                            class="rounded-lg px-2 py-1.5 text-neutral-500 transition hover:bg-neutral-200 disabled:opacity-30"
                                                            title="Mover hacia la puerta">▼ A la puerta</button>
                                                </div>
                                            </template>
                                            <button type="button" @click="duplicar(i)" x-show="lineas.length < 8"
                                                    class="rounded-lg px-2 py-1.5 text-neutral-500 transition hover:bg-neutral-200">Duplicar</button>
                                            <button type="button" @click="quitar(i)" x-show="lineas.length > 1"
                                                    class="ml-auto rounded-lg px-2 py-1.5 text-neutral-400 transition hover:bg-red-50 hover:text-red-600"
                                                    title="Quitar producto">Quitar</button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <x-secondary-button type="button" @click="agregar()" x-show="lineas.length < 8">
                                <x-icon.plus class="h-4 w-4" />
                                Agregar producto
                            </x-secondary-button>
                            {{-- CUBICAR: el motor acepta bultos a medida desde el 07-08, pero
                                 hasta ahora solo se llegaba por URL porque la fila plana no
                                 tenía dónde poner las medidas. Con el panel, sí. --}}
                            <x-secondary-button type="button" @click="agregarPallet()" x-show="lineas.length < 8"
                                                title="Sumar pallets armados a esta misma carga, junto con lo suelto">
                                <x-icon.plus class="h-4 w-4" />
                                Pallet
                            </x-secondary-button>
                            <x-secondary-button type="button" @click="agregarMedida()" x-show="lineas.length < 8"
                                                title="Cubicar algo que no está en el catálogo">
                                <x-icon.plus class="h-4 w-4" />
                                Bulto a medida
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
                            {{-- ESTADO SUCIO: mientras haya cambios sin enviar, el botón lo
                                 dice. El dibujo de al lado sigue mostrando el último
                                 resultado del servidor, y sin esta señal alguien leería un
                                 camión que ya no corresponde a los números que acaba de
                                 tipear. Antes avisar que disimular. --}}
                            <x-primary-button ::class="sucio && 'ring-2 ring-amber-400 ring-offset-1'">
                                <span x-show="! sucio">Calcular la carga</span>
                                <span x-show="sucio" x-cloak>Recalcular ·  hay cambios</span>
                            </x-primary-button>
                        </div>
                        {{-- ═══ EL CAMIÓN NO SIEMPRE SALE VACÍO ═══
                             Lote 5. Pasa todo el tiempo: el camión vuelve de un reparto con
                             carga arriba, o se le suma un pedido a uno que ya está armado.
                             Hasta ahora eso se simulaba a ojo eligiendo un camión más chico.

                             Va detrás de un botón porque el caso normal ES el camión vacío:
                             dos campos siempre visibles con 0 adentro son dos campos que
                             estorban en cada simulación para servir en una de cada diez.

                             LOS DOS CAMPOS JUNTOS, no de a uno: descontar el espacio sin
                             descontar los kilos deja el cartel de sobrepeso en verde con el
                             camión pasado. Ver `CamionSimulacion::paraCalculo`. --}}
                        @php $yaLleva = ($mixta['ocupado']['hay'] ?? false); @endphp
                        <div class="mt-3" x-data="{ abierto: {{ $yaLleva ? 'true' : 'false' }} }">
                            <button type="button" @click="abierto = ! abierto"
                                    :aria-pressed="abierto ? 'true' : 'false'"
                                    class="text-xs font-medium text-brand-700 underline-offset-2 hover:underline">
                                <span x-show="! abierto">El camión ya lleva carga</span>
                                <span x-show="abierto" x-cloak>Ocultar lo que ya lleva</span>
                            </button>

                            <div x-show="abierto" x-cloak class="mt-2 flex flex-wrap items-end gap-3">
                                <div>
                                    <label for="ocupado_cm" class="text-xs text-neutral-500">Piso ya ocupado (cm)</label>
                                    <x-text-input id="ocupado_cm" name="ocupado_cm" type="number" min="0" max="2000"
                                                  class="mt-1 w-32" inputmode="numeric" placeholder="0"
                                                  :value="($mixta['ocupado']['cm'] ?? 0) ?: ''"
                                                  @input="ensuciar()" />
                                </div>
                                <div>
                                    <label for="ocupado_kg" class="text-xs text-neutral-500">Kilos ya cargados</label>
                                    <x-text-input id="ocupado_kg" name="ocupado_kg" type="number" min="0" max="40000"
                                                  step="0.1" class="mt-1 w-32" inputmode="decimal" placeholder="0"
                                                  :value="($mixta['ocupado']['kg'] ?? 0) ?: ''"
                                                  @input="ensuciar()" />
                                </div>
                                <p class="max-w-md text-xs leading-snug text-neutral-500">
                                    Se descuentan del largo útil y de la carga máxima. Lo que ya viaja se toma
                                    contra la cabina —se subió primero— así que lo nuevo se acomoda desde ahí
                                    hacia la puerta, y el dibujo lo muestra en gris.
                                </p>
                            </div>
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
                            {{-- SOLO CAJAS (dueño, 07-08-2026: «los botellones nunca van a
                                 ir en pallet, solo cajas»). No es un límite del motor —
                                 palletiza cualquier bulto que quepa— sino cómo se trabaja
                                 en bodega. Ofrecer las bolsas acá devolvía un «0 pallets»
                                 que se lee como que la app falló, cuando en realidad el
                                 producto no va en pallet: la bolsa mide 130 cm y el pallet
                                 120. --}}
                            <x-select id="tipo_bulto_id_pallet" name="tipo_bulto_id" class="mt-1.5">
                                @foreach ($paletizables as $b)
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
                                    @foreach (\App\Models\TipoBulto::ESTIBAS_ELEGIBLES as $clave => $nombre)
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
