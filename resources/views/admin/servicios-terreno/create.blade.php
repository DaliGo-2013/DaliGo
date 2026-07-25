<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Nuevo servicio de terreno" subtitle="Se agrega al tarifario y al selector de la agenda."
                       :back="route('admin.servicios-terreno.index')" backTitle="Volver al catálogo" />
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.servicios-terreno.store') }}">
                    @csrf
                    @include('admin.servicios-terreno._form', ['servicio' => null])
                    <div class="mt-6">
                        <x-primary-button>Crear servicio</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
