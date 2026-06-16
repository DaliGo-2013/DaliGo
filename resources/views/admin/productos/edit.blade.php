<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Editar: '.$producto->nombre">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.productos.index')" form="producto-form" submitLabel="Guardar cambios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="producto-form" method="POST" action="{{ route('admin.productos.update', $producto) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.productos._form', ['producto' => $producto])
                </form>
            </div>

            @if (($stocks ?? collect())->isNotEmpty())
                <div class="mt-6 rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-100 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Stock por bodega — espejo Bsale, solo lectura</h3>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($stocks as $stock)
                            <li class="flex items-center justify-between gap-4 px-6 py-3 text-sm">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.bodegas.show', $stock->bodega) }}" class="truncate font-medium text-neutral-900 underline-offset-2 hover:underline">
                                        {{ $stock->bodega->nombre }}
                                    </a>
                                    @unless ($stock->bodega->activa)
                                        <x-badge variant="neutral">inactiva</x-badge>
                                    @endunless
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-medium text-neutral-900">{{ \App\Models\Stock::formatear($stock->stock_disponible) }} disp.</span>
                                    <span class="text-xs text-neutral-500">· {{ \App\Models\Stock::formatear($stock->stock_real) }} real</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (($precios ?? collect())->isNotEmpty())
                <div class="mt-6 rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="border-b border-neutral-100 px-6 py-3">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Precios por lista — espejo Bsale, solo lectura</h3>
                    </div>
                    <ul class="divide-y divide-neutral-100">
                        @foreach ($precios as $precio)
                            <li class="flex items-center justify-between gap-4 px-6 py-3 text-sm">
                                <div class="flex min-w-0 flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.listas-precios.show', $precio->lista) }}" class="truncate font-medium text-neutral-900 underline-offset-2 hover:underline">
                                        {{ $precio->lista->nombre }}
                                    </a>
                                    @if ($precio->lista->canal)
                                        <x-badge>{{ $precio->lista->canal }}</x-badge>
                                    @endif
                                    @unless ($precio->lista->activa)
                                        <x-badge variant="neutral">inactiva</x-badge>
                                    @endunless
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-medium text-neutral-900">${{ \App\Models\Precio::formatear($precio->precio_con_iva) ?? '—' }}</span>
                                    <span class="text-xs text-neutral-500">· neto ${{ \App\Models\Precio::formatear($precio->precio_neto) ?? '—' }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
