<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Crear tipo de botellón"
                       :back="route('admin.tipos-botellon.index')" backTitle="Volver a tipos de botellón" />
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.tipos-botellon.store') }}" class="space-y-5">
                @csrf
                @include('admin.tipos-botellon._form', ['tipo' => null])

                <x-form-footer>
                    <x-primary-button>Crear tipo</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
