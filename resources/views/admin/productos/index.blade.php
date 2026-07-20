<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Catálogo" subtitle="Productos (SKU), con peso y dimensiones para despacho.">
            <x-slot name="action">
                <div class="flex flex-wrap items-center gap-2">
                    <x-secondary-link :href="route('admin.productos.plantilla.medidas', request()->query())">Plantilla de medidas</x-secondary-link>
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
                <div class="sm:w-48">
                    <x-input-label for="categoria_interna" value="Categoría interna" />
                    <x-select id="categoria_interna" name="categoria_interna" class="mt-1.5">
                        <option value="">Todas</option>
                        <option value="__none__" @selected(($filtros['categoria_interna'] ?? '') === '__none__')>(Sin asignar)</option>
                        @foreach ($categoriasInternas as $ci)
                            <option value="{{ $ci }}" @selected(($filtros['categoria_interna'] ?? '') === $ci)>{{ $ci }}</option>
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
                <div class="sm:w-40">
                    <x-input-label for="medidas" value="Medidas" />
                    <x-select id="medidas" name="medidas" class="mt-1.5">
                        <option value="">Todas</option>
                        <option value="incompletas" @selected(($filtros['medidas'] ?? '') === 'incompletas')>Incompletas</option>
                        <option value="completas" @selected(($filtros['medidas'] ?? '') === 'completas')>Completas</option>
                    </x-select>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.productos.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            {{-- Progreso de la carga de medidas (peso + dimensiones, productos activos) --}}
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-neutral-200 bg-white px-4 py-3 text-sm shadow-sm">
                <span class="font-medium text-neutral-700">Medidas completas:</span>
                <x-badge>{{ $activosCompletos }} de {{ $activos }} activos</x-badge>
                @if ($activos > 0)
                    <span class="text-xs text-neutral-500">({{ round($activosCompletos * 100 / $activos) }}%)</span>
                @endif
                @if ($activosCompletos < $activos)
                    <span class="text-xs text-neutral-400">— descarga la «Plantilla de medidas» para completar los pendientes.</span>
                @endif
            </div>

            {{-- Selección múltiple para agrupar productos en una categoría interna
                 (propia de DaliGo; NO toca lo que viene de Bsale). La barra de
                 acción va como form APARTE de las filas (los forms de eliminar no
                 se pueden anidar). La selección es por página. --}}
            <div x-data="{ sel: [], pageIds: @js($productos->pluck('id')->map(fn ($i) => (string) $i)->values()) }">
                {{-- Barra de acción (aparece al seleccionar) --}}
                <form method="POST" action="{{ route('admin.productos.clasificacion-interna') }}"
                      x-show="sel.length" x-cloak
                      class="mb-3 flex flex-col gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-3 shadow-sm sm:flex-row sm:items-end sm:justify-between">
                    @csrf
                    <template x-for="id in sel" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <div class="text-sm font-medium text-brand-700">
                        <span x-text="sel.length"></span> seleccionado(s)
                        <button type="button" class="ml-2 text-xs font-normal text-neutral-500 underline" @click="sel = []">limpiar</button>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                        <div>
                            <label for="cat_interna_bulk" class="block text-xs font-medium text-neutral-500">Categoría interna</label>
                            <input id="cat_interna_bulk" name="categoria_interna" list="cats-internas" type="text" autocomplete="off"
                                   placeholder="Ej. Industrial (Carlos)" maxlength="191"
                                   class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 sm:w-56">
                            <datalist id="cats-internas">
                                @foreach ($categoriasInternas as $ci)<option value="{{ $ci }}"></option>@endforeach
                            </datalist>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" name="accion" value="asignar" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-700">Asignar</button>
                            <button type="submit" name="accion" value="quitar" class="rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50">Quitar</button>
                        </div>
                    </div>
                </form>

                {{-- Seleccionar todos los de la página --}}
                <label class="mb-2 flex items-center gap-2 px-1 text-sm text-neutral-500">
                    <input type="checkbox" @change="sel = $event.target.checked ? [...pageIds] : []"
                           :checked="pageIds.length && sel.length === pageIds.length"
                           class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                    Seleccionar todos los de esta página (<span x-text="pageIds.length"></span>)
                </label>

                <x-list-card title="Productos" :count="$productos->total()" :countLabel="\Illuminate\Support\Str::plural('producto', $productos->total())">
                    @forelse ($productos as $producto)
                        <x-list-row>
                            <x-slot name="leading">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" value="{{ $producto->id }}" x-model="sel"
                                           class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                                    <x-avatar>{{ mb_substr($producto->nombre, 0, 1) }}</x-avatar>
                                </div>
                            </x-slot>

                            <div class="flex flex-wrap items-center gap-2">
                                <p class="truncate font-medium text-neutral-900">{{ $producto->nombre }}</p>
                                @if ($producto->categoria_interna)
                                    <x-badge>{{ $producto->categoria_interna }}</x-badge>
                                @endif
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
                    <div class="mt-4">{{ $productos->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
