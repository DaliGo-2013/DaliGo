<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar tiempo estándar" :subtitle="$tiempo->trabajo"
                       :back="route('admin.tiempos-reparacion.index')" backTitle="Volver al catálogo" />
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.tiempos-reparacion.update', $tiempo) }}">
                @csrf
                @method('PUT')
                @include('admin.tiempos-reparacion._form', ['tiempo' => $tiempo])
                <div class="mt-6">
                    <x-primary-button>Guardar cambios</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
