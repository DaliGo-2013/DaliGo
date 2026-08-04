<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Despachar máquinas al taller"
                       subtitle="Quedas registrado como responsable de la entrega."
                       :back="route('admin.traslados.index')" backTitle="Volver a traslados" />
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                Revisa los datos: hay {{ $errors->count() }} campo(s) con problemas más abajo.
            </div>
        @endif

        @if ($ordenes->isEmpty())
            <div class="rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm text-neutral-600">No hay máquinas pendientes de despacho.</p>
                <p class="mt-1 text-xs text-neutral-400">
                    Acá aparecen las máquinas recibidas en una sucursal que no repara y que todavía no salieron al taller.
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.traslados.store') }}" class="space-y-5" data-una-vez
                  x-data="{ elegidas: [] }">
                @csrf

                {{-- Responsable de la entrega --}}
                <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Responsable de la entrega</h3>
                    <div class="space-y-4">
                        <div>
                            <x-input-label for="emisor_nombre">Quién entrega <span class="text-red-500">*</span></x-input-label>
                            <x-text-input id="emisor_nombre" name="emisor_nombre" type="text" class="mt-1.5 w-full" required
                                          maxlength="191" :value="old('emisor_nombre', $emisorSugerido)" />
                            {{-- Editable a propósito: hoy no hay cuentas creadas en las
                                 sucursales, así que puede estar cargando el despacho una
                                 persona distinta de la que entrega físicamente. Tu cuenta
                                 queda registrada igual, aparte de este nombre. --}}
                            <x-input-hint>El nombre de quien entrega físicamente las máquinas. Tu cuenta queda registrada aparte.</x-input-hint>
                            <x-input-error :messages="$errors->get('emisor_nombre')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="conductor" value="Conductor que las lleva" />
                            <x-select id="conductor" name="conductor" class="mt-1.5">
                                <option value="">— Sin conductor registrado —</option>
                                @foreach ($conductores as $c)
                                    <option value="{{ $c }}" @selected(old('conductor') === $c)>{{ $c }}</option>
                                @endforeach
                            </x-select>
                            <x-input-error :messages="$errors->get('conductor')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="observaciones_envio" value="Observaciones del envío" />
                            <x-textarea id="observaciones_envio" name="observaciones_envio" rows="2" class="mt-1.5"
                                        placeholder="Ej. van 3 en caja y 1 sin embalaje">{{ old('observaciones_envio') }}</x-textarea>
                            <x-input-error :messages="$errors->get('observaciones_envio')" class="mt-2" />
                        </div>
                    </div>
                </div>

                {{-- Máquinas a despachar --}}
                <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                            Máquinas a despachar (<span x-text="elegidas.length">0</span> de {{ $ordenes->count() }})
                        </h3>
                        <button type="button"
                                x-on:click="elegidas = elegidas.length === {{ $ordenes->count() }} ? [] : {{ $ordenes->pluck('id')->toJson() }}"
                                class="text-xs font-medium text-brand-600 hover:text-brand-700">
                            <span x-show="elegidas.length !== {{ $ordenes->count() }}">Marcar todas</span>
                            <span x-show="elegidas.length === {{ $ordenes->count() }}" x-cloak>Desmarcar todas</span>
                        </button>
                    </div>

                    {{-- Agrupadas por sucursal: un traslado tiene UN origen, así que si
                         se marcan máquinas de dos sucursales se crea un traslado por
                         cada una — el responsable de la entrega es de una sola. --}}
                    <div class="space-y-4">
                        @foreach ($ordenes->groupBy(fn ($o) => $o->sucursal?->nombre ?? 'Sin sucursal') as $sucursal => $delGrupo)
                            <div>
                                <p class="mb-2 text-xs font-semibold text-neutral-700">{{ $sucursal }} ({{ $delGrupo->count() }})</p>
                                <div class="space-y-2">
                                    @foreach ($delGrupo as $orden)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-neutral-200 p-3 transition hover:border-brand-300"
                                               :class="elegidas.includes({{ $orden->id }}) && 'border-brand-400 bg-brand-50'">
                                            <input type="checkbox" name="ordenes[]" value="{{ $orden->id }}" x-model.number="elegidas"
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
                                                <span class="mt-0.5 block text-xs text-neutral-400">
                                                    Ingresó {{ $orden->fecha_ingreso?->format('d-m-Y') }}
                                                    @if ($orden->recibida_por) · recibió {{ $orden->recibida_por }} @endif
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('ordenes')" class="mt-2" />
                </div>

                <div class="rounded-xl bg-neutral-50 p-3 text-xs text-neutral-500">
                    Al despachar, el taller recibe el aviso al instante y estas máquinas quedan
                    <span class="font-medium text-neutral-700">en tránsito</span>: no se pueden reparar
                    hasta que alguien del taller confirme que llegaron.
                </div>

                <x-primary-button class="w-full justify-center py-3 text-base" x-bind:disabled="elegidas.length === 0">
                    Despachar al taller
                </x-primary-button>
            </form>
        @endif
    </div>
</x-app-layout>
