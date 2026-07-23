<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Nuevo trabajo (tiempo estándar)" subtitle="Se suma a los costos generales de reparación.">
            <x-slot name="action">
                <x-icon-button :href="route('admin.tiempos-reparacion.index')" size="lg" variant="secondary" label="Volver" title="Volver al catálogo">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.tiempos-reparacion.store') }}">
                    @csrf
                    @include('admin.tiempos-reparacion._form', ['tiempo' => null])
                    <div class="mt-6">
                        <x-primary-button>Agregar trabajo</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
