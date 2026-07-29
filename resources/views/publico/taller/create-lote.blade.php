{{--
    Ingreso PUBLICO por CANTIDAD (varias máquinas de una vez). Sin login; la
    sucursal viene fija desde el link firmado. El cliente escribe SUS datos una
    sola vez y agrega cada máquina como una tarjeta (tipo, código opcional,
    serie y 2 fotos de respaldo). Cada máquina queda como una orden con su
    PROPIO folio (el técnico informa cada equipo por separado).
--}}
<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold tracking-tight text-neutral-900">Ingreso por cantidad</h1>
        <p class="mt-1 text-sm text-neutral-500">
            Sucursal <span class="font-medium text-neutral-700">{{ $sucursal->nombre }}</span>
        </p>
        <p class="mt-3 text-sm text-neutral-500">
            Escribe tus datos una sola vez y agrega cada máquina. Cada equipo queda con su propio folio.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            Revisa los datos: hay {{ $errors->count() }} campo(s) con problemas más abajo.
        </div>
    @endif

    <form method="POST" action="{{ route('ingreso-taller.lote.store') }}" enctype="multipart/form-data" class="space-y-5 pb-[calc(6rem_+_env(safe-area-inset-bottom))] sm:pb-0"
          x-data="loteServicioForm({ tipoDefault: @js(old('tipo_default', 'dispensador')), tiposSerie: @js(\App\Models\OrdenServicio::SERIE_OBLIGATORIA_TIPOS) })">
        @csrf
        <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">
        {{-- Honeypot anti-bot (un humano no lo ve ni lo llena). --}}
        <div class="hidden" aria-hidden="true">
            <label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- Tus datos (una sola vez) --}}
        <x-seccion titulo="Tus datos (una sola vez)">
            <div>
                <x-input-label for="cliente_nombre">Nombre y apellido <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('cliente_nombre')" />
                <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cliente_rut">RUT <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 w-full" required
                    maxlength="20" placeholder="Ej. 12.345.678-9" :value="old('cliente_rut')" />
                <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cliente_email">Correo <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('cliente_email')" />
                <x-input-hint>Te llegará el detalle con el folio de cada equipo.</x-input-hint>
                <x-input-error :messages="$errors->get('cliente_email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cliente_telefono">Teléfono <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 w-full" required
                    maxlength="30" placeholder="Ej. +56 9 1234 5678" :value="old('cliente_telefono')" />
                <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-2" />
            </div>
        </x-seccion>

        {{-- Datos comunes del lote --}}
        <x-seccion titulo="Datos comunes (para todas las máquinas)"
             x-data="{ cond: @js(old('facturacion', '')) }">
            <div>
                <x-input-label for="tipo_default">Tipo de equipo <span class="text-red-500">*</span></x-input-label>
                <x-select id="tipo_default" name="tipo_default" class="mt-1.5" required x-model="tipoDefault">
                    @foreach ($tipos as $t)
                        <option value="{{ $t }}" @selected(old('tipo_default', 'dispensador') === $t)>{{ \App\Models\OrdenServicio::etiquetaTipo($t) }}</option>
                    @endforeach
                </x-select>
                <x-input-hint>Si una máquina es de otro tipo, lo cambias en su tarjeta.</x-input-hint>
                <x-input-error :messages="$errors->get('tipo_default')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="facturacion">Condición <span class="text-red-500">*</span></x-input-label>
                <x-select id="facturacion" name="facturacion" class="mt-1.5" required x-model="cond">
                    <option value="">— Selecciona —</option>
                    @foreach ($facturaciones as $f)
                        <option value="{{ $f }}" @selected(old('facturacion') === $f)>{{ ucfirst($f) }}</option>
                    @endforeach
                </x-select>
                <x-input-hint>Garantía: no se cobra (si está vigente). Reparación: se cobra.</x-input-hint>
                <x-input-error :messages="$errors->get('facturacion')" class="mt-2" />
            </div>

            {{-- Documento de compra: solo si es garantía (uno para el lote). --}}
            <div x-show="cond === 'garantia'" x-cloak x-transition class="space-y-4 rounded-xl bg-neutral-50 p-3">
                <div>
                    <x-input-label for="garantia_doc_tipo">Documento <span class="text-red-500">*</span></x-input-label>
                    <x-select id="garantia_doc_tipo" name="garantia_doc_tipo" class="mt-1.5" x-bind:required="cond === 'garantia'">
                        <option value="">— Selecciona —</option>
                        @foreach ($garantiaDocTipos as $d)
                            <option value="{{ $d }}" @selected(old('garantia_doc_tipo') === $d)>{{ ucfirst($d) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('garantia_doc_tipo')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="garantia_doc_numero">N° del documento <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="garantia_doc_numero" name="garantia_doc_numero" type="text" class="mt-1.5 w-full"
                        maxlength="191" :value="old('garantia_doc_numero')" x-bind:required="cond === 'garantia'" />
                    <x-input-error :messages="$errors->get('garantia_doc_numero')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="garantia_doc_fecha">Fecha de compra <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="garantia_doc_fecha" name="garantia_doc_fecha" type="date" class="mt-1.5 w-full"
                        :value="old('garantia_doc_fecha')" x-bind:required="cond === 'garantia'" />
                    <x-input-error :messages="$errors->get('garantia_doc_fecha')" class="mt-2" />
                </div>
            </div>

        </x-seccion>

        {{-- Máquinas --}}
        {{-- Acordeón por máquina: con 3 equipos la pantalla del celular se hacía
             larguísima y el cliente perdía de vista dónde iba. Solo una tarjeta
             abierta a la vez; las demás quedan como una línea de resumen
             (✓ Máquina 1 · SN-1234).

             El estado vive ACÁ y no en el componente `loteServicioForm` de
             app.js porque ese componente lo comparte el formulario del conductor
             (admin/servicio-tecnico/lote), que muestra sus filas siempre
             abiertas y no debe cambiar.

             `invalid.capture` es el candado: una tarjeta plegada esconde campos
             `required`, y un campo required oculto NO se puede enfocar, así que
             el navegador aborta el envío sin decir nada. Al primer campo
             inválido abrimos su tarjeta y lo enfocamos. El evento `invalid` no
             burbujea: por eso se escucha en fase de captura. --}}
        <div class="sm:rounded-2xl sm:border sm:border-neutral-200 sm:bg-white sm:p-4 sm:shadow-sm"
             x-data="{
                abierta: 0,
                completa(m) {
                    return !!((m.modelo || '').trim() && (m.falla_reportada || '').trim() && m.foto_1 && m.foto_2);
                },
                resumen(m) {
                    return [m.numero_serie, m.modelo].map(v => (v || '').trim()).filter(Boolean).join(' · ');
                },
                {{-- El navegador dispara `invalid` en TODOS los campos inválidos de la
                     pasada, en orden del documento — no solo en el primero. Quedarse con
                     el último abría la ÚLTIMA máquina en vez del primer problema
                     (verificado: con 3 máquinas vacías abría la 3). Así que se guarda el
                     PRIMERO de la pasada y se actúa una sola vez, al final.

                     El aplazamiento es `setTimeout` y no `queueMicrotask` a propósito: al
                     volver de cada listener la pila de JS queda vacía y el navegador drena
                     los microtasks, así que un candado en microtask se limpiaba entre
                     evento y evento y no filtraba nada (probado). Un timeout corre en una
                     tarea nueva, ya terminada toda la ráfaga. --}}
                primerInvalido: null,
                pasadaProgramada: false,
                abrirPara(el) {
                    if (! this.primerInvalido) this.primerInvalido = el;
                    if (this.pasadaProgramada) return;
                    this.pasadaProgramada = true;

                    setTimeout(() => {
                        const campo = this.primerInvalido;
                        this.primerInvalido = null;
                        this.pasadaProgramada = false;

                        {{-- Si el primer campo con problemas está fuera de las tarjetas
                             (los datos del cliente, arriba), no se toca el acordeón: ese
                             campo está a la vista y el navegador ya lo enfoca solo. --}}
                        const tarjeta = campo && campo.closest('[data-maquina]');
                        if (! tarjeta) return;
                        this.abierta = Number(tarjeta.dataset.maquina);
                        this.$nextTick(() => campo.focus());
                    }, 0);
                },
             }"
             x-on:invalid.capture="abrirPara($event.target)">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                    Máquinas (<span x-text="maquinas.length"></span>)
                </h2>
                <x-agregar-fila-button x-on:click="agregar(); abierta = maquinas.length - 1">Agregar máquina</x-agregar-fila-button>
            </div>

            <div class="space-y-3">
                <template x-for="(m, i) in maquinas" :key="i">
                    <div :data-maquina="i" class="rounded-xl border"
                         :class="abierta === i ? 'border-brand-300 bg-white' : 'border-neutral-200 bg-neutral-50'">
                        {{-- Cabecera tocable: abre/cierra esta máquina. min-h-11 = 44px,
                             el mínimo táctil, porque es el control que gobierna la tarjeta. --}}
                        <div class="flex items-center gap-1 px-3">
                            <button type="button" x-on:click="abierta = (abierta === i ? null : i)"
                                :aria-expanded="abierta === i ? 'true' : 'false'"
                                class="flex min-h-11 min-w-0 flex-1 items-center gap-2 py-2 text-left">
                                <x-icon.chevron-right class="h-4 w-4 shrink-0 text-neutral-400 transition-transform duration-150"
                                    x-bind:class="abierta === i ? 'rotate-90' : ''" />
                                <span class="shrink-0 text-xs font-semibold text-neutral-500">
                                    Máquina <span x-text="i + 1"></span> de <span x-text="maquinas.length"></span>
                                </span>
                                {{-- ✓ solo cuando de verdad no falta nada de ESTA máquina
                                     (la serie cuenta únicamente si su tipo la exige). --}}
                                <x-icon.check class="h-4 w-4 shrink-0 text-green-600"
                                    x-show="completa(m) && (! serieObligatoria(m) || (m.numero_serie || '').trim())" x-cloak />
                                <span x-show="abierta !== i" x-text="resumen(m)"
                                      class="truncate text-xs text-neutral-500"></span>
                            </button>
                            <button type="button" x-on:click="quitar(i); abierta = Math.min(i, maquinas.length - 1)"
                                class="shrink-0 rounded-lg p-2.5 text-neutral-400 hover:bg-red-50 hover:text-red-600" title="Quitar">
                                <x-icon.trash class="h-4 w-4" />
                            </button>
                        </div>

                        <div x-show="abierta === i" x-cloak class="border-t border-neutral-100 p-2.5 sm:p-3">

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">Tipo</label>
                                <select x-model="m.tipo" :name="`maquinas[${i}][tipo]`"
                                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-base sm:text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    <option value="">Igual que arriba</option>
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t }}">{{ \App\Models\OrdenServicio::etiquetaTipo($t) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">
                                    N° de serie <span x-show="serieObligatoria(m)" class="text-red-500">*</span>
                                </label>
                                <input type="text" x-model="m.numero_serie" :name="`maquinas[${i}][numero_serie]`" maxlength="191"
                                    x-bind:required="serieObligatoria(m)"
                                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-base sm:text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                            </div>
                        </div>

                        {{-- Equipo (marca/modelo): texto libre OBLIGATORIO. Sin catálogo:
                             gerencia no quiere exponerle al cliente la variedad de productos. --}}
                        <div class="mt-2">
                            <label class="mb-0.5 block text-xs text-neutral-500">Equipo (marca y modelo) <span class="text-red-500">*</span></label>
                            <input type="text" x-model="m.modelo" :name="`maquinas[${i}][modelo]`" maxlength="191" required
                                placeholder="Ej. Dispensador LB-16 blanco"
                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-base sm:text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                        </div>

                        {{-- Falla y estado de ESTA máquina (golpes, rayas, caja, piezas). --}}
                        <div class="mt-2">
                            <label class="mb-0.5 block text-xs text-neutral-500">Falla y estado del equipo <span class="text-red-500">*</span></label>
                            <textarea :name="`maquinas[${i}][falla_reportada]`" x-model="m.falla_reportada" rows="2" required
                                placeholder="Ej. No enfría. Golpeada en tapa lateral, sin caja, le falta la llave roja."
                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-base sm:text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"></textarea>
                        </div>

                        {{-- 2 fotos de respaldo (obligatorias, como el ingreso por unidad) --}}
                        <div class="mt-2 grid grid-cols-1 gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">Foto 1 del equipo <span class="text-red-500">*</span></label>
                                {{-- El x-on:change solo anota que la foto ya está elegida (para el ✓
                                     del resumen); la compresión sigue en el onchange de siempre. --}}
                                <x-archivo-input ::name="`maquinas[${i}][fotos][]`" accept="image/*" capture="environment" required
                                    onchange="optimizarFotoInput(this)"
                                    x-on:change="m.foto_1 = $event.target.files.length > 0"
                                    texto="Tomar o elegir la foto"
                                    vacio="Todavía no elegiste esta foto" />
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">Foto 2 del equipo <span class="text-red-500">*</span></label>
                                <x-archivo-input ::name="`maquinas[${i}][fotos][]`" accept="image/*" capture="environment" required
                                    onchange="optimizarFotoInput(this)"
                                    x-on:change="m.foto_2 = $event.target.files.length > 0"
                                    texto="Tomar o elegir la foto"
                                    vacio="Todavía no elegiste esta foto" />
                            </div>
                        </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <x-barra-envio-movil>Enviar ingreso</x-barra-envio-movil>
        <p class="text-center text-xs text-neutral-400">Al enviar, muéstrale la pantalla al encargado del mostrador.</p>

        {{-- Volver a la pantalla principal (elegir por unidad / visita industrial).
             Secundario para no competir con el envío. --}}
        @if (! empty($urlInicio))
            <a href="{{ $urlInicio }}"
               class="block w-full rounded-xl border border-neutral-300 bg-white px-5 py-3 text-center text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                Volver al inicio
            </a>
        @endif
    </form>
</x-guest-layout>
