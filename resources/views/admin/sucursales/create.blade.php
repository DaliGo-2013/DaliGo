<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Crear sucursal">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.sucursales.index')" form="sucursal-form" submitLabel="Crear sucursal" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="sucursal-form" method="POST" action="{{ route('admin.sucursales.store') }}" class="space-y-5">
                    @csrf
                    @include('admin.sucursales._form', ['sucursal' => null])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
