{{--
    Codigos QR del mostrador (P-M12-01, piloto). Un QR por sucursal: cada uno
    apunta al link FIRMADO del formulario publico con su sucursal_id. El QR se
    dibuja en el cliente (canvas[data-qr] -> app.js, import dinamico de 'qrcode').
    Pensada para imprimir y pegar en el mostrador.
--}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Códigos QR" subtitle="Imprime y pega uno en el mostrador de cada sucursal.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-primary-button type="button" onclick="window.print()">Imprimir</x-primary-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <p class="mb-6 text-sm text-neutral-500 print:hidden">
                Cada código lleva embebida su sucursal. El cliente lo escanea con su celular, llena el
                formulario y tú confirmas la recepción desde el listado de Servicio Técnico.
            </p>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                @forelse ($sucursales as $item)
                    <div class="flex flex-col items-center rounded-2xl border border-neutral-200 bg-white p-6 text-center shadow-sm print:break-inside-avoid">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 font-black text-white">D</span>
                        <h3 class="mt-3 text-lg font-bold text-neutral-900">{{ $item['sucursal']->nombre }}</h3>
                        <p class="text-sm text-neutral-500">Servicio Técnico · Escanéame para ingresar tu equipo</p>

                        <div class="mt-4 rounded-xl border border-neutral-200 p-3">
                            <canvas data-qr="{{ $item['url'] }}" width="224" height="224" class="h-56 w-56"></canvas>
                        </div>

                        <div class="mt-4 w-full print:hidden">
                            <input type="text" readonly value="{{ $item['url'] }}"
                                   class="w-full truncate rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-xs text-neutral-500"
                                   onclick="this.select()">
                            <button type="button"
                                    class="mt-2 text-xs font-medium text-brand-600 hover:text-brand-700"
                                    onclick="navigator.clipboard.writeText('{{ $item['url'] }}'); this.textContent='¡Copiado!';">
                                Copiar link
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-neutral-500">No hay sucursales activas.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
