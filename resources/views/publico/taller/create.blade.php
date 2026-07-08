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
                    <x-input-label for="cliente_telefono" value="Teléfono" />
                    <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 block w-full"
                                  :value="old('cliente_telefono')" placeholder="Ej. +56 9 1234 5678" />
                    <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cliente_rut" value="RUT" />
                    <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 block w-full"
                                  :value="old('cliente_rut')" placeholder="Ej. 12.345.678-9" />
                    <x-input-hint>Opcional.</x-input-hint>
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

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-input-label for="numero_serie">N° de serie *</x-input-label>
                        <x-ayuda-serie />
                    </div>
                    <x-text-input id="numero_serie" name="numero_serie" type="text" class="mt-1.5 block w-full"
                                  :value="old('numero_serie')" required placeholder="El número que trae el equipo" />
                    <x-input-error :messages="$errors->get('numero_serie')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="fecha_ingreso" value="Fecha de ingreso" />
                    <x-text-input id="fecha_ingreso" type="date"
                                  class="mt-1.5 block w-full pointer-events-none bg-neutral-50 text-neutral-500"
                                  :value="now()->format('Y-m-d')" readonly tabindex="-1" />
                    <x-input-hint>Es la fecha de hoy.</x-input-hint>
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
