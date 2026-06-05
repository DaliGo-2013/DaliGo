<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Editar: '.\Illuminate\Support\Str::headline($configuracion->clave)" />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.configuracion.update', $configuracion) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="valor" value="Valor" />

                        @switch($configuracion->tipo)
                            @case(\App\Models\Configuracion::TIPO_BOOLEAN)
                                <div class="mt-1.5">
                                    <x-checkbox-item name="valor" value="1" :checked="old('valor', $configuracion->valor_tipado)">
                                        Activado
                                    </x-checkbox-item>
                                </div>
                                @break

                            @case(\App\Models\Configuracion::TIPO_INTEGER)
                                <x-text-input id="valor" class="mt-1.5" type="number" step="1" name="valor" :value="old('valor', $configuracion->valor)" required />
                                @break

                            @case(\App\Models\Configuracion::TIPO_DECIMAL)
                                <x-text-input id="valor" class="mt-1.5" type="number" step="any" name="valor" :value="old('valor', $configuracion->valor)" required />
                                @break

                            @case(\App\Models\Configuracion::TIPO_JSON)
                                <x-textarea id="valor" class="mt-1.5 font-mono text-xs" name="valor" rows="6" required>{{ old('valor', $configuracion->jsonPretty()) }}</x-textarea>
                                @break

                            @default
                                <x-text-input id="valor" class="mt-1.5" type="text" name="valor" :value="old('valor', $configuracion->valor)" />
                        @endswitch

                        @if ($configuracion->descripcion)
                            <x-input-hint>{{ $configuracion->descripcion }}</x-input-hint>
                        @endif
                        <x-input-error :messages="$errors->get('valor')" class="mt-2" />
                    </div>

                    <x-form-footer :cancel="route('admin.configuracion.index')">
                        <x-primary-button>Guardar cambios</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
