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
            {{-- El navegador no permite repoblar un <input type=file>: los datos escritos
                 vuelven (old('maquinas')), las fotos no. Decirlo aquí evita que el cliente
                 envíe creyendo que las fotos siguen adjuntas. --}}
            @if (old('maquinas'))
                <span class="mt-1 block font-medium">Tus datos siguen abajo, pero tienes que volver a adjuntar las fotos.</span>
            @endif
        </div>
    @endif

    {{-- `inicial` repuebla las tarjetas de máquina tras un error de validación. --}}
    <form method="POST" action="{{ route('ingreso-taller.lote.store') }}" enctype="multipart/form-data" class="space-y-5" data-una-vez
          x-data="loteServicioForm({ tipoDefault: @js(old('tipo_default', 'dispensador')), tiposSerie: @js(\App\Models\OrdenServicio::SERIE_OBLIGATORIA_TIPOS), inicial: @js(array_values(old('maquinas', []))) })">
        @csrf
        <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">
        {{-- Honeypot anti-bot (un humano no lo ve ni lo llena). --}}
        <div class="hidden" aria-hidden="true">
            <label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- Tus datos (una sola vez) --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm space-y-4">
            <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Tus datos (una sola vez)</h2>
            <div>
                <x-input-label for="cliente_nombre">Nombre y apellido <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('cliente_nombre')" />
                <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cliente_rut">RUT <span class="text-red-500">*</span></x-input-label>
                {{-- inputmode numeric (no type=tel): el teclado telefónico de iOS trae
                     + * # pero NO el guión que lleva todo RUT. --}}
                <x-text-input id="cliente_rut" name="cliente_rut" type="text" inputmode="numeric"
                    autocomplete="off" class="mt-1.5 w-full" required
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
        </div>

        {{-- Datos comunes del lote --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm space-y-4"
             x-data="{ cond: @js(old('facturacion', '')) }">
            <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Datos comunes (para todas las máquinas)</h2>
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
                    <x-text-input id="garantia_doc_numero" name="garantia_doc_numero" type="text" inputmode="numeric"
                        class="mt-1.5 w-full"
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

        </div>

        {{-- Máquinas --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                    Máquinas (<span x-text="maquinas.length"></span>)
                </h2>
                <x-agregar-fila-button x-on:click="agregar()">Agregar máquina</x-agregar-fila-button>
            </div>

            <div class="space-y-3">
                <template x-for="(m, i) in maquinas" :key="i">
                    <div class="rounded-xl border border-neutral-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-neutral-500">Máquina <span x-text="i + 1"></span></span>
                            {{-- 28px sin confirmación era la peor relación tamaño/consecuencia
                                 del sistema: un toque al azar borraba la máquina entera con sus
                                 dos fotos ya tomadas. x-icon-button da 44px en móvil. --}}
                            <x-icon-button variant="danger" title="Quitar" label="Quitar esta máquina"
                                x-on:click="confirm('¿Quitar esta máquina? Vas a perder sus fotos.') && quitar(i)">
                                <x-icon.trash class="h-4 w-4" />
                            </x-icon-button>
                        </div>

                        {{-- 1 columna en celular: a 375px este grid dejaba 105px por campo
                             y el <select> mostraba "Igual que a…" — el cliente no alcanzaba
                             a leer qué tipo eligió, ni el N° de serie que estaba tecleando. --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">Tipo</label>
                                <select x-model="m.tipo" :name="`maquinas[${i}][tipo]`"
                                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
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
                                    class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                            </div>
                        </div>

                        {{-- Equipo (marca/modelo): texto libre OBLIGATORIO. Sin catálogo:
                             gerencia no quiere exponerle al cliente la variedad de productos. --}}
                        <div class="mt-2">
                            <label class="mb-0.5 block text-xs text-neutral-500">Equipo (marca y modelo) <span class="text-red-500">*</span></label>
                            <input type="text" x-model="m.modelo" :name="`maquinas[${i}][modelo]`" maxlength="191" required
                                placeholder="Ej. Dispensador LB-16 blanco"
                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                        </div>

                        {{-- Falla y estado de ESTA máquina (golpes, rayas, caja, piezas). --}}
                        <div class="mt-2">
                            <label class="mb-0.5 block text-xs text-neutral-500">Falla y estado del equipo <span class="text-red-500">*</span></label>
                            {{-- rows=3: con 2 el placeholder de ejemplo se cortaba a media
                                 línea y se veía como texto roto en celular. --}}
                            <textarea :name="`maquinas[${i}][falla_reportada]`" x-model="m.falla_reportada" rows="3" required
                                placeholder="Ej. No enfría. Golpeada en tapa lateral, sin caja, le falta la llave roja."
                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"></textarea>
                        </div>

                        {{-- 2 fotos de respaldo (obligatorias, como el ingreso por unidad) --}}
                        <div class="mt-2 grid grid-cols-1 gap-2">
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">Foto 1 del equipo <span class="text-red-500">*</span></label>
                                <input type="file" :name="`maquinas[${i}][fotos][]`" accept="image/*" required
                                    onchange="optimizarFotoInput(this)"
                                    class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-700">
                            </div>
                            <div>
                                <label class="mb-0.5 block text-xs text-neutral-500">Foto 2 del equipo <span class="text-red-500">*</span></label>
                                <input type="file" :name="`maquinas[${i}][fotos][]`" accept="image/*" required
                                    onchange="optimizarFotoInput(this)"
                                    class="block w-full text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-neutral-700">
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <x-primary-button class="w-full justify-center py-3 text-base">Enviar ingreso</x-primary-button>
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
