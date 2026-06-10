<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Clientes" subtitle="Ficha local espejada desde Bsale, con segmento y cartera por vendedor.">
            <x-slot name="action">
                <x-button-link :href="route('admin.clientes.create')">
                    <x-icon.plus class="h-4 w-4" />
                    Crear cliente
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.clientes.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="q" value="Buscar (RUT o razón social)" />
                    <x-text-input id="q" name="q" class="mt-1.5" type="text" :value="$filtros['q'] ?? ''" placeholder="ej. 12.345.678-9 o Comercial" />
                </div>
                <div class="sm:w-44">
                    <x-input-label for="segmento" value="Segmento" />
                    <x-select id="segmento" name="segmento" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($segmentos as $s)
                            <option value="{{ $s }}" @selected(($filtros['segmento'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="sm:w-44">
                    <x-input-label for="vendedor_id" value="Vendedor" />
                    <x-select id="vendedor_id" name="vendedor_id" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($vendedores as $v)
                            <option value="{{ $v->id }}" @selected((int) ($filtros['vendedor_id'] ?? 0) === $v->id)>{{ $v->name }}</option>
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
                        <x-secondary-link :href="route('admin.clientes.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Clientes" :count="$clientes->total()" :countLabel="\Illuminate\Support\Str::plural('cliente', $clientes->total())">
                @forelse ($clientes as $cliente)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($cliente->razon_social, 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $cliente->razon_social }}</p>
                            @if ($cliente->segmento)
                                <x-badge>{{ ucfirst($cliente->segmento) }}</x-badge>
                            @endif
                            @if ($cliente->bsale_client_id)
                                <x-badge variant="neutral">Bsale</x-badge>
                            @endif
                            @unless ($cliente->activo)
                                <x-badge variant="neutral">inactivo</x-badge>
                            @endunless
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $cliente->rut ?? 'Sin RUT' }}@if ($cliente->giro) · {{ $cliente->giro }}@endif
                        </p>

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-40 sm:shrink-0 sm:text-right">
                                @if ($cliente->vendedor)
                                    {{ $cliente->vendedor->name }}
                                @else
                                    <span class="text-neutral-400">sin vendedor</span>
                                @endif
                            </div>
                        </x-slot>

                        <x-slot name="actions">
                            <x-icon-button :href="route('admin.clientes.edit', $cliente)" label="Editar" title="Editar">
                                <x-icon.pencil class="h-5 w-5" />
                            </x-icon-button>
                            <form method="POST" action="{{ route('admin.clientes.destroy', $cliente) }}" onsubmit="return confirm('¿Eliminar el cliente {{ $cliente->razon_social }}?');">
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
                        No hay clientes. Crea uno o sincroniza desde Bsale (php artisan bsale:sync-clients).
                    </li>
                @endforelse
            </x-list-card>

            @if ($clientes->hasPages())
                <div>{{ $clientes->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
