<x-app-layout ancho="formulario">
    @php
        $esReparacion = $orden->condicion_efectiva === 'reparacion';
        // tipo_equipo_label (no ucfirst): una bomba es «Bomba de agua» en el resto de
        // la app y quedaba «Bomba» solo acá. Y el `modelo` es lo que el CLIENTE
        // escribió del equipo — el técnico lo necesita para identificar la máquina.
        $equipo = collect([
            $orden->tipo_equipo_label,
            $orden->modelo,
            $orden->producto?->sku,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');

        $repuestosInit = $orden->repuestos->map(fn ($r) => [
            'nombre' => $r->nombre,
            // El SKU viaja para no perderlo al re-guardar: es lo que después se
            // factura como código de catálogo (regla 4 de Contabilidad).
            'sku' => $r->sku,
            'cantidad' => $r->cantidad,
            'precio_unitario' => $r->precio_unitario,
        ])->values();

        // --- Lo que el presupuesto y el historial necesitan, ahora que viven acá ---
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $ultima = $cotizaciones->first();

        // Qué falta para poder enviarle la cotización al cliente (espejo de la
        // validación del servidor). LA ETAPA NO VA ACÁ: se elige en el select de esta
        // misma pantalla, así que la decide Alpine con el valor en pantalla
        // (`puedeEnviar` en el pie). Un `$faltas` estático diciendo «ya pasó la etapa»
        // se contradiría con el select en cuanto el técnico eligiera «Cotizacion».
        // El total en $0 tampoco va: el botón se deshabilita con el total EN PANTALLA,
        // porque enviar guarda primero.
        $faltas = collect([
            blank($orden->cliente_email) ? 'la orden no tiene correo del cliente (agrégalo en la recepción)' : null,
            // Sin mano de obra calculable no sale al cliente: el total le cobraría de
            // menos sin que nadie lo note. Guardar sigue libre.
            $faltaManoObra,
        ])->filter();
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Parte del técnico · '.$orden->folio" :subtitle="$orden->cliente_nombre.($equipo ? ' · '.$equipo : '')"
                       :back="route('admin.servicio-tecnico.index')" backTitle="Volver al listado" />
    </x-slot>

    <div class="py-12" x-data="{ editando: {{ $errors->any() ? 'true' : 'false' }} }">
        @include('admin.servicio-tecnico._tabs', ['activa' => 'tecnico'])

        <x-status-alert :status="session('status')" />

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            {{-- Resumen de la recepcion (solo lectura, para contexto del tecnico). --}}
            <div class="mb-6 rounded-lg border border-neutral-200 bg-neutral-50 p-4 text-sm">
                <p class="font-medium text-neutral-900">{{ $orden->cliente_nombre }} · {{ $orden->cliente_rut }}@if ($orden->cliente_telefono) · {{ $orden->cliente_telefono }}@endif</p>
                @if ($equipo)
                    <p class="mt-0.5 text-neutral-500">{{ $equipo }}</p>
                @endif
                @if ($orden->falla_reportada)
                    <p class="mt-2 text-neutral-700"><span class="font-medium">Falla reportada:</span> {{ $orden->falla_reportada }}</p>
                @endif
                <p class="mt-2">
                    <x-badge :variant="$esReparacion ? 'brand' : 'neutral'">{{ $esReparacion ? 'Reparación' : 'Garantía' }}</x-badge>
                    @unless ($esReparacion)
                        <span class="ml-1 text-xs text-neutral-500">Garantía vigente: la reparación no se cobra.</span>
                    @endunless
                </p>
                {{-- Los precios se ingresan ACÁ desde el 20-08. Esta nota decía lo
                     contrario («se ingresan en la pestaña Cotización») y era justo la
                     instrucción que el dueño mandó a borrar. --}}
                @if ($esReparacion)
                    <p class="mt-2 text-xs text-neutral-500">
                        Los precios y el total se arman en esta pantalla; la pestaña
                        <a href="{{ route('admin.servicio-tecnico.cotizacion', $orden) }}" class="font-medium text-brand-600 hover:text-brand-700">Cotización</a>
                        muestra lo que le llega al cliente.
                    </p>
                @endif
            </div>

            {{-- ===================== INFORME (solo lectura) ===================== --}}
            @php
                $trabajoTxt = $orden->trabajo_realizado;
                // Por el ACCESSOR del modelo, no indexando la constante: una causa
                // guardada fuera de la lista (dato histórico, o un valor renombrado)
                // reventaba la pantalla con «Undefined array key». El accessor cae en
                // «Sin determinar», que es la misma etiqueta que usa el informe.
                $causaTxt = $orden->causa_falla_label;
            @endphp
            <div x-show="!editando">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Detalle del trabajo realizado</h3>
                    <x-secondary-button type="button" x-on:click="editando = true">
                        <x-icon.pencil class="h-4 w-4" /> Editar
                    </x-secondary-button>
                </div>

                <dl class="divide-y divide-neutral-100 rounded-xl border border-neutral-200 text-sm">
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Estado / etapa</dt>
                        <dd class="text-right"><x-badge variant="neutral">{{ \Illuminate\Support\Str::headline($orden->estado) }}</x-badge></dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Trabajo realizado</dt>
                        <dd class="text-right text-neutral-900">{{ $trabajoTxt ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Causa de la falla</dt>
                        <dd class="text-right text-neutral-900">{{ $causaTxt ?: 'Sin determinar' }}</dd>
                    </div>
                    @if ($orden->es_propia)
                        <div class="flex items-start justify-between gap-4 px-4 py-3">
                            <dt class="text-neutral-500">Categoría (reventa)</dt>
                            <dd class="text-right text-neutral-900">{{ $orden->categoria ? \App\Models\OrdenServicio::CATEGORIA_ETIQUETAS[$orden->categoria] : '—' }}</dd>
                        </div>
                    @endif
                    <div class="px-4 py-3">
                        <dt class="mb-1.5 text-neutral-500">Repuestos usados</dt>
                        <dd>
                            @forelse ($orden->repuestos as $r)
                                <div class="flex items-center justify-between py-0.5 text-neutral-900">
                                    <span>{{ $r->nombre }}</span>
                                    <span class="text-neutral-400">× {{ $r->cantidad }}</span>
                                </div>
                            @empty
                                <span class="text-neutral-400">Sin repuestos registrados.</span>
                            @endforelse
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Fecha de aviso</dt>
                        <dd class="text-right text-neutral-900">{{ $orden->fecha_aviso?->format('d-m-Y') ?: '—' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4 px-4 py-3">
                        <dt class="text-neutral-500">Fecha de retiro</dt>
                        <dd class="text-right text-neutral-900">{{ $orden->fecha_retiro?->format('d-m-Y') ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ===================== EDICIÓN (formulario) ===================== --}}
            <form x-show="editando" x-cloak id="reparacion-form" method="POST" action="{{ route('admin.servicio-tecnico.reparacion.guardar', $orden) }}"
                class="space-y-6" data-una-vez
                {{-- `manoObra` se siembra con la VIGENTE del catálogo (lo que va a quedar
                     al guardar), NO con `$orden->mano_obra`: si el trabajo perdió su
                     tiempo estándar, el total no puede prometer un monto que el guardado
                     baja a $0. Antes acá daba igual porque esta pantalla no mostraba el
                     total; ahora sí lo muestra (bitácora 2026-08-07). --}}
                x-data="reparacionForm({ repuestos: @js($repuestosInit), manoObra: {{ (int) $manoObraVigente }}, endpointRepuestos: '{{ route('admin.servicio-tecnico.buscar-repuesto') }}', precioHora: {{ (int) ($precioHoraServicio ?? 0) }}, descuentoPct: {{ (int) old('descuento_pct', $orden->descuento_pct ?? 0) }} })">
                @csrf
                @method('PUT')

                {{-- Estado / etapa --}}
                <div>
                    <x-input-label for="estado">Estado / etapa <span class="text-red-500">*</span></x-input-label>
                    <x-select id="estado" name="estado" class="mt-1.5" required>
                        @foreach ($estados as $e)
                            <option value="{{ $e }}" @selected(old('estado', $orden->estado) === $e)>{{ \Illuminate\Support\Str::headline($e) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                </div>

                {{-- Trabajo realizado: respuestas FIJAS del historial, agrupadas por resultado,
                     MÁS la opción de escribirlo a mano (dueño, 14-08-2026: «que quede la
                     respuesta manual»). El texto libre va en un textarea con corrección
                     ortográfica del navegador —el subrayado rojo que pidió— porque este texto
                     LO LEE EL CLIENTE: sale en el correo del retiro y en la cotización.

                     Un texto histórico fuera de la lista abre el campo manual con ese texto (y
                     no como una opción muerta del select): así se le puede corregir la falta de
                     ortografía que ya tiene, que es justo el punto. --}}
                @php
                    // LAS DOS FORMAS A LA VEZ (dueño, 20-08-2026): la lista completa el texto
                    // con un clic Y el texto queda editable. Antes eran excluyentes —había que
                    // elegir «Otro» en el select para recién poder escribir—, así que ajustarle
                    // una palabra a una respuesta de la lista obligaba a re-escribirla entera.
                    //
                    // El texto del textarea es AHORA LA ÚNICA respuesta. El select es un
                    // rellenador sin `name`: no viaja al servidor. El contrato con el
                    // controlador queda IGUAL que antes (`trabajo_realizado` = centinela y
                    // `trabajo_realizado_otro` = el texto), así que su validación, su colapso de
                    // espacios —esto se pega desde WhatsApp— y su tope de 191 siguen aplicando
                    // sin tocar una línea de PHP.
                    $textoInicial = old('trabajo_realizado_otro', old('trabajo_realizado') !== null ? '' : (string) $orden->trabajo_realizado);
                @endphp
                <div x-data="{
                        mapa: @js($tiemposMap),
                        valorHora: {{ (int) ($precioHoraServicio ?? 0) }},
                        texto: @js((string) $textoInicial),
                        get trabajo() { return this.texto.trim() },
                     }">
                    <x-input-label for="trabajo_realizado_lista" value="Trabajo realizado">
                        <x-slot:ayuda>
                            Elige una respuesta de la lista y queda escrita en el campo de abajo; después la
                            puedes ajustar o escribir la tuya.
                            <strong>Este texto lo lee el cliente</strong> en el correo del retiro y en la
                            cotización: cuenta qué se hizo y cómo quedó, como las respuestas de la lista.
                            El navegador te subraya en rojo lo que esté mal escrito — clic derecho sobre la
                            palabra para ver las sugerencias.
                        </x-slot:ayuda>
                    </x-input-label>

                    {{-- El rellenador: elegir una respuesta la ESCRIBE en el campo de abajo. Sin
                         `name` a propósito — lo que se guarda es el texto, no la opción. Vuelve a
                         «— Elige para completar —» después de rellenar: no es un estado que se
                         conserve, es una acción, y dejarlo marcado hacía creer que el texto
                         editado seguía siendo esa respuesta. --}}
                    <x-select id="trabajo_realizado_lista" class="mt-1.5"
                              x-on:change="if ($event.target.value) { texto = $event.target.value; $event.target.value = '' }">
                        <option value="">— Elige para completar —</option>
                        @foreach ($respuestasTrabajo as $grupo => $opciones)
                            <optgroup label="{{ $grupo }}">
                                @foreach ($opciones as $op)
                                    <option value="{{ $op }}">{{ $op }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </x-select>
                    {{-- Queda UNA línea visible, la que explica el mecanismo raro del control (elegir
                         acá ESCRIBE abajo); el resto está en la ⓘ de la etiqueta. --}}
                    <x-input-hint>Se escribe abajo y la puedes ajustar.</x-input-hint>

                    {{-- El texto, SIEMPRE visible y editable. `spellcheck` + `lang="es"` = el
                         subrayado rojo del navegador con sugerencias al hacer clic derecho. El
                         `lang` va explícito y no heredado del <html>: así el diccionario es el
                         español aunque la app corra con otro locale.

                         El centinela viaja en un hidden y SOLO cuando hay texto: vacío significa
                         «todavía no hay respuesta», que es un caso válido (se guarda el parte sin
                         cerrar el trabajo) y el controlador lo trata como nullable. --}}
                    <input type="hidden" name="trabajo_realizado"
                           x-bind:value="trabajo ? @js(\App\Models\OrdenServicio::TRABAJO_OTRO) : ''">
                    <div class="mt-2">
                        <x-textarea name="trabajo_realizado_otro" rows="2" x-model="texto"
                            spellcheck="true" lang="es" autocapitalize="sentences"
                            maxlength="{{ \App\Models\OrdenServicio::TRABAJO_MAX }}"
                            placeholder="Ej. Cambio de bomba y limpieza de circuito — funciona normal">{{ $textoInicial }}</x-textarea>
                        <x-input-error :messages="$errors->get('trabajo_realizado_otro')" class="mt-2" />
                    </div>
                    {{-- Mano de obra FIJA por el trabajo: informativa (la fija jefatura). --}}
                    <div class="mt-2 text-sm" x-cloak>
                        <template x-if="trabajo && mapa[trabajo] !== undefined">
                            <p class="rounded-lg bg-neutral-50 px-3 py-2 text-neutral-600">
                                Tiempo estándar: <span class="font-medium text-neutral-900" x-text="String(mapa[trabajo]).replace('.', ',')"></span> h
                                → mano de obra <span class="font-medium text-neutral-900" x-text="'$' + Math.round(mapa[trabajo] * valorHora).toLocaleString('es-CL')"></span>
                                <span class="text-neutral-400">· la fija jefatura, no se edita</span>
                            </p>
                        </template>
                        <template x-if="trabajo && mapa[trabajo] === undefined">
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-700">
                                Este trabajo no tiene tiempo estándar → mano de obra $0. Jefatura puede agregarlo en «Costos generales de reparación».
                                Guardar sí se puede; la cotización no se envía hasta que ese tiempo exista.
                                {{-- Desde el 20-08 el texto se puede editar, así que hay una forma
                                     nueva de caer acá: ajustarle una palabra a una respuesta de la
                                     lista. Se dice, porque si no el técnico ve desaparecer la mano
                                     de obra sin entender qué la hizo desaparecer. --}}
                                <span class="mt-1 block text-amber-600">Si ajustaste una respuesta de la lista, su tiempo estándar aplica solo con el texto exacto: dejalo tal cual para recuperarlo.</span>
                            </p>
                        </template>
                    </div>
                    <x-input-error :messages="$errors->get('trabajo_realizado')" class="mt-2" />
                </div>

                {{-- Causa de la falla (diagnóstico del técnico): alimenta el
                     indicador del informe para reforzar capacitación al cliente.
                     OBLIGATORIA al cerrar como «Reparado» o «Sin solución». --}}
                <div x-data="{
                        exige: false,
                        init() {
                            const sel = document.getElementById('estado');
                            const set = () => { this.exige = !!sel && ['reparado', 'sin_solucion'].includes(sel.value); };
                            set();
                            if (sel) sel.addEventListener('change', set);
                        },
                     }">
                    <x-input-label for="causa_falla">Causa de la falla <span x-show="exige" class="text-red-500">*</span></x-input-label>
                    <x-select id="causa_falla" name="causa_falla" class="mt-1.5" x-bind:required="exige">
                        <option value="">Sin determinar</option>
                        @foreach ($causasFalla as $c)
                            <option value="{{ $c }}" @selected(old('causa_falla', $orden->causa_falla) === $c)>{{ \App\Models\OrdenServicio::CAUSA_FALLA_ETIQUETAS[$c] }}</option>
                        @endforeach
                    </x-select>
                    <x-input-hint>¿La máquina falló por mal uso del cliente, desgaste normal o defecto de fábrica?</x-input-hint>
                    <x-input-hint x-show="exige" x-cloak>Obligatoria para cerrar la orden como «Reparado» o «Sin solución» (diagnóstico final).</x-input-hint>
                    <x-input-error :messages="$errors->get('causa_falla')" class="mt-2" />
                </div>

                {{-- Categoría de cierre: SOLO para máquinas propias (IMP. DALI). --}}
                @if ($orden->es_propia)
                    <div>
                        <x-input-label for="categoria" value="Categoría (para reventa)">
                            <x-slot:ayuda>Máquina propia (IMP. DALI): con qué calidad queda para revender — Primera, Segunda o Desarme (repuestos).</x-slot:ayuda>
                        </x-input-label>
                        <x-select id="categoria" name="categoria" class="mt-1.5">
                            <option value="">— Sin determinar —</option>
                            @foreach (\App\Models\OrdenServicio::CATEGORIAS as $cat)
                                <option value="{{ $cat }}" @selected(old('categoria', $orden->categoria) === $cat)>{{ \App\Models\OrdenServicio::CATEGORIA_ETIQUETAS[$cat] }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('categoria')" class="mt-2" />
                    </div>
                @endif

                {{-- EL PRESUPUESTO COMPLETO, dentro del parte del tecnico (dueno
                     20-08-2026: «que no exista un apartado… en la parte del tecnico se
                     pueda modificar la informacion»). Trae los repuestos CON su precio,
                     la mano de obra fija del catalogo, el descuento (solo si tenes el
                     permiso de jefatura) y el total con su IVA.

                     Reemplaza al bloque que declaraba solo nombre y cantidad y mandaba el
                     precio en un campo OCULTO para que re-guardar el parte no borrara lo
                     cotizado. Ese truco existia porque los precios vivian en otra pantalla;
                     con un solo formulario no hace falta.

                     EN GARANTIA va SIN precios: solo qué repuestos se usaron. Un «Costo
                     total a pagar» en una orden que no se cobra contradice al resto de la
                     app y al correo que recibe el cliente (repuestos sin precios). --}}
                @include('admin.servicio-tecnico.partials._presupuesto-campos', ['conPrecios' => $esReparacion])

                {{-- Fechas de aviso y retiro --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="fecha_aviso" value="Fecha de aviso al cliente" />
                        <x-text-input id="fecha_aviso" class="mt-1.5" type="date" name="fecha_aviso"
                            :value="old('fecha_aviso', $orden->fecha_aviso?->format('Y-m-d'))" />
                        <x-input-hint>Cuando se le avisó que el equipo está listo.</x-input-hint>
                        <x-input-error :messages="$errors->get('fecha_aviso')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="fecha_retiro" value="Fecha de retiro" />
                        <x-text-input id="fecha_retiro" class="mt-1.5" type="date" name="fecha_retiro"
                            :value="old('fecha_retiro', $orden->fecha_retiro?->format('Y-m-d'))" />
                        <x-input-hint>Cuando el cliente retiró el equipo (respaldo).</x-input-hint>
                        <x-input-error :messages="$errors->get('fecha_retiro')" class="mt-2" />
                    </div>
                </div>

                {{-- EL PIE: Guardar y ENVIAR LA COTIZACIÓN, en la misma fila (dueño
                     20-08-2026, señalando este recuadro). «Enviar» es submit de ESTE
                     formulario con enviar=1: guarda y manda en un paso, así lo que sale
                     al cliente es lo que está en pantalla — pegado a «Guardar», mandar
                     el snapshot viejo sin darse cuenta era demasiado fácil.

                     EL BOTÓN DEPENDE DE LA ETAPA, y esa se edita en este mismo
                     formulario: el servidor rechaza el envío si la orden ya pasó
                     «Cotización», con un mensaje que manda a cambiar la etapa… en esta
                     pantalla. Así que el botón se muestra según el estado ELEGIDO en el
                     select (Alpine, `puedeEnviar`), no según el guardado, y cuando no
                     corresponde se dice por qué en vez de ofrecer un botón que se va a
                     negar. Las etapas previas (recibido, en revisión) sí se pueden
                     enviar: el servidor las adelanta solo. --}}
                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2 border-t border-neutral-100 pt-5"
                     x-data="{
                        etapasQueEnvian: @js(['recibido', 'en_revision', 'cotizacion']),
                        etapa: @js((string) old('estado', $orden->estado)),
                        init() {
                            const sel = document.getElementById('estado');
                            if (sel) sel.addEventListener('change', () => { this.etapa = sel.value });
                        },
                        get puedeEnviar() { return this.etapasQueEnvian.includes(this.etapa) },
                     }">
                    @if ($esReparacion)
                        <p class="mr-auto max-w-sm text-xs text-neutral-400">
                            @if ($faltas->isNotEmpty())
                                Para enviarla al cliente: {{ $faltas->implode('; ') }}.
                            @else
                                <span x-show="puedeEnviar" x-cloak>
                                    «Enviar» guarda y manda la carta a {{ $orden->cliente_email }}.
                                    @if ($ultima && $ultima->estado === 'enviada') Reemplaza la anterior. @endif
                                </span>
                                <span x-show="! puedeEnviar" x-cloak>
                                    La orden pasó la etapa de cotización: para re-cotizar, elegí «Cotizacion» en Estado / etapa.
                                </span>
                            @endif
                        </p>
                    @endif
                    <button type="button" x-on:click="editando = false"
                        class="rounded-lg px-3 py-2 text-sm font-medium text-neutral-500 hover:text-neutral-700">Cancelar</button>
                    <div class="flex items-center gap-2">
                        @if ($esReparacion && $faltas->isEmpty())
                            <x-secondary-button type="submit" name="enviar" value="1"
                                                x-show="puedeEnviar" x-cloak
                                                x-bind:disabled="total <= 0"
                                                x-bind:title="total <= 0 ? 'Pon precios antes de enviar' : ''"
                                                x-on:click="if (! confirm('Se guardará y se enviará la cotización por ' + clp(total) + ' a ' + {{ Js::from($orden->cliente_email) }} + '. ¿Continuar?')) $event.preventDefault()">
                                {{ $ultima && $ultima->estado !== 'reemplazada' ? 'Enviar cotización nueva' : 'Enviar cotización' }}
                            </x-secondary-button>
                        @endif
                        <x-primary-button>
                            <x-icon.check class="h-4 w-4" /> Guardar
                        </x-primary-button>
                    </div>
                </div>
            </form>

            {{-- Y ABAJO, EN LA MISMA PANTALLA, lo que ya se le envió al cliente y su
                 historial: cuándo salió la carta, qué respondió y por qué, y cuándo
                 quedó listo para retirar (dueño 20-08: «toda la información en un solo
                 apartado»). Fuera del <form> a propósito: son datos, no campos. --}}
            @if ($esReparacion)
                @include('admin.servicio-tecnico.partials._envio-historial')
            @endif
        </div>
    </div>
</x-app-layout>
