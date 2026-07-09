{{--
    Formulario PUBLICO de ingreso a servicio tecnico por QR (P-M12-01, piloto).
    Sin login. La sucursal viene fija desde el link firmado del QR. El cliente
    llena esto en su celular; el encargado confirma la recepcion despues.

    Paso previo: elegir CÓMO ingresar el producto.
      - Código de barras (PRÓXIMAMENTE): con la pistola lectora se autocompletará
        el producto/factura/garantía/dónde se compró; el cliente solo pondría sus
        datos. Hoy es una vista de preview para presentar el flujo (no envía).
      - Manual: el formulario actual (equipos antiguos sin código de barras).

    Si hay errores de validación (envío manual fallido) se abre directo el modo
    manual para que el cliente vea sus errores.
--}}
<x-guest-layout>
    <div x-data="{ modo: @js($errors->any() ? 'manual' : null) }">

        {{-- Encabezado (persistente en todos los pasos) --}}
        <div class="mb-6 text-center">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900">Ingreso a servicio técnico</h1>
            <p class="mt-1 text-sm text-neutral-500">
                Sucursal <span class="font-medium text-neutral-700">{{ $sucursal->nombre }}</span>
            </p>
        </div>

        {{-- ───────── PASO 1: ¿Cómo desea ingresar su producto? ───────── --}}
        <div x-show="modo === null" class="space-y-3">
            <p class="text-center text-sm text-neutral-500">¿Cómo desea ingresar su producto?</p>

            {{-- Opción A: código de barras — DESHABILITADA. Solo se muestra como
                 "Pronto"; NO es clicable (es un <div>, sin @click), así el cliente
                 no abre ninguna vista interna. --}}
            <div aria-disabled="true"
                class="block w-full cursor-not-allowed select-none rounded-2xl border border-neutral-200 bg-neutral-50 p-5 text-left opacity-75">
                <span class="flex items-center gap-2">
                    <span class="font-semibold text-neutral-500">Con código de barras</span>
                    <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-100">Pronto</span>
                </span>
                <span class="mt-1 block text-sm text-neutral-400">
                    Estará disponible cuando tengamos el lector de código de barras.
                </span>
            </div>

            {{-- Opción B: manual --}}
            <button type="button" @click="modo = 'manual'"
                class="block w-full rounded-2xl border border-neutral-200 bg-white p-5 text-left shadow-sm transition duration-150 hover:border-brand-300 hover:shadow active:scale-[0.99]">
                <span class="font-semibold text-neutral-900">Ingresar manualmente</span>
                <span class="mt-1 block text-sm text-neutral-500">
                    Para equipos sin código de barras (los más antiguos). Tú escribes los datos del equipo.
                </span>
            </button>
        </div>

        {{-- ───────── PASO 2: formulario manual (el actual) ───────── --}}
        <div x-show="modo === 'manual'" x-cloak>
            <p class="mb-5 text-center text-sm text-neutral-500">
                Completa los datos de tu equipo. Cuando termines, muéstrale la pantalla al encargado del mostrador.
            </p>

            <form method="POST" action="{{ route('ingreso-taller.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">

                {{-- Honeypot anti-bot: invisible para personas, tentador para bots. --}}
                <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; height:0; overflow:hidden;">
                    <label for="sitio_web">No llenar</label>
                    <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                </div>

                {{-- Datos del cliente --}}
                <div>
                    <x-input-label for="cliente_nombre" value="Nombre y apellido *" />
                    <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 block w-full"
                                  :value="old('cliente_nombre')" required placeholder="Tu nombre" />
                    <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cliente_email" value="Correo *" />
                    <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 block w-full"
                                  :value="old('cliente_email')" required placeholder="tu@correo.cl" />
                    <x-input-hint>Te llegará el detalle con el número de folio de tu ingreso.</x-input-hint>
                    <x-input-error :messages="$errors->get('cliente_email')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cliente_telefono" value="Teléfono *" />
                    <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 block w-full"
                                  :value="old('cliente_telefono')" required placeholder="Ej. +56 9 1234 5678" />
                    <x-input-hint>Para avisarte cuando tu equipo esté listo.</x-input-hint>
                    <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cliente_rut" value="RUT *" />
                    <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 block w-full"
                                  :value="old('cliente_rut')" required placeholder="Ej. 12.345.678-9" />
                    <x-input-error :messages="$errors->get('cliente_rut')" class="mt-1.5" />
                </div>

                <hr class="border-neutral-200">

                {{-- Datos del equipo --}}
                <div>
                    <x-input-label for="tipo_equipo" value="Tipo de equipo *" />
                    <x-select id="tipo_equipo" name="tipo_equipo" class="mt-1.5 block w-full">
                        @foreach ($tipos as $t)
                            <option value="{{ $t }}" @selected(old('tipo_equipo', 'dispensador') === $t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('tipo_equipo')" class="mt-1.5" />
                </div>

                {{-- Código del producto Dali: autocompletado contra el catálogo (SKU o
                     nombre). Opcional; escribe el código que trae el equipo. --}}
                <x-buscador-remoto
                    name="producto_id"
                    label="Código del equipo (producto Dali)"
                    chip="Producto"
                    :endpoint="route('ingreso-taller.buscar-producto')"
                    placeholder="Escribe el código (SKU) o el nombre…"
                    hint="Opcional. Búscalo por el código que trae el equipo (ej. LB-07)." />

                {{-- N° de serie: obligatorio solo para dispensador/lavadora (serie
                     unica); opcional para bombas/herramientas. El asterisco y el
                     'required' cambian en vivo segun el "Tipo de equipo". --}}
                <div x-data="{
                        serieObl: true,
                        tiposObl: @js(\App\Models\OrdenServicio::SERIE_OBLIGATORIA_TIPOS),
                        init() {
                            const sel = document.getElementById('tipo_equipo');
                            const set = () => { this.serieObl = !sel || this.tiposObl.includes(sel.value); };
                            set();
                            if (sel) sel.addEventListener('change', set);
                        },
                     }">
                    <x-input-label for="numero_serie">N° de serie <span x-show="serieObl" class="text-red-500">*</span></x-input-label>
                    <x-text-input id="numero_serie" name="numero_serie" type="text" class="mt-1.5 block w-full"
                                  :value="old('numero_serie')" required x-bind:required="serieObl" placeholder="El número que trae el equipo" />
                    {{-- Botón "Ver ejemplo del N° de serie" (foto de la etiqueta trasera), justo debajo del campo. --}}
                    <x-ayuda-serie />
                    <x-input-hint x-show="!serieObl" x-cloak>Opcional para este tipo (bombas y herramientas no tienen serie única).</x-input-hint>
                    <x-input-error :messages="$errors->get('numero_serie')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="fecha_ingreso" value="Fecha de ingreso" />
                    <x-text-input id="fecha_ingreso" type="date"
                                  class="mt-1.5 block w-full pointer-events-none bg-neutral-50 text-neutral-500"
                                  :value="now()->format('Y-m-d')" readonly tabindex="-1" />
                    <x-input-hint>Es la fecha de hoy.</x-input-hint>
                </div>

                {{-- Condición: garantía o reparación. Si elige Garantía, se despliegan
                     los datos del documento de compra que la respalda (igual que en el
                     mostrador). El cliente los indica; el mostrador los verifica. --}}
                <div x-data="{ cond: @js(old('facturacion', '')) }">
                    <x-input-label for="facturacion" value="Condición *" />
                    <x-select id="facturacion" name="facturacion" class="mt-1.5 block w-full" x-model="cond">
                        <option value="" disabled>— Selecciona —</option>
                        @foreach (\App\Models\OrdenServicio::FACTURACION as $f)
                            <option value="{{ $f }}">{{ ucfirst($f) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-hint>Garantía: equipo con garantía vigente (trae la boleta o factura). Reparación: fuera de garantía (tiene costo).</x-input-hint>
                    <x-input-error :messages="$errors->get('facturacion')" class="mt-1.5" />

                    {{-- Documento de compra: aparece solo si eligió Garantía. --}}
                    <div class="mt-3 rounded-lg border border-brand-200 bg-brand-50 p-4"
                         x-show="cond === 'garantia'" x-cloak x-transition>
                        <p class="mb-3 text-sm font-medium text-brand-700">
                            Documento de compra (respalda la garantía · 6 meses desde la compra)
                        </p>
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="garantia_doc_tipo" value="Documento" />
                                <x-select id="garantia_doc_tipo" name="garantia_doc_tipo" class="mt-1.5 block w-full"
                                    x-bind:required="cond === 'garantia'">
                                    <option value="">— Selecciona —</option>
                                    @foreach (\App\Models\OrdenServicio::GARANTIA_DOC_TIPOS as $gt)
                                        <option value="{{ $gt }}" @selected(old('garantia_doc_tipo') === $gt)>{{ ucfirst($gt) }}</option>
                                    @endforeach
                                </x-select>
                                <x-input-error :messages="$errors->get('garantia_doc_tipo')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="garantia_doc_numero" value="N° de documento" />
                                <x-text-input id="garantia_doc_numero" name="garantia_doc_numero" type="text" class="mt-1.5 block w-full"
                                    :value="old('garantia_doc_numero')" maxlength="191"
                                    x-bind:required="cond === 'garantia'" />
                                <x-input-error :messages="$errors->get('garantia_doc_numero')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="garantia_doc_fecha" value="Fecha de compra" />
                                <x-text-input id="garantia_doc_fecha" name="garantia_doc_fecha" type="date" class="mt-1.5 block w-full"
                                    :value="old('garantia_doc_fecha')"
                                    x-bind:required="cond === 'garantia'" />
                                <x-input-error :messages="$errors->get('garantia_doc_fecha')" class="mt-1.5" />
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <x-input-label for="falla_reportada" value="¿Qué le pasa al equipo? *" />
                    <x-textarea id="falla_reportada" name="falla_reportada" rows="4" class="mt-1.5 block w-full"
                                required placeholder="Cuéntanos la falla que notaste">{{ old('falla_reportada') }}</x-textarea>
                    <x-input-error :messages="$errors->get('falla_reportada')" class="mt-1.5" />
                </div>

                <x-primary-button class="w-full justify-center">
                    Enviar ingreso
                </x-primary-button>
            </form>

            <button type="button" @click="modo = null"
                class="mt-4 block w-full text-center text-sm text-neutral-400 underline hover:text-neutral-600">&larr; Elegir otra forma</button>
        </div>

    </div>
</x-guest-layout>
