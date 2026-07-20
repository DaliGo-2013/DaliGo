<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Nuevo conductor" subtitle="Se agrega al selector del ingreso por lote.">
            <x-slot name="action">
                <x-icon-button :href="route('admin.conductores.index')" size="lg" variant="secondary" label="Volver" title="Volver a conductores">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.conductores.store') }}">
                    @csrf
                    @include('admin.conductores._form', ['conductor' => null])
                    <div class="mt-6">
                        <x-primary-button>Agregar conductor</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
