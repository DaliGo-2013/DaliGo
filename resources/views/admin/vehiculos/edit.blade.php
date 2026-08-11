<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Editar '.$vehiculo->ppu"
                       :subtitle="$vehiculo->alias ?: $vehiculo->marca_modelo"
                       :back="route('admin.vehiculos.show', $vehiculo)" backTitle="Volver a la ficha" />
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        {{-- `enctype`: el formulario lleva las fotos de los documentos. Sin esto el
             archivo NO viaja y el resto se guarda igual, así que el fallo es
             silencioso — se ve como «guardé y la foto no quedó». Candado:
             VehiculoRespaldoDesdeEditarTest. --}}
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.vehiculos.update', $vehiculo) }}"
              class="space-y-5" data-una-vez>
            @csrf
            @method('PUT')
            @include('admin.vehiculos._form', ['vehiculo' => $vehiculo])

            <x-form-footer>
                <x-primary-button>Guardar cambios</x-primary-button>
            </x-form-footer>
        </form>

        {{-- Eliminar va al final y separado: la salida NORMAL de la flota es dar
             de baja arriba (venta, pérdida total), que conserva la historia.
             Esto es para una fila cargada por error. --}}
        <div class="rounded-xl border border-red-200 bg-red-50 p-3 sm:p-4">
            <p class="text-sm font-medium text-red-800">Eliminar del registro</p>
            <p class="mt-1 text-sm text-red-700">
                Solo si la fila se cargó por error. Si el vehículo se vendió o se dio de baja,
                cámbiale el estado arriba: así queda la historia.
            </p>
            <form method="POST" action="{{ route('admin.vehiculos.destroy', $vehiculo) }}" class="mt-3"
                  onsubmit="return confirm('¿Eliminar el vehículo {{ $vehiculo->ppu }}? Esta acción no se puede deshacer.');">
                @csrf
                @method('DELETE')
                <x-danger-button>Eliminar {{ $vehiculo->ppu }}</x-danger-button>
            </form>
        </div>
    </div>
</x-app-layout>
