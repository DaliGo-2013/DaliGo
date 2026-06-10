<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Editar: '.$cliente->razon_social" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                @if ($cliente->bsale_client_id)
                    <p class="mb-6 text-sm text-neutral-500">
                        Cliente enlazado a Bsale (id {{ $cliente->bsale_client_id }}). Los datos de la ficha
                        se realinean con Bsale en cada sincronización; segmento, notas y vendedor son locales.
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.clientes.update', $cliente) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.clientes._form', ['cliente' => $cliente])

                    <x-form-footer :cancel="route('admin.clientes.index')">
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
