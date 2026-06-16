<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Registrar ingreso" subtitle="Nuevo equipo recibido en el taller.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Cancelar" title="Cancelar">
                        <x-icon.x-mark class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="orden-servicio-form" size="lg" variant="primary" label="Registrar ingreso" title="Registrar ingreso">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form id="orden-servicio-form" method="POST" action="{{ route('admin.servicio-tecnico.store') }}" class="space-y-6">
                    @csrf
                    @include('admin.servicio-tecnico._form', ['orden' => null])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
