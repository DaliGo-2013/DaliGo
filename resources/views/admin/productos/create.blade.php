<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Crear producto">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.productos.index')" form="producto-form" submitLabel="Crear producto" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="producto-form" method="POST" action="{{ route('admin.productos.store') }}" class="space-y-6">
                    @csrf
                    @include('admin.productos._form', ['producto' => null])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
