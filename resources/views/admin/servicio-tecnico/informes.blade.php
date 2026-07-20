{{-- Landing de informes de Servicio Técnico: dos "carpetas" (tarjetas) para
     elegir el dominio — Dispensadores (taller) e Industrial (terreno). --}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Informes · Servicio Técnico" subtitle="Elige qué estadísticas quieres ver.">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver al listado" title="Volver al listado">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {{-- Dispensadores (taller) --}}
                <a href="{{ route('admin.servicio-tecnico.informe.dispensadores') }}"
                    class="dg-enter group block rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm transition duration-150 hover:border-brand-300 hover:shadow active:scale-[0.99]">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon.wrench-screwdriver class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-neutral-900">Dispensadores</h3>
                    <p class="mt-1 text-sm text-neutral-500">
                        Taller: órdenes ingresadas, garantía vs reparación, equipos y clientes que más ingresan, causa de falla y repuestos usados.
                    </p>
                </a>

                {{-- Industrial (terreno) --}}
                <a href="{{ route('admin.servicio-tecnico.informe.industrial') }}"
                    class="dg-enter group block rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm transition duration-150 hover:border-brand-300 hover:shadow active:scale-[0.99]">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon.document-text class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-neutral-900">Industrial</h3>
                    <p class="mt-1 text-sm text-neutral-500">
                        Servicio en terreno: uso de repuestos, % por tipo de trabajo (reparación, instalación, mantención, visita técnica) y servicios más usados.
                    </p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
