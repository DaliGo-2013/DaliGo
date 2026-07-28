<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Crear producto"
                       :back="route('admin.productos.index')" backTitle="Volver al catálogo">
            <x-slot name="action">
                <x-form-actions form="producto-form" submitLabel="Crear producto" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form id="producto-form" method="POST" action="{{ route('admin.productos.store') }}" class="space-y-6">
                @csrf
                @include('admin.productos._form', ['producto' => null])
            </form>
        </div>
    </div>
</x-app-layout>
