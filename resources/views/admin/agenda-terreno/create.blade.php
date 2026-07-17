<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Agendar trabajo" subtitle="Mantención, reparación o instalación en terreno.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.agenda-terreno.index')" size="lg" variant="secondary" label="Volver" title="Volver a la agenda">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="agenda-form" size="lg" variant="primary" label="Guardar" title="Agendar">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8"
                 x-data="agendaTerrenoForm({
                    endpointCliente: '{{ route('admin.agenda-terreno.buscar-cliente') }}',
                    servicios: @js($serviciosJs),
                    clienteId: {{ (int) old('cliente_id', 0) }},
                    servicioId: @js(old('servicio_terreno_id', '')),
                 })">
                <form id="agenda-form" method="POST" action="{{ route('admin.agenda-terreno.store') }}">
                    @csrf
                    @include('admin.agenda-terreno._form', ['trabajo' => null])
                    <div class="mt-6">
                        <x-primary-button class="w-full justify-center py-3 sm:w-auto">Agendar trabajo</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
