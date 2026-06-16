<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Editar orden '.$orden->folio" subtitle="Actualiza los datos del equipo en taller.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" variant="secondary" label="Cancelar" title="Cancelar">
                        <x-icon.x-mark class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="orden-servicio-form" variant="primary" label="Guardar cambios" title="Guardar cambios">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
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
