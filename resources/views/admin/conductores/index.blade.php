<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Conductores" subtitle="Choferes que retiran en ruta (ingreso por lote).">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver a Servicio Técnico">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-button-link :href="route('admin.conductores.create')">
                        <x-icon.plus class="h-4 w-4" />
                        Nuevo conductor
                    </x-button-link>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-5 px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" />

            <x-list-card title="Conductores" :count="$conductores->count()" :countLabel="$conductores->count() === 1 ? 'conductor' : 'conductores'">
                @forelse ($conductores as $c)
                    <li class="flex items-center justify-between px-4 py-3 sm:px-6 {{ $c->activo ? '' : 'opacity-60' }}">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-neutral-900">{{ $c->nombre }}</p>
                            @unless ($c->activo)
                                <x-badge variant="neutral">Inactivo</x-badge>
                            @endunless
                        </div>
                        <x-secondary-link :href="route('admin.conductores.edit', $c)">Editar</x-secondary-link>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">Sin conductores. Agrega el primero.</li>
                @endforelse
            </x-list-card>

            <p class="text-xs text-neutral-400">
                Solo los conductores <span class="font-medium">activos</span> aparecen en el selector del ingreso por lote.
                No se borran: uno que ya no maneja se desactiva (los lotes históricos conservan su nombre).
            </p>
        </div>
    </div>
</x-app-layout>
