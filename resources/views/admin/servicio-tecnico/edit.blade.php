<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Recepción · '.$orden->folio" subtitle="Datos del equipo recibido en el taller.">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver al listado">
                        <x-icon.arrow-left class="h-5 w-5" />
                    </x-icon-button>
                    <x-icon-button type="submit" form="orden-servicio-form" size="lg" variant="primary" label="Guardar" title="Guardar cambios">
                        <x-icon.check class="h-5 w-5" />
                    </x-icon-button>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            {{-- Acceso directo a la etapa de taller (parte del tecnico): reparacion,
                 repuestos, mano de obra y fechas de la misma orden. --}}
            <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}"
               class="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 shadow-sm transition hover:bg-brand-100">
                <span class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                        <x-icon.wrench-screwdriver class="h-5 w-5" />
                    </span>
                    <span>
                        <span class="block font-medium text-neutral-900">Ver parte del técnico</span>
                        <span class="block text-sm text-neutral-500">Reparación, repuestos, mano de obra y fechas.</span>
                    </span>
                </span>
                <x-icon.arrow-right class="h-5 w-5 shrink-0 text-brand-600" />
            </a>

            {{-- Fotos que sacó el cliente al ingresar (estado del equipo). --}}
            <div class="mb-4">
                @include('admin.servicio-tecnico._fotos')
            </div>

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
