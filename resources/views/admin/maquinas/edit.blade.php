<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Editar máquina" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.maquinas.update', $maquina) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('admin.maquinas._form', ['maquina' => $maquina])

                    <x-form-footer :cancel="route('admin.maquinas.index')">
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
