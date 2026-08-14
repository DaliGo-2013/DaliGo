{{--
    CUÁNDO LA AGENDA ESTÁ CERRADA: feriados, vacaciones y medias jornadas.

    Pedido del dueño (13-08-2026): «todo esto alimentado por el jefe de ventas, que va a
    ser el que lleve adelante la agenda del técnico industrial».

    LO QUE ESTA PANTALLA CAMBIA Y LO QUE NO, dicho arriba de todo y no en una ayuda
    escondida: cierra el día para el CLIENTE (el formulario público deja de aceptarlo y
    se lo avisa al elegir la fecha), y NO impide agendar por dentro. Si alguien cree que
    esto bloquea la agenda interna, va a evitar cargar vacaciones «para no trabarse» — y
    el cliente va a seguir pidiendo días en que no hay nadie.
--}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Cuándo no se atiende"
                       subtitle="Feriados, vacaciones y días a media jornada del técnico industrial." />
    </x-slot>

    <div class="space-y-5 py-8 sm:py-12">
        <x-status-alert :status="session('status')" />

        <div class="rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-neutral-700">
            <p class="font-semibold text-brand-700">Esto lo ve el cliente, no le pone freno a ustedes.</p>
            <p class="mt-1">
                Los días que cierres acá dejan de poder pedirse desde el formulario público: al elegir
                la fecha, el cliente ve que ese día no se atiende y se le ofrece el más cercano
                disponible. <span class="font-medium">Nunca se le dice el motivo</span> — ni que son
                vacaciones ni nada. Adentro, la agenda sigue igual: si hay una urgencia, se agenda.
            </p>
        </div>

        {{-- ── Cargar un cierre ─────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Cerrar días</h2>

            <form method="POST" action="{{ route('admin.agenda-terreno.cierres.store') }}"
                  class="mt-3 space-y-4"
                  x-data="{ tipo: @js(old('tipo', 'cerrado')) }">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="fecha_desde">Desde <span class="text-red-500">*</span></x-input-label>
                        <x-text-input id="fecha_desde" name="fecha_desde" type="date" class="mt-1.5 w-full" required
                            min="{{ \App\Support\FechaNegocio::hoy() }}" :value="old('fecha_desde')" />
                        <x-input-error :messages="$errors->get('fecha_desde')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="fecha_hasta" value="Hasta (opcional)" />
                        <x-text-input id="fecha_hasta" name="fecha_hasta" type="date" class="mt-1.5 w-full"
                            min="{{ \App\Support\FechaNegocio::hoy() }}" :value="old('fecha_hasta')" />
                        <x-input-hint>Vacío = un solo día. Para vacaciones, poné la última fecha.</x-input-hint>
                        <x-input-error :messages="$errors->get('fecha_hasta')" class="mt-2" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="tipo">Qué pasa esos días <span class="text-red-500">*</span></x-input-label>
                        <x-select id="tipo" name="tipo" class="mt-1.5" x-model="tipo" required>
                            @foreach (\App\Models\AgendaCierre::TIPOS as $valor => $etiqueta)
                                <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
                    </div>
                    {{-- La hora solo aparece cuando hace falta: un campo que no aplica es una
                         pregunta que alguien va a contestar igual. --}}
                    <div x-show="tipo === 'media_jornada'" x-cloak>
                        <x-input-label for="hora_hasta">Atiende hasta <span class="text-red-500">*</span></x-input-label>
                        <x-text-input id="hora_hasta" name="hora_hasta" type="time" class="mt-1.5 w-full"
                            :value="old('hora_hasta', '14:00')" />
                        <x-input-hint>El cliente verá «hay disponibilidad hasta las 14:00».</x-input-hint>
                        <x-input-error :messages="$errors->get('hora_hasta')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="motivo">Motivo (interno) <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="motivo" name="motivo" type="text" class="mt-1.5 w-full" required
                        maxlength="191" placeholder="Ej. Vacaciones de Carlos · Capacitación · Mantención del taller"
                        :value="old('motivo')" />
                    <x-input-hint>Solo lo ven ustedes. Al cliente nunca se le dice por qué.</x-input-hint>
                    <x-input-error :messages="$errors->get('motivo')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <x-primary-button>Cerrar esos días</x-primary-button>
                </div>
            </form>
        </div>

        {{-- ── Lo que ya está cerrado ───────────────────────────────────────── --}}
        <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="flex items-baseline justify-between border-b border-neutral-100 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-500">De acá en adelante</h2>
                <span class="text-xs text-neutral-400">{{ $cierres->count() }} tramo(s)</span>
            </div>

            @if ($cierres->isEmpty())
                <x-lista-vacia vacio="No hay días cerrados de acá en adelante. El técnico atiende de lunes a viernes y los feriados se cargan solos." />
            @else
                <ul class="divide-y divide-neutral-100">
                    @foreach ($cierres as $c)
                        <li class="flex flex-wrap items-start gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-neutral-900">
                                    {{ $c->rango_label }}
                                    @if ($c->tipo === \App\Models\AgendaCierre::TIPO_MEDIA_JORNADA)
                                        <span class="font-normal text-neutral-500">· atiende hasta las {{ $c->hora_corta }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 truncate text-xs text-neutral-500">
                                    {{ $c->motivo }}
                                    @if ($c->autor)
                                        · {{ $c->autor->name }}
                                    @endif
                                </p>
                            </div>

                            @if ($c->origen === \App\Models\AgendaCierre::ORIGEN_FERIADO)
                                {{-- Sin botón de borrar: el seeder lo repone en cada despliegue y
                                     un botón que no cumple es peor que ninguno. --}}
                                <x-badge variant="neutral">Feriado legal</x-badge>
                            @else
                                <form method="POST" action="{{ route('admin.agenda-terreno.cierres.destroy', $c) }}"
                                      onsubmit="return confirm('¿Quitar este cierre? Esos días vuelven a estar disponibles para el cliente.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="rounded-lg p-1.5 text-neutral-400 transition hover:bg-red-50 hover:text-red-600"
                                            title="Quitar el cierre" aria-label="Quitar el cierre">
                                        <x-icon.trash class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
