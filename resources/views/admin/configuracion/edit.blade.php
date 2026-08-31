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
                    {{-- El formato va en la ⓘ (condicional: solo aplica al tipo lista) y abajo del
                         campo queda la descripción del parámetro, que es el dato de qué se está
                         editando, no una explicación del control. --}}
                    <x-input-label for="valor" value="Valor">
                        @if ($esSeleccionPreformas)
                            <x-slot:ayuda>Marca las preformas que se ofrecen al asignar producción. Con una selección guardada, una preforma nueva del catálogo no aparece hasta marcarla acá.</x-slot:ayuda>
                        @elseif ($esLista)
                            <x-slot:ayuda>Un valor por línea. Las líneas vacías y los repetidos se descartan solos.</x-slot:ayuda>
                        @endif
                    </x-input-label>

                    @if ($esSeleccionPreformas)
                        {{-- Whitelist de preformas (pedido del dueño 31-08): checkboxes del
                             universo del selector — sin tipeo de SKUs. Ninguna marcada = modo
                             automático (todas). --}}
                        <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($preformasUniverso as $preforma)
                                <x-checkbox-item name="valor[]" value="{{ $preforma->sku }}"
                                    :checked="in_array($preforma->sku, old('valor', $preformasMarcadas), true)">
                                    {{ $preforma->nombre }}
                                    <x-slot name="note">{{ $preforma->sku }}</x-slot>
                                </x-checkbox-item>
                            @endforeach
                        </div>
                        <x-input-hint>Desmarcar todas vuelve al modo automático (se ofrecen todas).</x-input-hint>
                    @elseif ($esLista)
                        {{-- Lista simple (COM-1): el dueño edita UN valor por
                             línea — nada de corchetes ni comillas. El controller
                             normaliza y guarda como JSON. --}}
                        <x-textarea id="valor" class="mt-1.5" name="valor" rows="6" required>{{ old('valor', implode("\n", is_array($configuracion->valor_tipado) ? $configuracion->valor_tipado : [])) }}</x-textarea>
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

                    {{-- Para la selección de preformas la descripción NO se apila: el campo ya
                         lleva su línea operativa y la explicación vive en la ⓘ (la ayuda se mide
                         POR CAMPO — AyudaEnIconoTest). --}}
                    @if ($configuracion->descripcion && ! $esSeleccionPreformas)
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
