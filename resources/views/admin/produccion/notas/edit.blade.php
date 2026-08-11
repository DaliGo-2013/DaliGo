<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar nota"
                       subtitle="Se pinta en la pantalla del soplador mientras esté vigente."
                       :back="route('admin.produccion.notas.index')" backTitle="Volver a Notas" />
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.produccion.notas.update', $nota) }}">
                @csrf
                @method('PUT')
                @include('admin.produccion.notas._form')
                <x-form-footer>
                    <x-primary-button>Guardar cambios</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
