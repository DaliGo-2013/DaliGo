<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Editar: '.\Illuminate\Support\Str::headline($configuracion->clave)"
                       :back="route('admin.configuracion.index')" backTitle="Volver a configuración" />
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.configuracion.update', $configuracion) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="valor" value="Valor" />

                    @if ($esLista)
                        {{-- Lista simple (COM-1): el dueño edita UN valor por
                             línea — nada de corchetes ni comillas. El controller
                             normaliza y guarda como JSON. --}}
                        <x-textarea id="valor" class="mt-1.5" name="valor" rows="6" required>{{ old('valor', implode("\n", is_array($configuracion->valor_tipado) ? $configuracion->valor_tipado : [])) }}</x-textarea>
                        <x-input-hint>Un valor por línea. Las líneas vacías y los repetidos se descartan solos.</x-input-hint>
                    @else
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
                    @endif

                    @if ($configuracion->descripcion)
                        <x-input-hint>{{ $configuracion->descripcion }}</x-input-hint>
                    @endif
                    <x-input-error :messages="$errors->get('valor')" class="mt-2" />
                </div>

                <x-form-footer>
                    <x-primary-button>Guardar cambios</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
