<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Registrar ingreso" subtitle="Nuevo equipo recibido en el taller."
                       :back="route('admin.servicio-tecnico.index')" backTitle="Volver al listado">
            <x-slot name="action">
                <x-form-actions form="orden-servicio-form" submitLabel="Registrar ingreso" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-4 sm:py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
            <form id="orden-servicio-form" method="POST" action="{{ route('admin.servicio-tecnico.store') }}" class="space-y-6" data-una-vez>
                @csrf
                @include('admin.servicio-tecnico._form', ['orden' => null])
            </form>
        </div>
    </div>
</x-app-layout>
