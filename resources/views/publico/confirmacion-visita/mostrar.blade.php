{{--
    Página pública para que el cliente confirme su visita agendada (link firmado
    del correo). Botones "Confirmo" / "No puedo ese día" + un comentario libre
    corto (~150 palabras). Si ya respondió / se canceló / la fecha pasó, muestra
    el estado en vez de los botones.
--}}
@php
    $t = $trabajo;
    $cuando = $t->fecha?->translatedFormat('l d \d\e F');
    $hora = $t->hora_corta ? ($t->hora_fin_corta && $t->hora_fin_corta !== $t->hora_corta
        ? $t->hora_corta.' a '.$t->hora_fin_corta.' hrs' : $t->hora_corta.' hrs') : null;
@endphp
<x-guest-layout>
    <div>
        <div class="text-center">
            <h1 class="text-xl font-bold tracking-tight text-neutral-900">Tu visita agendada</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ $t->tipo_label }}@if ($t->servicio) · {{ $t->servicio->nombre }} @endif</p>
        </div>

        <div class="mt-4 rounded-xl border border-brand-200 bg-brand-50 px-5 py-4 text-center">
            <div class="text-sm font-semibold capitalize text-brand-700">{{ $cuando }}</div>
            @if ($hora)<div class="text-sm text-brand-600">{{ $hora }}</div>@endif
            @if ($t->tecnico)<div class="mt-1 text-sm text-neutral-600">Te visitará: {{ $t->tecnico->name }}</div>@endif
            @if ($t->direccion || $t->ciudad)<div class="text-sm text-neutral-500">{{ collect([$t->direccion, $t->ciudad])->filter()->implode(', ') }}</div>@endif
        </div>

        @if ($t->esConfirmable())
            <form method="POST" action="{{ $urlResponder }}" class="mt-5" data-una-vez x-data="{ resp: '' }">
                @csrf
                <input type="text" name="sitio_web" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <p class="mb-2 text-center text-sm text-neutral-600">¿Podrás recibir al técnico ese día?</p>

                {{-- Comentario libre corto (~150 palabras). --}}
                <label for="nota" class="text-sm font-medium text-neutral-700">Comentario (opcional)</label>
                <textarea id="nota" name="nota" rows="3" maxlength="1000"
                          placeholder="Ej. Sí puedo, pero llego a las 15:00 / el portón da a la calle lateral…"
                          class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"></textarea>
                <p class="mt-1 text-xs text-neutral-400">Máx. ~150 palabras.</p>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button type="submit" name="respuesta" value="confirmada"
                            class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-600 px-4 text-base font-semibold text-white transition hover:bg-brand-700">
                        Confirmo, puedo ese día
                    </button>
                    <button type="submit" name="respuesta" value="no_puede"
                            class="inline-flex h-12 items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-base font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        No puedo ese día
                    </button>
                </div>
                <p class="mt-3 text-center text-xs text-neutral-400">Tu respuesta le llega al instante a nuestro equipo.</p>
            </form>
        @else
            <div class="mt-5 rounded-xl px-5 py-4 text-center text-sm
                        {{ $t->cliente_confirmacion === 'confirmada' ? 'bg-brand-50 text-brand-700' : ($t->cliente_confirmacion === 'no_puede' ? 'bg-neutral-100 text-neutral-600' : 'bg-neutral-100 text-neutral-600') }}">
                @if ($t->cliente_confirmacion === 'confirmada')
                    Ya confirmaste esta visita el {{ $t->cliente_confirmacion_at?->format('d-m-Y H:i') }}. ¡Te esperamos!
                @elseif ($t->cliente_confirmacion === 'no_puede')
                    Registramos que no puedes ese día ({{ $t->cliente_confirmacion_at?->format('d-m-Y H:i') }}). Te contactaremos para reagendar.
                @elseif ($t->estado === 'cancelado')
                    Esta visita fue cancelada. Te contactaremos para coordinar una nueva fecha.
                @else
                    Esta visita ya no está disponible para confirmar. Si tienes dudas, contáctanos.
                @endif
            </div>
        @endif
    </div>
</x-guest-layout>
