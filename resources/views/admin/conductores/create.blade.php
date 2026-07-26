<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Nuevo conductor" subtitle="Se agrega al selector del ingreso por lote."
                       :back="route('admin.conductores.index')" backTitle="Volver a conductores" />
    </x-slot>

    <div class="py-8 sm:py-12">
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
</x-app-layout>
