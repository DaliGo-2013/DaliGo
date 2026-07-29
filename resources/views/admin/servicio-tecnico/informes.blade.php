{{-- Landing de informes de Servicio Técnico: dos "carpetas" (tarjetas) para
     elegir el dominio — Dispensadores (taller) e Industrial (terreno). --}}
<x-app-layout ancho="formulario">
    <x-slot name="header">
        {{-- Sin "Volver": es un ítem del menú. Las dos pantallas que cuelgan de
             aquí (informe-dispensadores, informe-industrial) sí lo llevan, y
             apuntan a esta. --}}
        <x-page-header title="Informes · Servicio Técnico" subtitle="Elige qué estadísticas quieres ver." />
    </x-slot>

    <div class="py-12">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {{-- Dispensadores (taller) — solo con permiso del informe de taller. --}}
            @can('ver informe dispensadores')
                <a href="{{ route('admin.servicio-tecnico.informe.dispensadores') }}"
                    class="dg-enter group block rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6 transition duration-150 hover:border-brand-300 hover:shadow active:scale-[0.99]">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon.wrench-screwdriver class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-neutral-900">Dispensadores</h3>
                    <p class="mt-1 text-sm text-neutral-500">
                        Taller: órdenes ingresadas, garantía vs reparación, equipos y clientes que más ingresan, causa de falla y repuestos usados.
                    </p>
                </a>
            @endcan

            {{-- Industrial (terreno) — solo con permiso del informe industrial. --}}
            @can('ver informe industrial')
                <a href="{{ route('admin.servicio-tecnico.informe.industrial') }}"
                    class="dg-enter group block rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6 transition duration-150 hover:border-brand-300 hover:shadow active:scale-[0.99]">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                        <x-icon.document-text class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-neutral-900">Industrial</h3>
                    <p class="mt-1 text-sm text-neutral-500">
                        Servicio en terreno: uso de repuestos, % por tipo de trabajo (reparación, instalación, mantención, visita técnica) y servicios más usados.
                    </p>
                </a>
            @endcan
        </div>
    </div>
</x-app-layout>
