<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar molde" :subtitle="$molde->nombre"
                       :back="route('admin.moldes.show', $molde)" backTitle="Volver a la ficha">
            <x-slot name="action">
                <x-form-actions form="molde-form" submitLabel="Guardar molde" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form id="molde-form" method="POST" action="{{ route('admin.moldes.update', $molde) }}" class="space-y-5">
                @csrf
                @method('PUT')
                @include('admin.moldes._form')
            </form>
        </div>
    </div>
</x-app-layout>
