<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar máquina"
                       :back="route('admin.maquinas.index')" backTitle="Volver a máquinas" />
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.maquinas.update', $maquina) }}" class="space-y-5">
                @csrf
                @method('PUT')
                @include('admin.maquinas._form', ['maquina' => $maquina])

                <x-form-footer>
                    <x-primary-button>Guardar cambios</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
