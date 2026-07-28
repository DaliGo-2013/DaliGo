{{--
    Pantalla de escaneo en bodega (P-DSP-04). El GET solo LEE; el retiro lo
    confirma el POST. Tras el POST se vuelve acá con session('escaneo') = el
    veredicto, porque el operador tiene la carga delante y necesita leer
    "entrega" o "NO entregues" sin buscar nada.

    Paleta de 4: el rojo aparece SOLO en el rechazo (es la excepción sancionada
    para lo negativo). El caso válido va en naranjo de marca sólido y el estado
    de reposo en neutro.
--}}
@php
    $veredicto = session('escaneo');
    $puedeRetirar = $despacho->estado === \App\Models\Despacho::PREPARADO;
@endphp

<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Escaneo de retiro"
                       :subtitle="'Despacho '.$despacho->codigo"
                       :back="route('admin.despachos.index')" backTitle="Volver a despachos" />
    </x-slot>

    <div class="space-y-6 py-8 sm:py-12">

        {{-- VEREDICTO del escaneo recién hecho --}}
        @if ($veredicto === \App\Models\EscaneoDespacho::VALIDO)
            <div class="rounded-2xl bg-brand-600 p-6 text-center text-white shadow-sm">
                <p class="text-2xl font-semibold">Retiro autorizado</p>
                <p class="mt-1 text-sm text-brand-50">Entrega la carga y registra la salida del vehículo.</p>
            </div>
        @elseif ($veredicto === \App\Models\EscaneoDespacho::DOBLE_RETIRO)
            <div class="dg-shake rounded-2xl bg-red-600 p-6 text-center text-white shadow-sm">
                <p class="text-2xl font-semibold">NO entregues: doble retiro</p>
                <p class="mt-1 text-sm text-red-50">
                    Esta carga ya salió de bodega
                    @if ($despacho->retirado_at)
                        el {{ $despacho->retirado_at->enChile()->format('d-m-Y \a \l\a\s H:i') }}
                    @endif
                    . Avisa al jefe de bodega antes de mover nada.
                </p>
            </div>
        @elseif ($veredicto === \App\Models\EscaneoDespacho::ESTADO_INVALIDO)
            <div class="dg-shake rounded-2xl bg-red-50 p-6 text-center ring-1 ring-inset ring-red-200">
                <p class="text-xl font-semibold text-red-700">Este despacho ya está cerrado</p>
                <p class="mt-1 text-sm text-red-700">Estado actual: {{ $despacho->estado }}. No corresponde retirar.</p>
            </div>
        @endif

        {{-- Qué es esta carga --}}
        <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-6 py-3">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">La carga</h3>
            </div>
            <dl class="divide-y divide-neutral-100">
                <div class="flex items-center justify-between px-6 py-3">
                    <dt class="text-sm text-neutral-500">Código</dt>
                    <dd class="text-lg font-semibold tracking-tight text-neutral-900">{{ $despacho->codigo }}</dd>
                </div>
                <div class="flex items-center justify-between px-6 py-3">
                    <dt class="text-sm text-neutral-500">Cliente</dt>
                    <dd class="text-sm font-medium text-neutral-900">{{ $despacho->documento?->cliente?->razon_social ?? 'Sin cliente' }}</dd>
                </div>
                <div class="flex items-center justify-between px-6 py-3">
                    <dt class="text-sm text-neutral-500">Documento</dt>
                    <dd class="text-sm text-neutral-900">Folio {{ $despacho->documento?->folio ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between px-6 py-3">
                    <dt class="text-sm text-neutral-500">Zona</dt>
                    <dd class="text-sm text-neutral-900">{{ $despacho->zona?->nombre ?? 'Sin zona' }}</dd>
                </div>
                <div class="flex items-center justify-between px-6 py-3">
                    <dt class="text-sm text-neutral-500">Estado</dt>
                    <dd><x-despacho.estado-badge :estado="$despacho->estado" /></dd>
                </div>
            </dl>
        </div>

        {{-- Acción: solo si la carga está esperando retiro --}}
        @if ($puedeRetirar)
            <form method="POST" action="{{ $urlRetiro }}" data-una-vez>
                @csrf
                <x-primary-button class="h-14 w-full justify-center text-base">
                    Autorizar el retiro de {{ $despacho->codigo }}
                </x-primary-button>
            </form>
        @endif

        {{-- CIERRE de la entrega. La entrega "de verdad" la firma el conductor en
             su PWA (P-DSP-05); esto es la contraparte de bodega para cerrar hoy.
             El parcial EXIGE saldo: un parcial sin saldo no se puede reclamar. --}}
        @if ($despacho->admiteEntrega())
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm"
                 x-data="{ parcial: {{ old('parcial') || $errors->has('entrega_observacion') ? 'true' : 'false' }} }">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cerrar el despacho</h3>

                <form method="POST" action="{{ route('admin.despachos.entrega', $despacho) }}" class="mt-4 space-y-4" data-una-vez>
                    @csrf
                    {{-- Patrón checkbox+hidden, y el NAME va en el CHECKBOX.
                         Antes el único portador del flag era un hidden con solo
                         `:value` de Alpine y el checkbox no tenía name: si Alpine
                         no corría, una entrega PARCIAL se grababa como ENTREGADO
                         con el saldo adentro — perdía dato de negocio en silencio
                         (hallazgo 2 del gate del Director, 28-07). Ahora el HTML
                         solo ya es correcto: el hidden manda 0 y el checkbox
                         marcado lo pisa con 1 por ir después. --}}
                    <input type="hidden" name="parcial" value="0">

                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="parcial" value="1" x-model="parcial"
                               @checked(old('parcial'))
                               class="mt-0.5 h-5 w-5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500/30">
                        <span class="text-sm text-neutral-700">
                            Entrega PARCIAL (quedó saldo pendiente)
                        </span>
                    </label>

                    {{-- SIN x-cloak a propósito: `[x-cloak]{display:none!important}`
                         dejaría el campo oculto PARA SIEMPRE si Alpine no corre, y
                         el servidor exige el saldo igual → el operador no podría
                         completarlo. Con Alpine hay un flash de un campo de texto
                         que se oculta al inicializar; se prefiere el flash a perder
                         la función. --}}
                    <div x-show="parcial">
                        <x-input-label for="entrega_observacion" value="Qué quedó pendiente *" />
                        <x-text-input id="entrega_observacion" name="entrega_observacion" type="text"
                                      class="mt-1.5 block w-full" :value="old('entrega_observacion')"
                                      placeholder="Ej. faltaron 4 botellones de 20L" />
                        <x-input-error :messages="$errors->get('entrega_observacion')" class="mt-2" />
                    </div>
                    <x-input-error :messages="$errors->get('estado')" class="mt-2" />

                    <x-form-footer>
                        <x-primary-button x-text="parcial ? 'Registrar entrega parcial' : 'Registrar entrega'">
                            Registrar entrega
                        </x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        @elseif ($despacho->entrega_observacion)
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Saldo pendiente</h3>
                <p class="mt-2 text-sm text-neutral-900">{{ $despacho->entrega_observacion }}</p>
                <p class="mt-1 text-xs text-neutral-500">
                    Entrega parcial registrada {{ $despacho->entregado_at?->enChile()->format('d-m-Y H:i') }}
                </p>
            </div>
        @endif

        {{-- Historial de escaneos: la evidencia. Append-only, incluye los
             rechazados — es lo que se revisa cuando aparece un doble retiro. --}}
        @if ($despacho->escaneos->isNotEmpty())
            <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-6 py-3">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                        Escaneos de este código · {{ $despacho->escaneos->count() }}
                    </h3>
                </div>
                <ul class="divide-y divide-neutral-100">
                    @foreach ($despacho->escaneos->sortByDesc('id') as $escaneo)
                        <li class="flex items-center gap-4 px-6 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-neutral-900">
                                    {{ $escaneo->operador?->name ?? 'Sin operador' }}
                                </p>
                                <p class="text-xs text-neutral-500">
                                    {{ $escaneo->created_at?->enChile()->format('d-m-Y H:i') }}
                                    @if ($escaneo->detalle)
                                        · {{ $escaneo->detalle }}
                                    @endif
                                </p>
                            </div>
                            @if ($escaneo->resultado === \App\Models\EscaneoDespacho::VALIDO)
                                <x-badge variant="brand">Válido</x-badge>
                            @else
                                <x-badge variant="danger">{{ str_replace('_', ' ', $escaneo->resultado) }}</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
