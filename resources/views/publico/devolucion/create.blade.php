{{--
    Formulario PÚBLICO de devolución (M13 · P-M13-01, flujo A-12). Sin login:
    el cliente llega por el QR/link firmado (la sucursal viene embebida en la
    firma). Declara qué devuelve, por qué canal compró, el motivo, y adjunta
    las fotos de evidencia — las de bodega se toman aparte al recibir (los DOS
    momentos, decisión del dueño 30-07).

    El POST va FIRMADO (variante endurecida de PLAN-M13): el action del form
    es $urlStore, generado con URL::signedRoute. Reglas de operario: campos
    mínimos, canal por chips tocables, fotos con cámara directa y compresión
    en el navegador (obligatoria: una foto de 12MP revienta GD en el server).
--}}
<x-guest-layout>
    <div class="pb-[calc(6rem_+_env(safe-area-inset-bottom))] sm:pb-0">
        <div class="mb-6 text-center">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900">Devolución de producto</h1>
            <p class="mt-1 text-sm text-neutral-500">
                Sucursal <span class="font-medium text-neutral-700">{{ $sucursal->nombre }}</span>
            </p>
        </div>

        <p class="mb-5 text-center text-sm text-neutral-500">
            Cuéntanos qué producto devuelves y por qué. Al enviar recibirás un folio para hacer seguimiento.
        </p>

        <form method="POST" action="{{ $urlStore }}" class="space-y-5" enctype="multipart/form-data" data-una-vez>
            @csrf

            {{-- Honeypot anti-bot: invisible para personas, tentador para bots. --}}
            <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; height:0; overflow:hidden;">
                <label for="sitio_web">No llenar</label>
                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
            </div>

            <x-seccion titulo="Tus datos">
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
                    <x-input-hint>Ahí te avisaremos el resultado de tu devolución.</x-input-hint>
                    <x-input-error :messages="$errors->get('cliente_email')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cliente_telefono" value="Teléfono" />
                    <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 block w-full"
                                  :value="old('cliente_telefono')" placeholder="+56 9 …" />
                    <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cliente_rut" value="RUT" />
                    {{-- SIN inputmode numérico: el DV puede ser K y el teclado
                         numérico de iOS no la tiene (bitácora 28-07, RutConKTest). --}}
                    <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 block w-full"
                                  autocapitalize="characters" :value="old('cliente_rut')" placeholder="12.345.678-9" />
                    <x-input-error :messages="$errors->get('cliente_rut')" class="mt-1.5" />
                </div>
            </x-seccion>

            <x-seccion titulo="Tu compra">
                <div>
                    <x-input-label value="¿Dónde compraste? *" />
                    <div class="mt-1.5 grid grid-cols-2 gap-2">
                        @foreach ($canales as $valor => $label)
                            <x-chip-radio name="canal" :value="$valor" :label="$label"
                                          :checked="old('canal') === $valor" required />
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('canal')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="folio_referencia" value="N° de boleta, factura u orden (si lo tienes)" />
                    <x-text-input id="folio_referencia" name="folio_referencia" type="text" class="mt-1.5 block w-full"
                                  :value="old('folio_referencia')" placeholder="Ej: 123456" />
                    <x-input-error :messages="$errors->get('folio_referencia')" class="mt-1.5" />
                </div>
            </x-seccion>

            <x-seccion titulo="El producto">
                <div>
                    <x-input-label for="producto" value="¿Qué producto devuelves? *" />
                    <x-text-input id="producto" name="producto" type="text" class="mt-1.5 block w-full"
                                  :value="old('producto')" required placeholder="Ej: Dispensador de agua XYZ" />
                    <x-input-error :messages="$errors->get('producto')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="cantidad" value="Cantidad *" />
                    <x-text-input id="cantidad" name="cantidad" type="number" min="1" max="999" class="mt-1.5 block w-24"
                                  :value="old('cantidad', 1)" required />
                    <x-input-error :messages="$errors->get('cantidad')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="motivo" value="¿Por qué lo devuelves? *" />
                    <x-textarea id="motivo" name="motivo" rows="3" class="mt-1.5 block w-full"
                                required placeholder="Cuéntanos qué pasó: llegó dañado, no funciona, no era lo que pediste…">{{ old('motivo') }}</x-textarea>
                    <x-input-error :messages="$errors->get('motivo')" class="mt-1.5" />
                </div>
            </x-seccion>

            <x-seccion titulo="Fotos del producto">
                <p class="text-sm text-neutral-500">
                    {{ $fotosMin }} fotos: el estado del producto y su embalaje. Son tu respaldo si el daño vino del transporte.
                </p>
                @for ($i = 1; $i <= $fotosMin; $i++)
                    <div>
                        <label for="foto_{{ $i }}" class="mb-1 block text-sm font-medium text-neutral-700">
                            Foto {{ $i }}{{ $i === 1 ? ' — el producto completo' : ' — el daño o detalle' }}
                        </label>
                        <x-archivo-input id="foto_{{ $i }}" name="fotos[]" accept="image/*" capture="environment" required
                            onchange="optimizarFotoInput(this)"
                            texto="Tomar o elegir la foto"
                            vacio="Todavía no elegiste la foto {{ $i }}" />
                        <x-input-error :messages="$errors->get('fotos.'.($i - 1))" class="mt-1.5" />
                    </div>
                @endfor
                <x-input-error :messages="$errors->get('fotos')" class="mt-1.5" />
            </x-seccion>

            <x-barra-envio-movil label="Enviar devolución" />
        </form>
    </div>
</x-guest-layout>
