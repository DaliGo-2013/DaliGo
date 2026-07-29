<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Editar: '.$conductor->nombre" subtitle="Conductor de ruta (ingreso por lote)."
                       :back="route('admin.conductores.index')" backTitle="Volver a conductores" />
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.conductores.update', $conductor) }}">
                @csrf
                @method('PUT')
                @include('admin.conductores._form', ['conductor' => $conductor])
                <div class="mt-6">
                    <x-primary-button>Guardar cambios</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
