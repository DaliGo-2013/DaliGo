<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Catálogo" subtitle="Productos (SKU), con peso y dimensiones para despacho.">
            <x-slot name="action">
                <div class="flex flex-wrap items-center gap-2">
                    <x-secondary-link :href="route('admin.productos.import.form')">Importar CSV</x-secondary-link>
                    <x-secondary-link :href="route('admin.productos.export', request()->query())">Exportar CSV</x-secondary-link>
                    <x-button-link :href="route('admin.productos.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Crear producto
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.productos.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="q" value="Buscar (SKU o nombre)" />
                    <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. botellón" />
                </div>
                <div class="sm:w-44">
                    <x-input-label for="categoria" value="Categoría" />
                    <x-select id="categoria" name="categoria" class="mt-1.5">
                        <option value="">Todas</option>
                        @foreach ($categorias as $c)
                            <option value="{{ $c }}" @selected(($filtros['categoria'] ?? '') === $c)>{{ $c }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:w-44">
                    <x-input-label for="marca" value="Marca" />
                    <x-select id="marca" name="marca" class="mt-1.5">
                        <option value="">Todas</option>
                        @foreach ($marcas as $m)
                            <option value="{{ $m }}" @selected(($filtros['marca'] ?? '') === $m)>{{ $m }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:w-36">
                    <x-input-label for="activo" value="Estado" />
                    <x-select id="activo" name="activo" class="mt-1.5">
                        <option value="">Todos</option>
                        <option value="1" @selected(($filtros['activo'] ?? '') === '1')>Activos</option>
                        <option value="0" @selected(($filtros['activo'] ?? '') === '0')>Inactivos</option>
                    </x-select>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.productos.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Productos" :count="$productos->total()" :countLabel="\Illuminate\Support\Str::plural('producto', $productos->total())">
                @forelse ($productos as $producto)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($producto->nombre, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $producto->nombre }}</p>
                            @if ($producto->marca)
                                <x-badge variant="neutral">{{ $producto->marca }}</x-badge>
                            @endif
                            @unless ($producto->activo)
                                <x-badge variant="neutral">inactivo</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $producto->sku }}@if ($producto->categoria) · {{ $producto->categoria }}@endif
                        </p>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-40 sm:shrink-0 sm:text-right">
                                @if ($producto->peso_kg !== null)
                                    {{ rtrim(rtrim($producto->peso_kg, '0'), '.') }} kg
                                @else
                                    <span class="text-neutral-400">sin peso</span>
                                @endif
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.productos.edit', $producto)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                            <form method="POST" action="{{ route('admin.productos.destroy', $producto) }}" onsubmit="return confirm('¿Eliminar el producto {{ $producto->sku }}?');">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" variant="danger" label="Eliminar" title="Eliminar">
                                    <x-icon.trash class="h-5 w-5" />
                                </x-icon-button>
                            </form>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">
                        No hay productos. Crea uno o importa un CSV.
                    </li>
                @endforelse
            </x-list-card>

            @if ($productos->hasPages())
                <div>{{ $productos->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
