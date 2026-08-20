{{-- LO QUE YA SE LE ENVIO AL CLIENTE, y su historial: cuando salio la carta, que
     respondio y por que, los re-envios y las respuestas anteriores.

     EN UN PARTIAL para poder colgarlo ABAJO DEL PARTE DEL TECNICO (dueno 20-08:
     «abajo de la parte del tecnico este la informacion de cuando se envio al cliente
     con el detalle como historial… toda la informacion en un solo apartado»).
     Requiere: $orden, $cotizaciones, $ultima, $clp.
--}}
            {{-- ===== Lo ya enviado al cliente (P-M12-02) =====
                 El botón de enviar vive arriba, junto a «Guardar»: esta tarjeta es
                 solo la constancia de lo que salió, así que si nunca se envió nada
                 NO se dibuja (dueño 07-08: la pantalla no debe ser tan extensa). --}}
            @if ($ultima)
            <div class="mt-5 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold text-neutral-900">Enviada al cliente</h3>
                    <x-badge :variant="$ultima->estado_variante">{{ $ultima->estado_label }}</x-badge>
                    <span class="text-sm text-neutral-600">
                        {{ $ultima->created_at->format('d-m-Y H:i') }} · ${{ number_format((int) $ultima->costo_total, 0, ',', '.') }}
                        · {{ $ultima->cliente_email }}@if ($ultima->respondida_at) · respondida el {{ $ultima->respondida_at->format('d-m-Y H:i') }}@endif
                    </span>
                </div>

                {{-- El «¿por qué?» que escribió el cliente al responder (dueño 06-08). --}}
                @if (filled($ultima->respuesta_motivo))
                    <p class="mt-1.5 text-sm italic text-neutral-500">Motivo del cliente: «{{ $ultima->respuesta_motivo }}»</p>
                @endif
                @if (! $ultima->correo_enviado_at && $ultima->esRespondible())
                    <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.reintentar', [$orden, $ultima->id]) }}" class="mt-3" data-una-vez>
                        @csrf
                        <x-secondary-button type="submit">Reintentar correo</x-secondary-button>
                        <span class="ml-2 text-xs text-red-600">El correo no salió al enviarla.</span>
                    </form>
                @endif

                {{-- Historial (re-envíos y respuestas anteriores) --}}
                @if ($cotizaciones->count() > 1)
                    <div class="mt-3 border-t border-neutral-100 pt-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-neutral-400">Historial</p>
                        <ul class="mt-1.5 space-y-1">
                            @foreach ($cotizaciones->slice(1) as $c)
                                <li class="text-xs text-neutral-500">
                                    {{ $c->created_at->format('d-m-Y H:i') }} · ${{ number_format((int) $c->costo_total, 0, ',', '.') }} · {{ $c->estado_label }}@if ($c->respondida_at) ({{ $c->respondida_at->format('d-m-Y H:i') }})@endif @if (filled($c->respuesta_motivo))· «{{ $c->respuesta_motivo }}»@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
            @endif

            @include('admin.servicio-tecnico._listo-retiro')
