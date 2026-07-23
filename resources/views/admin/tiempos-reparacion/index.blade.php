<x-app-layout>
    @php $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.'); @endphp
    <x-slot name="header">
        <x-page-header title="Costos generales de reparación" subtitle="Tiempo estándar (horas) por trabajo — fija la mano de obra del taller.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('dashboard')" size="lg" variant="secondary" label="Volver al inicio" title="Volver al inicio">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-button-link :href="route('admin.tiempos-reparacion.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Agregar trabajo
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-neutral-700 shadow-sm sm:p-5">
                Estas horas fijan la <span class="font-medium">mano de obra</span> de cada orden: se calcula
                <span class="font-medium">horas × valor hora</span>@if ($valorHora) (valor hora actual {{ $clp($valorHora) }})@endif.
                El técnico <span class="font-medium">no la puede modificar</span>: solo jefatura la ajusta aquí.
                @unless ($valorHora)
                    <span class="mt-1 block text-xs text-red-600">Ojo: no hay valor hora configurado (SKU {{ config('servicio_tecnico.sku_hora_servicio') }} sin precio) → la mano de obra queda en $0 hasta cargarlo.</span>
                @endunless
            </div>

            @forelse ($porGrupo as $grupo => $tiempos)
                <x-list-card :title="$grupo ?: 'Sin grupo'" :count="$tiempos->count()" :countLabel="$tiempos->count() === 1 ? 'trabajo' : 'trabajos'">
                    @foreach ($tiempos as $t)
                        <li class="px-4 py-3 sm:px-6 {{ $t->activo ? '' : 'opacity-60' }}">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded bg-brand-50 px-1.5 py-0.5 text-xs font-semibold text-brand-700">{{ $t->horas_fmt }} h</span>
                                        <p class="font-medium text-neutral-900">{{ $t->trabajo }}</p>
                                        @unless ($t->activo)
                                            <x-badge variant="neutral">Inactivo</x-badge>
                                        @endunless
                                    </div>
                                    @if ($valorHora)
                                        <p class="mt-0.5 text-xs text-neutral-500">Mano de obra: {{ $clp($t->horas * $valorHora) }}</p>
                                    @endif
                                </div>
                                <div class="shrink-0">
                                    <x-secondary-link :href="route('admin.tiempos-reparacion.edit', $t)">Editar</x-secondary-link>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </x-list-card>
            @empty
                <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center text-sm text-neutral-500 shadow-sm">
                    Sin trabajos en el catálogo todavía. Usa «Agregar trabajo».
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
