<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar sucursal"
                       :back="route('admin.sucursales.index')" backTitle="Volver a sucursales">
            <x-slot name="action">
                <x-form-actions form="sucursal-form" submitLabel="Guardar cambios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form id="sucursal-form" method="POST" action="{{ route('admin.sucursales.update', $sucursal) }}" class="space-y-5">
                @csrf
                @method('PUT')
                @include('admin.sucursales._form', ['sucursal' => $sucursal])
            </form>
        </div>
    </div>
</x-app-layout>
