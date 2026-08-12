<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Agregar vehículo" subtitle="Patente, documentos y a quién está asignado."
                       :back="route('admin.vehiculos.index')" backTitle="Volver a vehículos" />
    </x-slot>

    <div class="py-8">
        {{-- `enctype`: el formulario lleva las fotos de los documentos (ver la nota
             en edit.blade.php). Un vehículo nuevo se puede dar de alta ya con sus
             respaldos: se guardan después de crearlo, cuando existe el id. --}}
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.vehiculos.store') }}"
              class="space-y-5" data-una-vez>
            @csrf
            @include('admin.vehiculos._form', ['vehiculo' => $vehiculo])

            <x-form-footer>
                <x-primary-button>Agregar vehículo</x-primary-button>
            </x-form-footer>
        </form>
    </div>
</x-app-layout>
