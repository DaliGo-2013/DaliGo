{{--
    Ficha de una devolución (M13): el destino de la campanita y del correo
    interno. Cuelga del listado → lleva «Volver» (doctrina P-NAV-08). La
    acción disponible depende del estado: solicitada → recibir (con las fotos
    de BODEGA, el segundo momento de evidencia) · recibida → evaluar
    (categorización P-M13-02) · evaluada → resolver (P-M13-03). Resuelta =
    solo lectura.
--}}
<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="'Devolución '.$devolucion->folio"
                       :subtitle="($devolucion->cliente_nombre ?: '—').' · '.(\App\Models\Devolucion::CANALES[$devolucion->canal] ?? $devolucion->canal)"
                       :back="route('admin.devoluciones.index')" backTitle="Devoluciones" />
    </x-slot>

    <div class="space-y-4 py-6" x-data="{ paneles: { recibir: false, evaluar: false, resolver: false } }">
        <x-status-alert :status="session('status')" />

        {{-- Estado + línea de tiempo mínima --}}
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :variant="$devolucion->esResuelta() ? 'neutral' : 'brand'">{{ ucfirst($devolucion->estado) }}</x-badge>
            <span class="text-xs text-neutral-500">Declarada {{ $devolucion->created_at?->enChile()->format('d-m-Y H:i') }}</span>
            @if ($devolucion->recibida_at)
                <span class="text-xs text-neutral-500">· Recibida {{ $devolucion->recibida_at->enChile()->format('d-m-Y H:i') }} ({{ $devolucion->recibidaPor?->name ?? '—' }})</span>
            @endif
            @if ($devolucion->resuelta_at)
                <span class="text-xs text-neutral-500">· Resuelta {{ $devolucion->resuelta_at->enChile()->format('d-m-Y H:i') }} ({{ $devolucion->resueltaPor?->name ?? '—' }})</span>
            @endif
        </div>

        <x-seccion titulo="Cliente y compra">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-neutral-500">Cliente</dt>
                    <dd class="font-medium text-neutral-800">{{ $devolucion->cliente_nombre }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-neutral-500">RUT</dt>
                    <dd class="font-medium tabular-nums text-neutral-800">{{ $devolucion->cliente_rut ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-neutral-500">Correo</dt>
                    <dd class="font-medium text-neutral-800">{{ $devolucion->cliente_email }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-neutral-500">Teléfono</dt>
                    <dd class="font-medium text-neutral-800">{{ $devolucion->cliente_telefono ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-neutral-500">Documento de venta</dt>
                    <dd class="font-medium text-neutral-800">
                        {{ $devolucion->documentoVenta?->folio ?? $devolucion->folio_referencia ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-neutral-500">Sucursal</dt>
                    <dd class="font-medium text-neutral-800">{{ $devolucion->sucursal?->nombre ?? '—' }}</dd>
                </div>
            </dl>
            <div class="rounded-lg bg-neutral-50 px-3 py-2">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Motivo del cliente</p>
                <p class="mt-0.5 text-sm text-neutral-700">{{ $devolucion->motivo }}</p>
            </div>
        </x-seccion>

        <x-seccion titulo="Productos">
            <ul class="divide-y divide-neutral-100">
                @foreach ($devolucion->items as $item)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="text-neutral-800">{{ $item->cantidad }}× {{ $item->descripcion }}</span>
                        @if ($item->estado_producto)
                            <x-badge :variant="$item->estado_producto === \App\Models\DevolucionItem::APTO ? 'brand' : 'danger'">
                                {{ \App\Models\DevolucionItem::ESTADOS_PRODUCTO[$item->estado_producto] }}
                            </x-badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-seccion>

        @if ($devolucion->causa)
            <x-seccion titulo="Evaluación">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3 sm:block">
                        <dt class="text-neutral-500">Causa</dt>
                        <dd class="font-medium text-neutral-800">{{ \App\Models\Devolucion::CAUSAS[$devolucion->causa] ?? $devolucion->causa }}</dd>
                    </div>
                    @if ($devolucion->transportista)
                        <div class="flex justify-between gap-3 sm:block">
                            <dt class="text-neutral-500">Transportista</dt>
                            <dd class="font-medium text-neutral-800">{{ $devolucion->transportista }} · seguimiento {{ $devolucion->seguimiento ?? '—' }}</dd>
                        </div>
                    @endif
                    @if ($devolucion->conductor)
                        <div class="flex justify-between gap-3 sm:block">
                            <dt class="text-neutral-500">Conductor propio</dt>
                            <dd class="font-medium text-neutral-800">{{ $devolucion->conductor->nombre }}</dd>
                        </div>
                    @endif
                </dl>
            </x-seccion>
        @endif

        <x-seccion titulo="Evidencia fotográfica">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Del cliente (al declarar)</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($fotosCliente as $foto)
                            <a href="{{ route('admin.devoluciones.foto', $foto) }}" target="_blank" rel="noopener">
                                <img src="{{ route('admin.devoluciones.foto', $foto) }}" alt="Foto del cliente"
                                     class="h-24 w-24 rounded-lg border border-neutral-200 object-cover">
                            </a>
                        @empty
                            <p class="text-sm text-neutral-500">Sin fotos.</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">De bodega (al recibir)</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @forelse ($fotosBodega as $foto)
                            <a href="{{ route('admin.devoluciones.foto', $foto) }}" target="_blank" rel="noopener">
                                <img src="{{ route('admin.devoluciones.foto', $foto) }}" alt="Foto de bodega"
                                     class="h-24 w-24 rounded-lg border border-neutral-200 object-cover">
                            </a>
                        @empty
                            <p class="text-sm text-neutral-500">Todavía no se recibe en bodega.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-seccion>

        @if ($devolucion->movimientos->isNotEmpty())
            <x-seccion titulo="Kardex local">
                <ul class="divide-y divide-neutral-100">
                    @foreach ($devolucion->movimientos as $mov)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="text-neutral-800">{{ $mov->tipo === \App\Models\DevolucionMovimiento::REINGRESO ? 'Reingreso' : 'Merma' }} · {{ rtrim(rtrim(number_format($mov->cantidad, 4, ',', '.'), '0'), ',') }} un.</span>
                            <span class="text-xs text-neutral-500">{{ $mov->bodega_destino ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="text-xs text-neutral-400">Registro local: el stock de Bsale no se toca (se empuja cuando exista M04).</p>
            </x-seccion>
        @endif

        {{-- ── Acciones por estado (solo con manage) ─────────────────────── --}}
        @can('manage devoluciones')
            @if ($devolucion->estado === \App\Models\Devolucion::SOLICITADA)
                <x-collapsible label="Recibir en bodega…" model="paneles.recibir">
                    <form method="POST" action="{{ route('admin.devoluciones.recibir', $devolucion->id) }}"
                          enctype="multipart/form-data" class="space-y-4" data-una-vez>
                        @csrf
                        <p class="text-sm text-neutral-500">
                            Toma tus propias fotos del estado REAL en que llegó: son la otra mitad de la evidencia
                            si el daño vino del transporte.
                        </p>
                        <div>
                            <x-input-label for="fotos_bodega" value="Fotos de bodega *" />
                            <x-archivo-input id="fotos_bodega" name="fotos[]" accept="image/*" capture="environment" multiple required
                                onchange="optimizarFotoInput(this)"
                                texto="Tomar o elegir fotos"
                                vacio="Todavía no eliges las fotos" />
                            <x-input-error :messages="$errors->get('fotos')" class="mt-1.5" />
                            <x-input-error :messages="$errors->get('fotos.0')" class="mt-1.5" />
                        </div>
                        <x-form-footer>
                            <x-primary-button type="submit">Confirmar recepción</x-primary-button>
                        </x-form-footer>
                    </form>
                </x-collapsible>
            @elseif ($devolucion->estado === \App\Models\Devolucion::RECIBIDA)
                <x-collapsible label="Categorizar…" model="paneles.evaluar">
                    <form method="POST" action="{{ route('admin.devoluciones.evaluar', $devolucion->id) }}" class="space-y-4" data-una-vez
                          x-data="{ causa: @js(old('causa')) }">
                        @csrf
                        <div>
                            <x-input-label value="Causa *" />
                            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                @foreach (\App\Models\Devolucion::CAUSAS as $valor => $label)
                                    <x-chip-radio name="causa" :value="$valor" :label="$label"
                                                  :checked="old('causa') === $valor" x-model="causa" required />
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('causa')" class="mt-1.5" />
                        </div>

                        {{-- Regla automática (P-M13-02): transporte exige el dato del
                             reclamo. x-show NO esconde campos required (mina de la
                             bitácora 28-07): los inputs solo son required si aplican. --}}
                        <div x-show="causa === 'transporte'" x-cloak class="space-y-4">
                            <div>
                                <x-input-label for="transportista" value="Transportista *" />
                                <x-text-input id="transportista" name="transportista" type="text" class="mt-1.5 block w-full"
                                              :value="old('transportista')" x-bind:required="causa === 'transporte'"
                                              placeholder="Chilexpress, Starken…" />
                                <x-input-error :messages="$errors->get('transportista')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="seguimiento" value="N° de seguimiento *" />
                                <x-text-input id="seguimiento" name="seguimiento" type="text" class="mt-1.5 block w-full"
                                              :value="old('seguimiento')" x-bind:required="causa === 'transporte'" />
                                <x-input-error :messages="$errors->get('seguimiento')" class="mt-1.5" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Estado de cada producto *" />
                            <div class="mt-1.5 space-y-3">
                                @foreach ($devolucion->items as $item)
                                    <div>
                                        <p class="mb-1 text-sm text-neutral-700">{{ $item->cantidad }}× {{ $item->descripcion }}</p>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                            @foreach (\App\Models\DevolucionItem::ESTADOS_PRODUCTO as $valor => $label)
                                                <x-chip-radio :name="'items['.$item->id.']'" :value="$valor" :label="$label"
                                                              :checked="old('items.'.$item->id) === $valor" required />
                                            @endforeach
                                        </div>
                                        <x-input-error :messages="$errors->get('items.'.$item->id)" class="mt-1.5" />
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <x-form-footer>
                            <x-primary-button type="submit">Guardar evaluación</x-primary-button>
                        </x-form-footer>
                    </form>
                </x-collapsible>
            @elseif ($devolucion->estado === \App\Models\Devolucion::EVALUADA)
                <x-collapsible label="Resolver…" model="paneles.resolver">
                    <form method="POST" action="{{ route('admin.devoluciones.resolver', $devolucion->id) }}" class="space-y-4" data-una-vez
                          x-data="{ salida: @js(old('salida')) }">
                        @csrf
                        <div>
                            <x-input-label value="Salida *" />
                            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <x-chip-radio name="salida" value="reembolso" label="Reembolso" :checked="old('salida') === 'reembolso'" x-model="salida" required />
                                <x-chip-radio name="salida" value="reingreso" label="Reingreso a bodega" :checked="old('salida') === 'reingreso'" x-model="salida" required />
                                <x-chip-radio name="salida" value="rechazo" label="Rechazar" :checked="old('salida') === 'rechazo'" x-model="salida" required />
                            </div>
                            <x-input-error :messages="$errors->get('salida')" class="mt-1.5" />
                        </div>

                        <div x-show="salida === 'reembolso'" x-cloak>
                            <x-input-label for="monto_reembolso" value="Monto a reembolsar (CLP) *" />
                            <x-text-input id="monto_reembolso" name="monto_reembolso" type="number" min="1" class="mt-1.5 block w-40"
                                          :value="old('monto_reembolso')" x-bind:required="salida === 'reembolso'" />
                            <x-input-hint>Sobre el umbral configurado queda pendiente de aprobación (M14); bajo él se aplica al tiro con registro.</x-input-hint>
                            <x-input-error :messages="$errors->get('monto_reembolso')" class="mt-1.5" />
                        </div>

                        <div>
                            <x-input-label for="resolucion_motivo" value="Comentario de la resolución" />
                            <x-textarea id="resolucion_motivo" name="resolucion_motivo" rows="2" class="mt-1.5 block w-full"
                                        x-bind:required="salida === 'rechazo'"
                                        placeholder="Obligatorio si rechazas: el cliente lo va a leer.">{{ old('resolucion_motivo') }}</x-textarea>
                            <x-input-error :messages="$errors->get('resolucion_motivo')" class="mt-1.5" />
                        </div>

                        <x-form-footer>
                            <x-primary-button type="submit">Resolver devolución</x-primary-button>
                        </x-form-footer>
                    </form>
                </x-collapsible>
            @endif
        @endcan
    </div>
</x-app-layout>
