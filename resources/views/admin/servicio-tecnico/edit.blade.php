<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Editar orden '.$orden->folio" subtitle="Actualiza los datos del equipo en taller."
            :back="route('admin.servicio-tecnico.index')">
            <x-slot name="action">
                <x-form-actions :cancel="route('admin.servicio-tecnico.index')" form="orden-servicio-form" submitLabel="Guardar cambios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="orden-servicio-form" method="POST" action="{{ route('admin.servicio-tecnico.update', $orden) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('admin.servicio-tecnico._form', ['orden' => $orden])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
