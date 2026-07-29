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
                    <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6"
                         x-data="entregaForm({ url: '{{ route('entregas.confirmar', $despacho) }}', etiqueta: {{ \Illuminate\Support\Js::from($despacho->codigo.' · '.($despacho->documento?->cliente?->razon_social ?? 'Sin cliente')) }} })"
                         :class="encolada && 'opacity-60'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold tracking-tight text-neutral-900"
                                   :class="encolada && 'line-through'">{{ $despacho->codigo }}</p>
                                <p class="mt-0.5 truncate text-sm text-neutral-700">
                                    {{ $despacho->documento?->cliente?->razon_social ?? 'Sin cliente' }}
                                </p>
                                <p class="mt-0.5 text-xs text-neutral-500">
                                    Folio {{ $despacho->documento?->folio ?? '—' }}
                                    · retirado {{ $despacho->retirado_at?->enChile()->format('H:i') ?? 's/h' }}
                                </p>
                            </div>
                            <x-despacho.estado-badge :estado="$despacho->estado" class="shrink-0" />
                        </div>

                        {{-- Confirmación encolada sin señal: queda tachada hasta drenar. --}}
                        <p x-show="encolada" x-cloak class="mt-3 rounded-lg bg-neutral-100 px-3 py-2 text-sm text-neutral-700">
                            Guardada en este teléfono — se envía sola al volver la señal.
                        </p>

                        <div x-show="! encolada">
                            <button type="button" x-show="! abierto" x-on:click="abierto = true"
                                    class="mt-4 inline-flex h-12 w-full items-center justify-center rounded-lg bg-brand-600 px-4 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                                Registrar entrega
                            </button>

                            <div x-show="abierto" x-cloak class="mt-4 space-y-4 border-t border-neutral-100 pt-4"
                                 x-on:firma-cambio="firmaLista = $event.detail.firmado">

                                <div>
                                    <p class="text-sm font-medium text-neutral-700">Foto de la entrega *</p>
                                    {{-- capture=environment: abre la cámara trasera directo. --}}
                                    <input type="file" x-ref="foto" accept="image/*" capture="environment"
                                           x-on:change="fotoLista = $refs.foto.files.length > 0"
                                           class="mt-1.5 block w-full text-sm text-neutral-600 file:mr-3 file:h-12 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-4 file:text-sm file:font-semibold file:text-neutral-700">
                                </div>

                                <x-firma-pad />

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
