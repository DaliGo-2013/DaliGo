<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar sucursal">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.sucursales.index')" form="sucursal-form" submitLabel="Guardar cambios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="sucursal-form" method="POST" action="{{ route('admin.sucursales.update', $sucursal) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('admin.sucursales._form', ['sucursal' => $sucursal])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
