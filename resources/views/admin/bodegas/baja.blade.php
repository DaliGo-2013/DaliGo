<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Dar de baja" :subtitle="$bodega->nombre"
                       :back="route('admin.bodegas.show', $bodega)" backTitle="Volver a la bodega" />
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        @if ($conStock->isEmpty())
            {{-- Estado 1: vacía según el espejo → baja inmediata. --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <h3 class="text-sm font-semibold text-neutral-900">La bodega está vacía</h3>
                <p class="mt-2 text-sm text-neutral-600">
                    El espejo de Bsale no registra existencias en <span class="font-medium text-neutral-700">{{ $bodega->nombre }}</span>:
                    la baja es inmediata. La bodega no se borra (su historial queda) — desaparece de las pantallas
                    operativas y queda marcada «en baja» en este listado.
                </p>
                <form method="POST" action="{{ route('admin.bodegas.baja.store', $bodega) }}" class="mt-4">
                    @csrf
                    <x-danger-button class="h-12 w-full justify-center sm:h-auto sm:w-auto">
                        Dar de baja ahora
                    </x-danger-button>
                </form>
            </div>
        @else
            {{-- Estado 2: con stock → el sistema OBLIGA a decidir a dónde va. --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <h3 class="text-sm font-semibold text-neutral-900">La bodega tiene existencias: primero se trasladan</h3>
                <p class="mt-2 text-sm text-neutral-600">
                    Dar de baja jamás pierde stock. Elige la bodega de destino y se creará una
                    <span class="font-medium text-neutral-700">orden de traslado</span> con la foto de lo que hay ahora;
                    {{ $bodega->nombre }} queda en baja pendiente (fuera de la operación) y
                    <span class="font-medium text-neutral-700">la baja se completa sola</span> cuando el espejo confirme stock 0.
                    El movimiento físico se ejecuta en Bsale, como siempre.
                </p>
            </div>

            <x-list-card title="Lo que hay que trasladar" :count="$conStock->count()"
                         :countLabel="\Illuminate\Support\Str::plural('producto', $conStock->count())">
                @foreach ($conStock as $stock)
                    <x-list-row>
                        <p class="truncate font-medium text-neutral-900">{{ $stock->producto_nombre }}</p>
                        <p class="truncate text-sm text-neutral-500">{{ $stock->producto_sku }}</p>
                        <x-slot name="meta">
                            <div class="text-sm sm:w-48 sm:shrink-0 sm:text-right">
                                <p class="font-medium text-neutral-900">{{ \App\Models\Stock::formatear($stock->stock_real) }} reales</p>
                                <p class="text-xs text-neutral-500">{{ \App\Models\Stock::formatear($stock->stock_disponible) }} disp. · {{ \App\Models\Stock::formatear($stock->stock_reservado) }} reserv.</p>
                            </div>
                        </x-slot>
                    </x-list-row>
                @endforeach
            </x-list-card>

            <form method="POST" action="{{ route('admin.bodegas.baja.store', $bodega) }}"
                  class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                @csrf
                <div>
                    <x-input-label for="bodega_destino_id" value="Bodega de destino" />
                    <x-select id="bodega_destino_id" name="bodega_destino_id" class="mt-1.5" required>
                        <option value="" disabled selected>Elige a dónde va el stock…</option>
                        @foreach ($destinos as $destino)
                            <option value="{{ $destino->id }}" @selected((string) old('bodega_destino_id') === (string) $destino->id)>{{ $destino->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-hint>Solo bodegas en operación. El destino recibirá lo listado arriba.</x-input-hint>
                    <x-input-error :messages="$errors->get('bodega_destino_id')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-danger-button class="h-12 w-full justify-center sm:h-auto sm:w-auto">
                        Crear orden de traslado y dejar en baja pendiente
                    </x-danger-button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
