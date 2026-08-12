<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Notas del jefe"
                       subtitle="Mensajes operativos que se pintan en la pantalla del soplador mientras están vigentes."
                       :back="route('admin.produccion.index')" backTitle="Volver a Producción">
            <x-slot name="action">
                <x-button-link :href="route('admin.produccion.notas.create')">
                    <x-icon.plus class="h-4 w-4" /> Nueva nota
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <x-status-alert :status="session('status')" class="mb-4" />

        <x-list-card :count="$notas->count()" countLabel="nota(s)">
            <x-slot name="title">Notas</x-slot>
            @forelse ($notas as $nota)
                @php
                    $vigente = $nota->esVigente();
                    $destinatario = $nota->soplador?->name ?? 'Todos los sopladores';
                    $vigencia = collect([
                        $nota->vigente_desde ? 'desde '.$nota->vigente_desde->format('d-m-Y') : null,
                        $nota->vigente_hasta ? 'hasta '.$nota->vigente_hasta->format('d-m-Y') : null,
                    ])->filter()->implode(' · ') ?: 'sin límite de fechas';
                @endphp
                <x-list-row>
                    <x-slot name="leading">
                        <a href="{{ route('admin.produccion.notas.edit', $nota) }}" class="block min-w-0">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $nota->texto }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">Para: {{ $destinatario }} · {{ $vigencia }}</p>
                        </a>
                    </x-slot>
                    <x-slot name="meta">
                        <x-badge :variant="$vigente ? 'brand' : 'neutral'">{{ $vigente ? 'Vigente' : 'No vigente' }}</x-badge>
                    </x-slot>
                    <x-slot name="actions">
                        <form method="POST" action="{{ route('admin.produccion.notas.destroy', $nota) }}"
                              onsubmit="return confirm('¿Eliminar esta nota?');">
                            @csrf
                            @method('DELETE')
                            <x-icon-button type="submit" variant="danger" label="Eliminar nota" title="Eliminar nota">
                                <x-icon.trash class="h-5 w-5" />
                            </x-icon-button>
                        </form>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Sin notas todavía. Publica la primera con «Nueva nota».
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
