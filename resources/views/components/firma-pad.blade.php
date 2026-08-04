{{--
    Pad de firma manuscrita (P-DSP-05). Canvas vanilla con pointer events —
    sin dependencia npm (la regla del hosting exige import dinámico + build
    commiteado para libs nuevas, y el suavizado bézier de signature_pad no se
    necesita para una firma de recepción).

    Requiere estar DENTRO de un x-data que tenga registrado Alpine.data
    'firmaPad' (resources/js/app.js) o cualquier x-data padre: el componente
    declara el suyo propio. Expone su Blob vía el evento 'firma-lista' y el
    estado `firmado` para gatear el submit.

    Uso: <x-firma-pad />  y el padre escucha  @firma-cambio.window  o consulta
    window.dgFirmaPad(el) — ver entregaForm en app.js.
--}}
<div x-data="firmaPad()" data-firma-pad {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <p class="text-sm font-medium text-neutral-700">Firma de quien recibe *</p>

    {{-- touch-action: none — sin esto el navegador scrollea la página en vez
         de dibujar (el gesto de firmar ES un pan para el browser). --}}
    <div class="overflow-hidden rounded-lg border border-neutral-300 bg-white">
        <canvas x-ref="lienzo" width="600" height="300"
                class="block h-40 w-full touch-none"
                x-on:pointerdown="empezar($event)"
                x-on:pointermove="trazar($event)"
                x-on:pointerup="soltar($event)"
                x-on:pointercancel="soltar($event)"></canvas>
    </div>

    <div class="flex items-center justify-between">
        <p class="text-xs text-neutral-500" x-show="! firmado">Dibuja la firma con el dedo.</p>
        <p class="text-xs text-neutral-500" x-show="firmado" x-cloak>Listo. Puedes rehacerla si quedó mal.</p>
        <button type="button" x-on:click="limpiar()"
                class="inline-flex h-12 items-center rounded-lg border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-700 shadow-sm transition duration-150 hover:bg-neutral-50 active:scale-[0.98]">
            Limpiar
        </button>
    </div>
</div>
