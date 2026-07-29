{{--
    QR del despacho para imprimir y pegar en la carga (P-DSP-04). El QR apunta al
    link FIRMADO del escaneo con el código embebido; lo dibuja en el cliente
    `canvas[data-qr]` → dibujarQrsMostrador de app.js (chunk 'qrcode' ya en el
    bundle desde M12), sin dependencia nueva de servidor.
--}}
<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'QR del despacho '.$despacho->codigo"
                       subtitle="Imprime esta hoja y pégala en la carga. En bodega se escanea para autorizar el retiro."
                       :back="route('admin.despachos.index')" backTitle="Volver a despachos">
            <x-slot name="action">
                <x-primary-button type="button" onclick="window.print()">Imprimir</x-primary-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="flex flex-col items-center rounded-2xl border border-neutral-200 bg-white p-6 text-center shadow-sm print:break-inside-avoid sm:p-8">
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-black text-white">D</span>

            <p class="mt-4 text-3xl font-semibold tracking-tight text-neutral-900">{{ $despacho->codigo }}</p>
            <p class="mt-1 text-sm text-neutral-500">
                Folio {{ $despacho->documento?->folio ?? '—' }}
                · {{ $despacho->documento?->cliente?->razon_social ?? 'Sin cliente' }}
                @if ($despacho->zona)
                    · Zona {{ $despacho->zona->nombre }}
                @endif
            </p>

            <div class="mt-5 rounded-xl border border-neutral-200 p-3">
                <canvas data-qr="{{ $url }}" width="224" height="224" class="h-56 w-56"></canvas>
            </div>

            <p class="mt-4 max-w-sm text-xs text-neutral-500">
                Escanéalo desde el puesto de bodega. Solo un operador con permiso de despachos puede
                autorizar el retiro: si este código ya se usó, la pantalla avisa en vez de dejarlo pasar.
            </p>

            <div class="mt-5 w-full print:hidden">
                <input type="text" readonly value="{{ $url }}"
                       class="w-full truncate rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-xs text-neutral-500"
                       onclick="this.select()">
            </div>
        </div>
    </div>
</x-app-layout>
