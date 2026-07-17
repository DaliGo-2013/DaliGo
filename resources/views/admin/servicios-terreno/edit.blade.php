<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar servicio" :subtitle="$servicio->nombre">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicios-terreno.index')" size="lg" variant="secondary" label="Volver" title="Volver al catálogo">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.servicios-terreno.update', $servicio) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.servicios-terreno._form', ['servicio' => $servicio])
                    <div class="mt-6">
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
