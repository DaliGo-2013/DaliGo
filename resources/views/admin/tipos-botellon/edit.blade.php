<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar tipo de botellón"
                       :back="route('admin.tipos-botellon.index')" backTitle="Volver a tipos de botellón" />
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.tipos-botellon.update', $tipo) }}" class="space-y-5">
                @csrf
                @method('PUT')
                @include('admin.tipos-botellon._form', ['tipo' => $tipo])

                <x-form-footer>
                    <x-primary-button>Guardar cambios</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
