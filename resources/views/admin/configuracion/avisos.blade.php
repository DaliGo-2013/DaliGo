<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header
            title="Avisos y destinatarios"
            subtitle="Marca qué roles reciben cada aviso del sistema."
            :back="route('admin.configuracion.index')"
            backTitle="Volver a configuración"
        >
            <x-slot name="action">
                <x-form-actions form="form-avisos" submitLabel="Guardar destinatarios" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        <form id="form-avisos" method="POST" action="{{ route('admin.configuracion.avisos.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @foreach ($familias as $familia => $eventos)
                <x-list-card
                    :title="$familia"
                    :count="count($eventos)"
                    :countLabel="\Illuminate\Support\Str::plural('aviso', count($eventos))"
                >
                    @foreach ($eventos as $evento => $etiqueta)
                        {{-- El contador vive en Alpine para reaccionar al toque; el
                             estado «nadie» es deliberado (dueño 28-08), no un error:
                             badge neutral sólido, no rojo. --}}
                        <li class="px-4 py-4 sm:px-6" x-data="{ n: {{ count($marcados[$evento]) }} }">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-neutral-900">{{ $etiqueta }}</p>
                                @if ($ayudas->get('notif_plantilla_'.str_replace('.', '_', $evento)))
                                    <x-info-tip>{{ $ayudas->get('notif_plantilla_'.str_replace('.', '_', $evento)) }}</x-info-tip>
                                @endif
                                <span class="ms-auto shrink-0 text-xs tabular-nums text-neutral-500"
                                    x-show="n > 0" x-text="n + (n === 1 ? ' rol' : ' roles')"></span>
                                <span class="ms-auto inline-flex shrink-0 rounded-full bg-neutral-800 px-2.5 py-0.5 text-xs font-medium text-white"
                                    x-show="n === 0" x-cloak>Nadie recibe este aviso</span>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
                                @change="n = $el.querySelectorAll('input:checked').length">
                                @foreach ($roles as $rol => $rotulo)
                                    <x-checkbox-item
                                        name="audiencias[{{ $evento }}][]"
                                        value="{{ $rol }}"
                                        :checked="in_array($rol, $marcados[$evento], true)"
                                    >{{ $rotulo }}</x-checkbox-item>
                                @endforeach
                            </div>

                            <x-input-error
                                :messages="collect($errors->get('audiencias.'.$evento.'.*'))->flatten()->all()"
                                class="mt-2"
                            />
                        </li>
                    @endforeach
                </x-list-card>
            @endforeach
        </form>

        {{-- Los avisos que NO se reparten por rol: destinatario fijo, solo se
             muestra a quién van (fuera del form: no viajan checkboxes). --}}
        <x-list-card title="Avisos con destinatario fijo" :count="count($fijos)" countLabel="avisos">
            @foreach ($fijos as $evento => $regla)
                <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:gap-3 sm:px-6">
                    <p class="text-sm font-medium text-neutral-900">{{ \App\Models\Notificacion::EVENTOS[$evento] }}</p>
                    <p class="text-xs text-neutral-500 sm:ms-auto sm:text-right">{{ $regla }}</p>
                </li>
            @endforeach
        </x-list-card>

        <x-form-footer>
            <x-primary-button form="form-avisos">Guardar destinatarios</x-primary-button>
        </x-form-footer>
    </div>
</x-app-layout>
