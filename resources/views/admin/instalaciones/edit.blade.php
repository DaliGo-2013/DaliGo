<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar instalación" subtitle="Instalación / puesta en marcha en terreno.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    {{-- "Atrás" del navegador: vuelve al registro donde estaba; link de respaldo. --}}
                    <x-icon-button :href="route('admin.instalaciones.index')" size="lg" variant="secondary" label="Volver" title="Volver al registro"
                        onclick="if (window.history.length > 1) { event.preventDefault(); window.history.back(); }">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="instalacion-form" size="lg" variant="primary" label="Guardar" title="Guardar cambios">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
                 x-data="instalacionForm({
                    endpointCliente: '{{ route('admin.instalaciones.buscar-cliente') }}',
                    clienteId: {{ (int) old('cliente_id', $instalacion->cliente_id ?? 0) }},
                 })">
                <form id="instalacion-form" method="POST" action="{{ route('admin.instalaciones.update', $instalacion) }}" data-una-vez>
                    @csrf
                    @method('PUT')
                    @include('admin.instalaciones._form', ['instalacion' => $instalacion])
                    <div class="mt-6">
                        <x-primary-button class="w-full justify-center py-3 sm:w-auto">Guardar cambios</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Eliminar del registro --}}
            <form method="POST" action="{{ route('admin.instalaciones.destroy', $instalacion) }}"
                  onsubmit="return confirm('¿Eliminar esta instalación del registro?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-red-600 transition hover:text-red-700">Eliminar del registro</button>
            </form>
        </div>
    </div>
</x-app-layout>
