<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Registrar ingreso" subtitle="Nuevo equipo recibido en el taller." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.servicio-tecnico.store') }}" class="space-y-6">
                    @csrf
                    @include('admin.servicio-tecnico._form', ['orden' => null])

                    <x-form-footer :cancel="route('admin.servicio-tecnico.index')">
                        <x-primary-button>Registrar ingreso</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
