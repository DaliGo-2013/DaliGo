<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Editar: '.$producto->nombre" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.productos.update', $producto) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.productos._form', ['producto' => $producto])

                    <x-form-footer :cancel="route('admin.productos.index')">
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
