{{--
    Documento tributario de una orden (M05 · B8) — ENSAYO EN SECO.

    El ORDEN de la pantalla imita el de Bsale por pedido de Gerencia (quien
    factura ahí tiene que encontrar las cosas donde las busca): líneas al medio con
    Cantidad · Detalle · $/unidad · % desc. · Subtotal, cliente abajo a la
    izquierda, tipo de documento junto al total, Confirmar abajo a la derecha.
    Los COLORES y los componentes son los de DaliGo, no los de Bsale.

    Hoy no emite: lo impide el candado, y la pantalla lo dice.
--}}
<x-app-layout ancho="listado">
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $equipo = collect([
            ucfirst($orden->tipo_equipo),
            $orden->modelo,
            $orden->numero_serie ? 'N° '.$orden->numero_serie : null,
        ])->filter()->implode(' · ');
    @endphp

    <x-slot name="header">
        {{-- El padre es DOCUMENTOS, no la orden: es de donde se entra a esta
             pantalla, y es el módulo que la sidebar marca como activo (ver el
             `activo_extra` de facturacion en MenuPrincipal). Apuntaba a la orden y
             eso sacaba al usuario de Facturación sin haber pedido salir.
             El enlace a la orden sigue disponible más abajo, como enlace y no como
             Volver — el Volver tiene UN destino garantizado (doctrina P-NAV-08). --}}
        <x-page-header :title="'Documento tributario · '.$orden->folio"
                       :subtitle="$orden->cliente_nombre.($equipo ? ' · '.$equipo : '')"
                       :back="route('admin.dte.index')" backTitle="Volver a Documentos" />
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        {{-- Estado del candado. Va ARRIBA y no escondido al pie: es la información
             más importante de la pantalla. --}}
        @if ($bloqueo)
            <div class="rounded-2xl border border-brand-200 bg-brand-50 p-4">
                <div class="flex items-start gap-3">
                    <x-icon.information-circle class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold text-brand-900">Ensayo en seco — no se va a emitir nada</p>
                        <p class="mt-1 text-brand-800">{{ $bloqueo }}</p>
                        <p class="mt-2 text-xs text-brand-700">
                            Abajo está el documento tal como saldría. Sirve para revisarlo con Contabilidad
                            <span class="font-medium">antes</span> de que exista.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @if ($yaEmitido)
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                <p class="text-sm font-semibold text-neutral-900">Esta orden ya tiene un documento</p>
                <p class="mt-1 text-sm text-neutral-600">
                    {{ $yaEmitido->tipo_label }} {{ $yaEmitido->folio_label }} ·
                    <x-badge :variant="$yaEmitido->estado_variante">{{ $yaEmitido->estado_label }}</x-badge>
                </p>
                <p class="mt-1 text-xs text-neutral-400">
                    Un documento tributario no se borra: si está mal, se corrige con nota de crédito.
                </p>
            </div>
        @endif

        @if ($problema)
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-semibold text-red-900">No se puede armar el documento</p>
                <p class="mt-1 text-sm text-red-800">{{ $problema }}</p>
            </div>
        @endif

        @if ($documento)
            <form method="GET" action="{{ route('admin.servicio-tecnico.documento', $orden) }}"
                  class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">

                {{-- ── Zona 1: cabecera con el buscador (como el de Bsale) ────────── --}}
                <div class="flex flex-col gap-2 border-b border-neutral-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                        Detalle del documento
                    </p>
                    <p class="text-xs text-neutral-400">
                        Las líneas salen de
                        <a href="{{ route('admin.servicio-tecnico.show', $orden) }}"
                           class="font-medium text-brand-600 hover:text-brand-700">la orden {{ $orden->folio }}</a>:
                        repuestos y mano de obra. Para cambiarlas, se editan en
                        <a href="{{ route('admin.servicio-tecnico.cotizacion', $orden) }}"
                           class="font-medium text-brand-600 hover:text-brand-700">Cotización</a>.
                    </p>
                </div>

                {{-- ── Zona 2: las líneas ─────────────────────────────────────────── --}}
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[38rem] text-sm">
                        <thead>
                            <tr class="border-b border-neutral-100 bg-neutral-50 text-xs font-medium uppercase tracking-wide text-neutral-500">
                                <th class="w-20 px-4 py-2 text-right sm:px-6">Cantidad</th>
                                <th class="px-4 py-2 text-left">Detalle</th>
                                <th class="w-28 px-4 py-2 text-right">$/unidad</th>
                                <th class="w-20 px-4 py-2 text-right">% desc.</th>
                                <th class="w-28 px-4 py-2 text-right sm:px-6">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach ($documento->lineas as $linea)
                                <tr>
                                    <td class="px-4 py-3 text-right tabular-nums text-neutral-900 sm:px-6">{{ $linea->cantidad }}</td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-neutral-900">{{ $linea->descripcion }}</p>
                                        <p class="mt-0.5 text-xs text-neutral-400">
                                            @if ($linea->codigoProducto)
                                                <span class="font-mono">{{ $linea->codigoProducto }}</span>
                                            @else
                                                Glosa libre (no vino del catálogo)
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-neutral-700">{{ $clp($linea->precioNetoUnitario) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-neutral-400">{{ $linea->descuentoPct > 0 ? $linea->descuentoPct.'%' : '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums font-medium text-neutral-900 sm:px-6">{{ $clp($linea->netoLinea()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-neutral-100 px-4 py-2 text-xs text-neutral-400 sm:px-6">
                    Nr. líneas: {{ count($documento->lineas) }} ·
                    Los precios de esta tabla van <span class="font-medium">netos</span> (sin IVA), como los exige el documento tributario.
                </p>

                {{-- ── Zonas 4, 5 y 6: cliente · tipo de documento · totales ──────── --}}
                <div class="grid gap-5 border-t border-neutral-100 bg-neutral-50 px-4 py-4 sm:px-6 xl:grid-cols-3">

                    {{-- Cliente (abajo a la izquierda, como en Bsale) --}}
                    <div class="text-sm">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cliente</p>
                        <p class="mt-1 font-medium text-neutral-900">{{ $documento->receptorNombre ?: 'Consumidor final' }}</p>
                        <p class="text-neutral-600">{{ $documento->receptorRut ?: 'Sin RUT' }}</p>
                        @if ($documento->receptorGiro)
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $documento->receptorGiro }}</p>
                        @endif
                        @if ($documento->receptorDireccion)
                            <p class="text-xs text-neutral-500">{{ collect([$documento->receptorDireccion, $documento->receptorComuna])->filter()->implode(', ') }}</p>
                        @endif
                        @if ($tipoDte === \App\Services\Dte\DocumentoTributario::FACTURA_AFECTA && blank($documento->receptorGiro))
                            <p class="mt-2 text-xs font-medium text-red-600">
                                Falta el giro del cliente: una factura no es válida sin él. Se completa en la ficha del cliente.
                            </p>
                        @endif
                    </div>

                    {{-- Tipo de documento y forma de pago (lo elige quien atiende) --}}
                    <div class="space-y-3">
                        <div>
                            <x-input-label for="tipo_dte" value="Tipo de documento" />
                            <x-select id="tipo_dte" name="tipo_dte" class="mt-1.5" x-on:change="$el.form.submit()">
                                <option value="{{ \App\Services\Dte\DocumentoTributario::BOLETA }}" @selected($tipoDte === \App\Services\Dte\DocumentoTributario::BOLETA)>Boleta electrónica</option>
                                <option value="{{ \App\Services\Dte\DocumentoTributario::FACTURA_AFECTA }}" @selected($tipoDte === \App\Services\Dte\DocumentoTributario::FACTURA_AFECTA)>Factura electrónica</option>
                            </x-select>
                            <x-input-hint>Lo decide el cliente al momento de pagar.</x-input-hint>
                        </div>
                        <div>
                            <x-input-label for="forma_pago" value="Forma de pago" />
                            <x-select id="forma_pago" name="forma_pago" class="mt-1.5" x-on:change="$el.form.submit()">
                                @foreach (\App\Services\Dte\FormaPago::TODAS as $forma)
                                    <option value="{{ $forma }}" @selected($formaPago === $forma)>{{ \App\Services\Dte\FormaPago::etiqueta($forma) }}</option>
                                @endforeach
                            </x-select>
                            <x-input-hint>El pago se registra al emitir.</x-input-hint>
                        </div>
                    </div>

                    {{-- Totales (abajo a la derecha, como en Bsale) --}}
                    <div class="text-sm">
                        <dl class="space-y-1">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-neutral-500">Neto</dt>
                                <dd class="tabular-nums text-neutral-900">{{ $clp($documento->neto()) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-neutral-500">IVA (19%)</dt>
                                <dd class="tabular-nums text-neutral-900">{{ $clp($documento->iva()) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-neutral-200 pt-1.5">
                                <dt class="font-semibold text-neutral-900">Total</dt>
                                <dd class="text-lg font-semibold tabular-nums text-neutral-900">{{ $clp($documento->totalEfectivo()) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-neutral-400">
                            El total es el que aceptó el cliente en la cotización ({{ $clp($orden->costo_total) }});
                            el neto y el IVA se derivan de ahí, así neto + IVA da exacto.
                        </p>
                    </div>
                </div>

                {{-- ── Zona 7: la acción ──────────────────────────────────────────── --}}
                <div class="flex flex-col gap-3 border-t border-neutral-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs text-neutral-400">
                        Emisor: <span class="font-medium text-neutral-600">{{ ucfirst($emisor) }}</span> ·
                        ambiente <span class="font-medium text-neutral-600">{{ $ambiente }}</span> ·
                        identificador <span class="font-mono">{{ $documento->salesId }}</span>
                    </p>
                    @if ($bloqueo)
                        <span class="inline-flex items-center gap-2 rounded-lg bg-neutral-100 px-3 py-2 text-sm font-semibold text-neutral-400"
                              title="{{ $bloqueo }}">
                            Confirmar y emitir
                        </span>
                    @else
                        <x-primary-button type="button" disabled>Confirmar y emitir</x-primary-button>
                    @endif
                </div>
            </form>

            {{-- Falta configurar algo de Bsale. El documento existe y se ve igual;
                 lo que no se puede todavía es armar el mensaje para el emisor. --}}
            @if ($faltaConfigurar)
                <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
                    <p class="text-sm font-semibold text-neutral-900">Falta un dato de {{ ucfirst($emisor) }} para poder emitir</p>
                    <p class="mt-1 text-sm text-neutral-600">{{ $faltaConfigurar }}</p>
                    <p class="mt-2 text-xs text-neutral-400">
                        El documento de arriba está correcto: lo que falta es el identificador que usa
                        {{ ucfirst($emisor) }}. Se completa una vez, leyéndolo de la cuenta.
                    </p>
                </div>
            @endif

            {{-- Lo que se le enviaría al emisor, para quien quiera verlo. Colapsado:
                 es para desarrollo y para diagnosticar, no para el mostrador. --}}
            @if ($payload)
                <div x-data="{ abierto: false }" class="rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <x-collapsible label="Ver lo que se le enviaría a {{ ucfirst($emisor) }}" model="abierto">
                        <pre class="overflow-x-auto rounded-lg bg-neutral-900 p-4 text-xs leading-relaxed text-neutral-100">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        <p class="mt-2 text-xs text-neutral-400">
                            Este es el mensaje exacto. Todavía no se envió y, con el candado puesto, no se puede enviar.
                        </p>
                    </x-collapsible>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
