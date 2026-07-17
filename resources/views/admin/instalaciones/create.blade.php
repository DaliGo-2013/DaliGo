<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Registrar instalación" subtitle="Instalación / puesta en marcha en terreno.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.instalaciones.index')" size="lg" variant="secondary" label="Volver" title="Volver al registro">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="instalacion-form" size="lg" variant="primary" label="Guardar" title="Registrar">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
                 x-data="instalacionForm({
                    endpointCliente: '{{ route('admin.instalaciones.buscar-cliente') }}',
                    clienteId: {{ (int) old('cliente_id', 0) }},
                 })">
                <form id="instalacion-form" method="POST" action="{{ route('admin.instalaciones.store') }}" data-una-vez>
                    @csrf
                    @include('admin.instalaciones._form', ['instalacion' => null])
                    <div class="mt-6">
                        <x-primary-button class="w-full justify-center py-3 sm:w-auto">Registrar instalación</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
