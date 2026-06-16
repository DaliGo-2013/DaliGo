<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Crear cliente">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.clientes.index')" form="cliente-form" submitLabel="Crear cliente" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="cliente-form" method="POST" action="{{ route('admin.clientes.store') }}" class="space-y-6">
                    @csrf
                    @include('admin.clientes._form', ['cliente' => null])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
