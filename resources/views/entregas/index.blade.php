{{--
    Hoja de ruta del conductor (P-DSP-05, M08-MVP). Pantalla de OPERARIO
    (doctrina CLAUDE.md): sin banda de cabecera, título compacto, mínimos
    elementos, objetivos táctiles grandes — se usa en el celular, en la calle.

    Los despachos viajan inline: con la pestaña ABIERTA la hoja sigue operable
    sin señal (las confirmaciones se encolan en IndexedDB y drenan al volver la
    señal). Recargar sin señal cae a /offline del SW — límite aceptado del MVP:
    cachear HTML autenticado está prohibido (SPIKE-PWA §3).
--}}
<x-app-layout ancho="formulario">
    <div class="space-y-5 py-6">

        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">Mis entregas</h2>
                <p class="mt-0.5 text-sm text-neutral-500">{{ \App\Support\FechaNegocio::ahora()->translatedFormat('l j \d\e F') }}</p>
            </div>
            {{-- Indicador propio: acá SÍ se puede trabajar sin señal (hay cola).
                 El <x-produccion.indicador-red> dice "espera la señal" — falso aquí. --}}
            <span x-data x-cloak x-show="! $store.red.online" role="status"
                  class="inline-flex shrink-0 items-center gap-2 rounded-full bg-neutral-800 px-3 py-1.5 text-xs font-medium text-white">
                <span class="h-2 w-2 rounded-full bg-white/60"></span>
                Sin señal — tus entregas se guardan y se envían solas
            </span>
        </div>

        <x-status-alert :status="session('status')" />

        @if ($despachos->isEmpty())
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 text-center shadow-sm sm:p-6">
                <p class="text-lg font-semibold text-neutral-900">Sin entregas pendientes</p>
                <p class="mt-1 text-sm text-neutral-500">Cuando bodega te asigne un despacho retirado, aparece aquí.</p>
            </div>
        @endif

        @foreach ($porZona as $zona => $grupo)
            <div class="space-y-3">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                    {{ $zona }} · {{ $grupo->count() }}
                </h3>

                @foreach ($grupo as $despacho)
                    @php
                        $cliente = $despacho->documento?->cliente;
                        $parada = $despacho->parada;
                        $cobra = $parada?->estado_cobro === \App\Models\HojaRutaParada::COBRO_EN_ENTREGA;
                        $direccion = collect([$cliente?->direccion, $cliente?->comuna ?? $cliente?->ciudad])->filter()->implode(', ');
                    @endphp
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6"
                         x-data="entregaForm({
                            url: '{{ route('entregas.confirmar', $despacho) }}',
                            urlRechazo: {{ \Illuminate\Support\Js::from($parada ? route('entregas.rechazar', $despacho) : '') }},
                            etiqueta: {{ \Illuminate\Support\Js::from($despacho->codigo.' · '.($cliente?->razon_social ?? 'Sin cliente')) }},
                            cobra: {{ $cobra ? 'true' : 'false' }},
                            total: {{ (int) round((float) ($despacho->documento?->total ?? 0)) }},
                         })"
                         :class="(encolada || rechazada) && 'opacity-60'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold tracking-tight text-neutral-900"
                                   :class="encolada && 'line-through'">{{ $despacho->codigo }}</p>
                                <p class="mt-0.5 truncate text-sm text-neutral-700">
                                    {{ $cliente?->razon_social ?? 'Sin cliente' }}
                                </p>
                                {{-- El hueco más grande vs el papel (P-DSP-09): a dónde ir. --}}
                                @if ($direccion !== '')
                                    <p class="mt-0.5 text-sm text-neutral-600" data-direccion>{{ $direccion }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    Folio {{ $despacho->documento?->folio ?? '—' }}
                                    · retirado {{ $despacho->retirado_at?->enChile()->format('H:i') ?? 's/h' }}
                                </p>
                            </div>
                            <x-despacho.estado-badge :estado="$despacho->estado" class="shrink-0" />
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            {{-- El conductor sabe ANTES de tocar la puerta si cobra (R4). --}}
                            @if ($parada)
                                @if ($cobra)
                                    <span class="inline-flex items-center rounded-full bg-brand-600 px-2.5 py-0.5 text-xs font-medium text-white ring-1 ring-inset ring-brand-600">
                                        Cobrar en entrega · ${{ number_format((float) ($despacho->documento?->total ?? 0), 0, ',', '.') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 ring-1 ring-inset ring-neutral-200">
                                        {{ $parada->estado_cobro === \App\Models\HojaRutaParada::COBRO_PAGADO ? 'Pagado' : 'Crédito' }}
                                    </span>
                                @endif
                            @endif
                            {{-- Llamar es UN toque (pantalla de operario). --}}
                            @if ($cliente?->telefono)
                                <a href="tel:{{ preg_replace('/[^+\d]/', '', $cliente->telefono) }}"
                                   class="inline-flex min-h-8 items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 ring-1 ring-inset ring-neutral-200 transition duration-150 hover:bg-neutral-200 active:scale-[0.98]">
                                    ☎ {{ $cliente->telefono }}
                                </a>
                            @endif
                        </div>

                        {{-- Confirmación encolada sin señal: queda tachada hasta drenar. --}}
                        <p x-show="encolada" x-cloak class="mt-3 rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-700">
                            Guardada en este teléfono — se envía sola al volver la señal.
                        </p>
                        <p x-show="rechazada" x-cloak class="mt-3 rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-700">
                            Rechazo guardado en este teléfono — se envía solo al volver la señal.
                        </p>

                        <div x-show="! encolada && ! rechazada">
                            <button type="button" x-show="! abierto" x-on:click="abierto = true"
                                    class="mt-4 inline-flex h-12 w-full items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                                Registrar entrega
                            </button>

                            <div x-show="abierto" x-cloak class="mt-4 space-y-4 border-t border-neutral-100 pt-4"
                                 x-on:firma-cambio="firmaLista = $event.detail.firmado">

                                <div>
                                    <p class="mb-1.5 text-sm font-medium text-neutral-700">Foto de la entrega *</p>
                                    {{-- capture=environment: abre la cámara trasera directo. --}}
                                    {{-- data-foto y no x-ref: el componente trae su propio x-data,
                                         así que un x-ref acá se registra en ese root anidado y el
                                         $refs de entregaForm (root PADRE) no lo ve — $refs junta
                                         refs de ancestros, no de hijos. Mismo idioma que
                                         [data-firma-pad]. --}}
                                    <x-archivo-input texto="Tomar foto" vacio="Todavía no hay foto"
                                        data-foto accept="image/*" capture="environment"
                                        x-on:change="fotoLista = $event.target.files.length > 0" />
                                </div>

                                <x-firma-pad />

                                {{-- Receptor en la puerta (R13): quién recibió. --}}
                                <div class="space-y-3">
                                    <p class="text-sm font-medium text-neutral-700">Quién recibe *</p>
                                    <input type="text" x-model="receptorNombre" maxlength="191"
                                           placeholder="Nombre de quien recibe" aria-label="Nombre de quien recibe"
                                           class="block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    {{-- inputmode text + autocapitalize: el DV puede ser K y el
                                         teclado numérico de iOS no la tiene (gotcha 28-07). --}}
                                    <input type="text" x-model="receptorRut" maxlength="12"
                                           inputmode="text" autocapitalize="characters"
                                           placeholder="RUT (ej. 12345678-K)" aria-label="RUT de quien recibe"
                                           class="block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    <div class="grid grid-cols-3 gap-2">
                                        @foreach (['empresa' => 'Empresa', 'conserje' => 'Conserje', 'otro' => 'Otro'] as $valor => $rotulo)
                                            <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border px-2 text-sm font-medium transition duration-150"
                                                   :class="receptorRelacion === '{{ $valor }}' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-neutral-300 bg-white text-neutral-700'">
                                                <input type="radio" x-model="receptorRelacion" value="{{ $valor }}" class="sr-only">
                                                {{ $rotulo }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Cobro en la puerta (R4+R7): SOLO si la hoja lo pidió. --}}
                                @if ($cobra)
                                    <div class="space-y-3 rounded-lg bg-brand-50 p-3 ring-1 ring-inset ring-brand-100" data-cobro>
                                        <p class="text-sm font-medium text-brand-700">Cobro en la entrega *</p>
                                        <div class="grid grid-cols-3 gap-2">
                                            @foreach (['efectivo' => 'Efectivo', 'cheque' => 'Cheque', 'transbank' => 'Transbank'] as $valor => $rotulo)
                                                <label class="flex min-h-12 cursor-pointer items-center justify-center rounded-lg border px-2 text-sm font-medium transition duration-150"
                                                       :class="cobroMetodo === '{{ $valor }}' ? 'border-brand-600 bg-white text-brand-700' : 'border-neutral-300 bg-white text-neutral-700'">
                                                    <input type="radio" x-model="cobroMetodo" value="{{ $valor }}" class="sr-only">
                                                    {{ $rotulo }}
                                                </label>
                                            @endforeach
                                        </div>
                                        <label class="block text-xs font-medium text-neutral-600" for="monto-{{ $despacho->id }}">Monto recibido (viene precargado con el total del documento)</label>
                                        <input type="number" id="monto-{{ $despacho->id }}" x-model="cobroMonto" min="1" inputmode="numeric"
                                               class="block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    </div>
                                @endif

                                <label class="flex items-start gap-3">
                                    <input type="checkbox" x-model="parcial"
                                           class="mt-0.5 h-5 w-5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500/30">
                                    <span class="text-sm text-neutral-700">Entrega PARCIAL (quedó saldo pendiente)</span>
                                </label>

                                <div x-show="parcial">
                                    <label class="block text-sm font-medium text-neutral-700" for="obs-{{ $despacho->id }}">Qué quedó pendiente *</label>
                                    <input type="text" id="obs-{{ $despacho->id }}" x-model="observacion" maxlength="188"
                                           placeholder="Ej. faltaron 4 botellones de 20L"
                                           class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                </div>

                                <p x-show="error" x-cloak x-text="error" class="dg-shake text-sm text-red-600" data-error-message></p>

                                <button type="button" x-on:click="confirmar()" :disabled="! puedeEnviar()"
                                        class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98] disabled:opacity-50"
                                        x-text="enviando ? 'Registrando…' : (parcial ? 'Confirmar entrega parcial' : 'Confirmar entrega')">
                                    Confirmar entrega
                                </button>

                                {{-- Rechazo en puerta (R15): solo paradas de hoja. --}}
                                @if ($parada)
                                    <div class="border-t border-neutral-100 pt-3" data-rechazo>
                                        <button type="button" x-show="! rechazando" x-on:click="rechazando = true"
                                                class="inline-flex min-h-12 w-full items-center justify-center rounded-lg border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-700 shadow-sm transition duration-150 hover:bg-neutral-50 active:scale-[0.98]">
                                            No se pudo entregar
                                        </button>
                                        <div x-show="rechazando" x-cloak class="space-y-3">
                                            <label class="block text-sm font-medium text-neutral-700" for="motivo-{{ $despacho->id }}">Por qué no se pudo *</label>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach (['Local cerrado', 'Nadie recibe', 'Cliente rechaza la carga', 'Sin pago'] as $sugerencia)
                                                    <button type="button" x-on:click="motivoRechazo = '{{ $sugerencia }}'"
                                                            class="rounded-full border px-3 py-1.5 text-xs font-medium transition duration-150"
                                                            :class="motivoRechazo === '{{ $sugerencia }}' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-neutral-300 bg-white text-neutral-600'">
                                                        {{ $sugerencia }}
                                                    </button>
                                                @endforeach
                                            </div>
                                            <input type="text" id="motivo-{{ $despacho->id }}" x-model="motivoRechazo" maxlength="188"
                                                   placeholder="O escribe el motivo…"
                                                   class="block w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                            <button type="button" x-on:click="rechazar()" :disabled="motivoRechazo.trim() === '' || enviando"
                                                    class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-red-500 active:scale-[0.98] disabled:opacity-50"
                                                    x-text="enviando ? 'Registrando…' : 'Registrar rechazo'">
                                                Registrar rechazo
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- RECHAZADAS por el servidor (422/403 al drenar): acción manual.
             Sin esta sección un rechazo permanente viviría mudo en IndexedDB. --}}
        <div x-data="rechazadasEntregas()" x-show="items.length > 0" x-cloak class="space-y-3" data-rechazadas>
            <h3 class="text-xs font-medium uppercase tracking-wide text-red-600">No se pudieron enviar</h3>
            <template x-for="item in items" :key="item.uuid">
                <div class="rounded-2xl bg-red-50 p-4 ring-1 ring-inset ring-red-200 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-red-700" x-text="item.titulo"></p>
                            <p class="mt-0.5 text-xs text-red-700" x-text="item.motivo"></p>
                        </div>
                        <button type="button" x-on:click="descartar(item.uuid)"
                                class="inline-flex h-12 shrink-0 items-center rounded-lg px-3 text-sm font-semibold text-red-700 transition duration-150 hover:bg-red-100 active:scale-[0.98]">
                            Descartar
                        </button>
                    </div>
                </div>
            </template>
        </div>

    </div>
</x-app-layout>
