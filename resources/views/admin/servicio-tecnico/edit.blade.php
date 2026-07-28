<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Recepción · '.$orden->folio" subtitle="Datos del equipo recibido en el taller."
                       :back="route('admin.servicio-tecnico.index')" backTitle="Volver al listado">
            <x-slot name="action">
                <x-icon-button type="submit" form="orden-servicio-form" size="lg" variant="primary" label="Guardar" title="Guardar cambios">
                    <x-icon.check class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        {{-- Etapas de la orden: recepción · cotización · parte del técnico. --}}
        @include('admin.servicio-tecnico._tabs', ['activa' => 'recepcion'])

        {{-- Fotos que sacó el cliente al ingresar (estado del equipo). --}}
        <div class="mb-4">
            @include('admin.servicio-tecnico._fotos')
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form id="orden-servicio-form" method="POST" action="{{ route('admin.servicio-tecnico.update', $orden) }}" class="space-y-6" data-una-vez>
                @csrf
                @method('PUT')
                @include('admin.servicio-tecnico._form', ['orden' => $orden])
            </form>
        </div>
    </div>
</x-app-layout>
