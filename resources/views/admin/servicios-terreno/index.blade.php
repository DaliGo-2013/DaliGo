<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Catálogo de servicios de terreno" subtitle="Tarifario en UF del técnico industrial (editable).">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.agenda-terreno.index')" size="lg" variant="secondary" label="Volver" title="Volver a la agenda">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-button-link :href="route('admin.servicios-terreno.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Nuevo servicio
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <x-list-card title="Servicios" :count="$servicios->count()" :countLabel="$servicios->count() === 1 ? 'servicio' : 'servicios'">
                @forelse ($servicios as $s)
                    <li class="px-4 py-3 sm:px-6 {{ $s->activo ? '' : 'opacity-60' }}">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-medium text-neutral-900">{{ $s->nombre }}</p>
                                    @if ($s->valor_uf !== null)
                                        <span class="rounded bg-brand-50 px-1.5 py-0.5 text-xs font-semibold text-brand-700">{{ rtrim(rtrim(number_format($s->valor_uf, 2, ',', '.'), '0'), ',') }} UF</span>
                                    @endif
                                    @if ($s->duracion)
                                        <span class="text-xs text-neutral-500">{{ $s->duracion }}</span>
                                    @endif
                                    @unless ($s->activo)
                                        <x-badge variant="neutral">Inactivo</x-badge>
                                    @endunless
                                </div>
                                @if ($s->incluye)
                                    <p class="mt-0.5 text-sm text-neutral-600">{{ $s->incluye }}</p>
                                @endif
                                @if ($s->observaciones)
                                    <p class="mt-0.5 text-xs text-neutral-400">{{ $s->observaciones }}</p>
                                @endif
                            </div>
                            <div class="shrink-0">
                                <x-secondary-link :href="route('admin.servicios-terreno.edit', $s)">Editar</x-secondary-link>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">Sin servicios en el catálogo.</li>
                @endforelse
            </x-list-card>

            {{-- Condiciones generales del tarifario (del impreso del taller). --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 text-sm text-neutral-600 shadow-sm sm:p-5">
                <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Condiciones generales</h3>
                <ul class="list-inside list-disc space-y-1">
                    <li>Garantía de producto: <span class="font-medium text-neutral-900">6 meses</span>.</li>
                    <li>Garantía de servicio: <span class="font-medium text-neutral-900">30 días hábiles</span>.</li>
                    <li>Soldadura: se cobra aparte (empresa externa en Santiago).</li>
                    <li>El valor es por día hábil y en horario de trabajo.</li>
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
