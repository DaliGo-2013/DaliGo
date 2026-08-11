{{--
    UN documento del vehículo, a pantalla completa (pedido del dueño 11-08).

    Está diseñado para UNA escena: el conductor parado en un control de ruta,
    mostrando el permiso desde el teléfono. Por eso:

    · La IMAGEN es la protagonista y va primero — el carabinero no necesita ver
      un menú. Patente, documento y vencimiento van arriba en una sola línea,
      porque son la mitad del valor («vence el 30-09-2026»).
    · Tocar la imagen abre el JPEG pelado en el navegador: ahí el pellizco para
      agrandar es el NATIVO del teléfono (iPhone y Android), sin JS propio que
      mantener ni que pueda trabarse.
    · Pesa poco a propósito: la imagen viene comprimida del servidor
      (~100-250 KB) y viaja con Cache-Control privado, así que la segunda vez
      abre del caché del teléfono aunque la señal sea mala.
    · El historial va al final y PLEGADO: es respaldo («verlo todas las veces
      que uno quiera»), no parte de la escena del control.
--}}
<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="$vehiculo->ppu . ' · ' . $label"
                       :back="route('admin.vehiculos.show', $vehiculo)"
                       backTitle="Volver a la ficha del vehículo" />
    </x-slot>

    <div class="space-y-4 py-6">

        {{-- La línea del control: qué es, de qué patente y hasta cuándo vale. --}}
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-3">
            <div class="text-sm">
                <span class="font-semibold text-neutral-900">{{ $label }}</span>
                <span class="text-neutral-500">· {{ $vehiculo->ppu }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if ($ficha && $ficha['vence'])
                    <span class="text-sm tabular-nums text-neutral-600">vence {{ $ficha['vence']->format('d-m-Y') }}</span>
                @endif
                @if ($ficha)
                    <x-badge :variant="\App\Models\Vehiculo::variante($ficha['estado'])">
                        {{ \App\Models\Vehiculo::estadoDocumentalLabel($ficha['estado']) }}
                    </x-badge>
                @endif
            </div>
        </div>

        {{-- El documento. `loading` eager implícito (es el contenido principal);
             el <a> alrededor abre el JPEG directo para el zoom nativo.
             `max-h-[80vh]` y no un valor propio: es la clase que ya usa el visor
             del simulador, así que está en el CSS commiteado — una clase nueva
             obligaría a reconstruir `public/build` para esta vista (R-33). --}}
        <a href="{{ route('admin.vehiculos.documentos.archivo', $vigente) }}"
           class="block overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-900 shadow-sm"
           title="Tocar para abrir a pantalla completa (ahí podés agrandar con los dedos)">
            <img src="{{ route('admin.vehiculos.documentos.archivo', $vigente) }}"
                 alt="{{ $label }} de {{ $vehiculo->ppu }}"
                 class="mx-auto block max-h-[80vh] max-w-full">
        </a>
        <p class="text-center text-xs text-neutral-400">
            Tocá el documento para verlo a pantalla completa y agrandarlo con los dedos.
            Subido el {{ $vigente->created_at->format('d-m-Y') }}{{ $vigente->autor ? ' por '.$vigente->autor->name : '' }}
            · {{ $vigente->tamano_kb }} KB
        </p>

        {{-- Historial: las versiones anteriores quedan como respaldo, nunca se
             pisan. Plegado porque no es parte de la escena del control. --}}
        @if ($historial->isNotEmpty())
            <details class="rounded-xl border border-neutral-200 bg-white">
                <summary class="cursor-pointer select-none px-4 py-3 text-sm font-medium text-neutral-700">
                    Versiones anteriores ({{ $historial->count() }})
                </summary>
                <ul role="list" class="divide-y divide-neutral-100 border-t border-neutral-100 px-4">
                    @foreach ($historial as $version)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span class="text-sm text-neutral-600">
                                {{ $version->created_at->format('d-m-Y H:i') }}
                                {{ $version->autor ? '· '.$version->autor->name : '' }}
                            </span>
                            <a href="{{ route('admin.vehiculos.documentos.archivo', $version) }}"
                               class="min-h-8 inline-flex items-center text-sm font-medium text-brand-700 hover:text-brand-600">
                                Ver <span class="ml-1 font-normal text-neutral-400">· {{ $version->tamano_kb }} KB</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</x-app-layout>
