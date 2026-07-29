{{--
    Facturación · Estado (M05).

    Mientras no se emite, ESTA es la pantalla útil del módulo: qué está listo y qué
    falta para poder emitir. Convierte en algo mirable lo que si no vive en
    documentos y en la cabeza de quien programó.
--}}
<x-app-layout ancho="formulario">
    <x-slot name="header">
        {{-- Sin «Volver»: es un ítem del menú, se llega por la sidebar (doctrina
             P-NAV-08, candado en VolverTest). Desde Documentos se llega por el
             enlace del aviso. --}}
        <x-page-header title="Estado de la facturación"
                       subtitle="Qué está listo y qué falta para poder emitir documentos tributarios." />
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">

        {{-- Resumen: quién emite y con qué credencial. --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Conexión</h3>
            <dl class="mt-2 divide-y divide-neutral-100 text-sm">
                <div class="flex items-center justify-between gap-4 py-2">
                    <dt class="text-neutral-500">Emisor</dt>
                    <dd class="font-medium text-neutral-900">{{ ucfirst($emisor) }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                    <dt class="text-neutral-500">Ambiente de la credencial</dt>
                    <dd>
                        <x-badge :variant="$ambiente === 'produccion' ? 'danger' : 'neutral'">
                            {{ $ambiente === 'produccion' ? 'Producción' : 'Prueba' }}
                        </x-badge>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2">
                    <dt class="text-neutral-500">Documentos emitidos</dt>
                    <dd class="font-medium tabular-nums text-neutral-900">{{ $emitidos }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4 py-2">
                    <dt class="text-neutral-500">¿Puede emitir?</dt>
                    <dd class="text-right">
                        @if ($bloqueo)
                            <x-badge variant="neutral">No</x-badge>
                        @else
                            <x-badge variant="danger">Sí — los documentos serán reales</x-badge>
                        @endif
                    </dd>
                </div>
            </dl>
            @if ($ambiente === 'produccion')
                <p class="mt-3 rounded-lg bg-neutral-50 p-3 text-xs text-neutral-600">
                    La credencial está declarada como de <span class="font-medium">producción</span>. En Bsale la
                    dirección de la API es la misma para prueba y producción: lo único que decide si un documento es
                    real o de mentira es cuál credencial está puesta.
                </p>
            @endif
        </div>

        {{-- El checklist. --}}
        <div>
            <h3 class="mb-2 text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que falta para emitir</h3>
            <ul class="divide-y divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                @foreach ($faltantes as $item)
                    <li class="flex items-start gap-3 px-4 py-3 sm:px-6">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full {{ $item['listo'] ? 'bg-brand-600 text-white' : 'border border-neutral-300 bg-white' }}">
                            @if ($item['listo'])
                                <x-icon.check class="h-3.5 w-3.5" />
                            @endif
                        </span>
                        <div class="min-w-0 text-sm">
                            <p class="font-medium {{ $item['listo'] ? 'text-neutral-900' : 'text-neutral-600' }}">{{ $item['titulo'] }}</p>
                            <p class="mt-0.5 text-neutral-500">{{ $item['detalle'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-neutral-400">
                Los identificadores de Bsale se completan una sola vez, leyéndolos de la cuenta. Hasta entonces el
                sistema se niega a emitir en lugar de adivinarlos: emitir desde la oficina equivocada es un documento
                mal atribuido, y eso se corrige con nota de crédito.
            </p>
        </div>

        {{-- Lo que no depende del sistema. --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm sm:p-4">
            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que no depende del sistema</h3>
            <ul class="mt-2 space-y-2 text-sm text-neutral-600">
                <li class="flex gap-2">
                    <span class="text-neutral-300">•</span>
                    <span><span class="font-medium text-neutral-900">Autorización de Gerencia por escrito</span> para la
                    primera emisión real. Bsale recomienda hacerla en producción con un documento de monto bajo y
                    anularla con nota de crédito si hace falta: su ambiente de prueba no llega al SII.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-neutral-300">•</span>
                    <span><span class="font-medium text-neutral-900">Dos respuestas de Bsale</span>: a qué caja se
                    atribuyen los documentos emitidos por integración, y si se puede consultar el stock de folios.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-neutral-300">•</span>
                    <span><span class="font-medium text-neutral-900">Los campos de transporte del 1-nov-2026</span> para
                    las guías de despacho. Bsale respondió que va a cumplir, sin dar fecha.</span>
                </li>
            </ul>
        </div>

        {{-- Lo que sí está resuelto, para que no parezca que falta todo. --}}
        <div class="rounded-2xl border border-neutral-200 bg-neutral-50 p-3 sm:p-4">
            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ya resuelto</h3>
            <p class="mt-2 text-sm text-neutral-600">
                Las 8 reglas contables están definidas por Contabilidad y aplicadas en el sistema: cómo se reparte el
                IVA (manda el total que paga el cliente), quién elige entre boleta y factura, cómo se desglosa una
                reparación, desde qué sucursal se emite, cuándo se registra el pago y quién puede anular.
            </p>
            <p class="mt-2 text-xs text-neutral-400">
                El detalle completo, con las fuentes del SII, está en el informe de facturación electrónica.
            </p>
        </div>
    </div>
</x-app-layout>
