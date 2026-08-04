<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Traslado '.$traslado->codigo"
                       :subtitle="$traslado->origen?->nombre.' → '.$traslado->destino?->nombre"
                       :back="route('admin.traslados.index')" backTitle="Volver a traslados" />
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        {{-- Cadena de custodia: las dos puntas, siempre a la vista. Es el punto de
             todo el registro — quién entregó y quién recibió, con nombre. --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cadena de custodia</h3>
                <x-badge :variant="$traslado->estado_variante">{{ $traslado->estado_label }}</x-badge>
            </div>

            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-neutral-400">Entregó</dt>
                    <dd class="text-sm font-medium text-neutral-900">{{ $traslado->emisor_nombre }}</dd>
                    <dd class="text-xs text-neutral-500">
                        {{ $traslado->origen?->nombre }} · {{ $traslado->despachado_at?->enChile()->format('d-m-Y H:i') }}
                        @if ($traslado->emisor)
                            <span class="block">Cargado con la cuenta de {{ $traslado->emisor->name }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Recibió</dt>
                    @if ($traslado->recibido)
                        <dd class="text-sm font-medium text-neutral-900">{{ $traslado->receptor_nombre }}</dd>
                        <dd class="text-xs text-neutral-500">
                            {{ $traslado->destino?->nombre }} · {{ $traslado->recibido_at?->enChile()->format('d-m-Y H:i') }}
                            @if ($traslado->receptor)
                                <span class="block">Confirmado con la cuenta de {{ $traslado->receptor->name }}</span>
                            @endif
                        </dd>
                    @else
                        <dd class="text-sm text-amber-700">Pendiente de confirmar</dd>
                        <dd class="text-xs text-neutral-500">Las máquinas no se pueden reparar hasta que alguien del taller confirme.</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Conductor</dt>
                    <dd class="text-sm text-neutral-900">{{ $traslado->conductor ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Conteo</dt>
                    <dd class="text-sm text-neutral-900">
                        Despachadas: <span class="font-medium">{{ $traslado->total_enviado }}</span>
                        @if ($traslado->recibido)
                            · Llegaron: <span class="font-medium">{{ $traslado->total_recibido }}</span>
                        @endif
                    </dd>
                </div>
                @if ($traslado->observaciones_envio)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-neutral-400">Observaciones del envío</dt>
                        <dd class="whitespace-pre-line text-sm text-neutral-900">{{ $traslado->observaciones_envio }}</dd>
                    </div>
                @endif
                @if ($traslado->observaciones_recepcion)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-neutral-400">Observaciones de la recepción</dt>
                        <dd class="whitespace-pre-line text-sm text-neutral-900">{{ $traslado->observaciones_recepcion }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- La diferencia, si la hubo: con los dos nombres y el detalle de qué falta.
             Sin decir CUÁL máquina, «falta una» no le sirve a nadie. --}}
        @if ($traslado->tiene_diferencia)
            <div class="rounded-2xl border-2 border-red-300 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-800">
                    Faltan {{ $traslado->faltantes }} {{ \Illuminate\Support\Str::plural('máquina', $traslado->faltantes) }}
                </p>
                <p class="mt-1 text-sm text-red-700">
                    Salieron {{ $traslado->total_enviado }} de {{ $traslado->origen?->nombre }} (entregó {{ $traslado->emisor_nombre }})
                    y llegaron {{ $traslado->total_recibido }} a {{ $traslado->destino?->nombre }} (recibió {{ $traslado->receptor_nombre }}).
                </p>
                <ul class="mt-2 space-y-1">
                    @foreach ($traslado->ordenesFaltantes() as $falta)
                        <li class="text-sm text-red-800">
                            <span class="font-mono text-xs">{{ $falta->folio }}</span> · {{ $falta->cliente_nombre }}
                            @if ($falta->numero_serie) · N° {{ $falta->numero_serie }} @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulario de recepción --}}
        @if (! $traslado->recibido)
            @can('recibir traslado servicio')
                <form method="POST" action="{{ route('admin.traslados.recibir', $traslado) }}"
                      class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4" data-una-vez
                      x-data="{ marcadas: {{ $traslado->ordenes->pluck('id')->toJson() }} }">
                    @csrf
                    @method('PUT')

                    <h3 class="mb-1 text-xs font-medium uppercase tracking-wide text-neutral-500">Confirmar recepción</h3>
                    <p class="mb-3 text-sm text-neutral-500">
                        Vienen marcadas todas. <span class="font-medium text-neutral-700">Desmarca la que no llegó</span> —
                        queda registrado como diferencia y se avisa a jefatura.
                    </p>

                    <div class="space-y-2">
                        @foreach ($traslado->ordenes as $orden)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 p-3 transition"
                                   :class="marcadas.includes({{ $orden->id }}) ? 'border-neutral-200' : 'border-red-300 bg-red-50'">
                                <input type="checkbox" name="recibidas[]" value="{{ $orden->id }}" x-model.number="marcadas"
                                       class="mt-0.5 h-4 w-4 shrink-0 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs text-neutral-400">{{ $orden->folio }}</span>
                                        <span class="truncate text-sm font-medium text-neutral-900">{{ $orden->cliente_nombre }}</span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-neutral-500">
                                        {{ $orden->tipo_equipo_label }}@if ($orden->numero_serie) · N° {{ $orden->numero_serie }}@endif
                                        @if ($orden->modelo) · {{ $orden->modelo }}@endif
                                    </span>
                                    <span class="mt-0.5 block text-xs font-medium text-red-600" x-show="! marcadas.includes({{ $orden->id }})" x-cloak>
                                        No llegó
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <x-input-label for="receptor_nombre">Quién recibe <span class="text-red-500">*</span></x-input-label>
                            <x-text-input id="receptor_nombre" name="receptor_nombre" type="text" class="mt-1.5 w-full" required
                                          maxlength="191" :value="old('receptor_nombre', auth()->user()->name)" />
                            <x-input-error :messages="$errors->get('receptor_nombre')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="observaciones_recepcion" value="Observaciones de la recepción" />
                            <x-textarea id="observaciones_recepcion" name="observaciones_recepcion" rows="2" class="mt-1.5"
                                        placeholder="Ej. una llegó con la tapa quebrada">{{ old('observaciones_recepcion') }}</x-textarea>
                            <x-input-error :messages="$errors->get('observaciones_recepcion')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-primary-button class="w-full justify-center py-3 text-base">
                            <span x-show="marcadas.length === {{ $traslado->ordenes->count() }}">Confirmar que llegaron todas</span>
                            <span x-show="marcadas.length !== {{ $traslado->ordenes->count() }}" x-cloak>
                                Confirmar recepción (<span x-text="marcadas.length"></span> de {{ $traslado->ordenes->count() }})
                            </span>
                        </x-primary-button>
                    </div>
                </form>
            @else
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
                    La recepción la confirma el técnico, el jefe de bodega o el jefe de ventas en {{ $traslado->destino?->nombre }}.
                </div>
            @endcan
        @endif

        {{-- Las máquinas del traslado (siempre visibles, recibido o no). --}}
        <x-list-card title="Máquinas del traslado" :count="$traslado->ordenes->count()"
                     :countLabel="\Illuminate\Support\Str::plural('máquina', $traslado->ordenes->count())">
            @foreach ($traslado->ordenes as $orden)
                <x-list-row>
                    <a href="{{ route('admin.servicio-tecnico.show', $orden) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-neutral-400">{{ $orden->folio }}</span>
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $orden->cliente_nombre }}</p>
                            @if ($orden->traslado_recibida_at)
                                <x-badge variant="neutral">En el taller</x-badge>
                            @elseif ($traslado->recibido)
                                <x-badge variant="danger">No llegó</x-badge>
                            @else
                                <x-badge variant="brand">En tránsito</x-badge>
                            @endif
                        </div>
                        <p class="truncate text-sm text-neutral-500">
                            {{ $orden->tipo_equipo_label }}@if ($orden->numero_serie) · N° {{ $orden->numero_serie }}@endif
                        </p>
                    </a>
                    <x-slot name="actions">
                        <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                    </x-slot>
                </x-list-row>
            @endforeach
        </x-list-card>
    </div>
</x-app-layout>
