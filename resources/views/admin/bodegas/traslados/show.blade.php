<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Orden de traslado #'.$traslado->id"
                       :subtitle="$traslado->bodega->nombre.' → '.$traslado->destino->nombre"
                       :back="route('admin.bodegas.show', $traslado->bodega_id)" backTitle="Volver a la bodega">
            <x-slot name="action">
                {{-- La orden que viaja a bodega: se GENERA al momento desde la
                     foto guardada. Sin @can: el gate es el middleware de la ruta. --}}
                <x-secondary-button-link :href="route('admin.bodegas.traslados.excel', $traslado)"
                                         title="Descarga la orden de traslado en Excel">
                    <x-icon.document-text class="h-4 w-4" />
                    <span class="hidden sm:inline">Descargar Excel</span>
                    <span class="sm:hidden">Excel</span>
                </x-secondary-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Orden de traslado por baja de bodega</h3>
                @if ($traslado->estado === \App\Models\BodegaTraslado::PENDIENTE)
                    <x-badge variant="brand">pendiente</x-badge>
                @elseif ($traslado->estado === \App\Models\BodegaTraslado::COMPLETADO)
                    <x-badge variant="neutral">completada</x-badge>
                @else
                    <x-badge variant="neutral">anulada</x-badge>
                @endif
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-neutral-400">Origen (en baja)</dt>
                    <dd class="text-sm font-medium text-neutral-900">{{ $traslado->bodega->nombre }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Destino</dt>
                    <dd class="text-sm font-medium text-neutral-900">{{ $traslado->destino->nombre }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Solicitada por</dt>
                    <dd class="text-sm text-neutral-900">{{ $traslado->solicitante_nombre }}</dd>
                    <dd class="text-xs text-neutral-500">{{ $traslado->created_at?->enChile()->format('d-m-Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Cierre</dt>
                    @if ($traslado->completado_at)
                        <dd class="text-sm text-neutral-900">Completada el {{ $traslado->completado_at->enChile()->format('d-m-Y H:i') }}</dd>
                        <dd class="text-xs text-neutral-500">El espejo confirmó stock 0 y la baja se completó sola.</dd>
                    @elseif ($traslado->anulado_at)
                        <dd class="text-sm text-neutral-900">Anulada el {{ $traslado->anulado_at->enChile()->format('d-m-Y H:i') }}</dd>
                    @else
                        <dd class="text-sm text-neutral-900">Se completa sola cuando el espejo confirme stock 0.</dd>
                        <dd class="text-xs text-neutral-500">El traslado físico se ejecuta en Bsale; el espejo se refresca cada 15 minutos.</dd>
                    @endif
                </div>
            </dl>
        </div>

        @if ($traslado->aviso_stock_nuevo_at !== null && $traslado->esPendiente())
            <div class="rounded-2xl border-2 border-red-300 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-800">Llegó stock nuevo a la bodega en baja</p>
                <p class="mt-1 text-sm text-red-700">
                    El {{ $traslado->aviso_stock_nuevo_at->enChile()->format('d-m-Y H:i') }} el espejo detectó existencias por
                    encima de la foto de esta orden. La bodega sigue en baja: traslada también lo nuevo, o anula la orden si
                    la bodega debe volver a operar.
                </p>
            </div>
        @endif

        {{-- La FOTO: lo que había al momento de pedir la baja. No cambia
             aunque el stock siga moviéndose — es el documento de trabajo. --}}
        <x-list-card title="Foto de existencias a trasladar" :count="$traslado->items->count()"
                     :countLabel="\Illuminate\Support\Str::plural('producto', $traslado->items->count())">
            @foreach ($traslado->items as $item)
                <x-list-row>
                    <p class="truncate font-medium text-neutral-900">{{ $item->nombre }}</p>
                    <p class="truncate text-sm text-neutral-500">{{ $item->sku }}</p>
                    <x-slot name="meta">
                        <div class="text-sm sm:w-28 sm:shrink-0 sm:text-right">
                            <p class="font-medium tabular-nums text-neutral-900">{{ \App\Models\Stock::formatear($item->cantidad) }}</p>
                        </div>
                    </x-slot>
                </x-list-row>
            @endforeach
        </x-list-card>

        @if ($traslado->esPendiente())
            <form method="POST" action="{{ route('admin.bodegas.traslados.anular', $traslado) }}"
                  onsubmit="return confirm({{ Illuminate\Support\Js::from('¿Anular la orden #'.$traslado->id.'? '.$traslado->bodega->nombre.' volverá a operación.') }});"
                  class="flex justify-end">
                @csrf
                <x-secondary-button type="submit">Anular orden (la bodega vuelve a operación)</x-secondary-button>
            </form>
        @endif
    </div>
</x-app-layout>
