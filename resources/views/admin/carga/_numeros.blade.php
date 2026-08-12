{{--
    LOS NÚMEROS DE LA CARGA: ocupación, volumen, peso, cómo cae entre los ejes, y los
    avisos que van con eso.

    VIVE DENTRO DEL RECUADRO DEL VISOR (pedido del dueño 12-08, dibujado sobre la
    pantalla: «acá lo quiero abajo… dentro de donde está el camión, para ahorrar espacio»).
    Era una tarjeta suelta debajo del dibujo, con su borde, su sombra y el hueco entre las
    dos; adentro se ahorran los tres y los números quedan pegados al camión que describen.
    Es la misma decisión que la franja de medidas de arriba (10-08).

    Se extrajo a un partial en vez de mover el markup: así el diff es una línea en cada
    lado, y quien mañana toque los números —el reparto por eje es de otra sesión— sigue
    teniendo un solo lugar donde tocarlos.

    Requiere: $mixta, $camion, $p.
--}}
                                    <div class="text-sm">
                                        <div class="flex justify-between py-1">
                                            <span class="text-neutral-500">Ocupación</span>
                                            <span class="font-medium tabular-nums text-neutral-900">{{ round($mixta['resultado']['ocupacion'] * 100) }}%</span>
                                        </div>
                                        <div class="mb-2 h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                            <div class="h-1.5 rounded-full bg-brand-600" style="width: {{ min(100, round($mixta['resultado']['ocupacion'] * 100)) }}%"></div>
                                        </div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-neutral-500">Volumen</span>
                                            <span class="font-medium tabular-nums text-neutral-900">{{ number_format($mixta['resultado']['volumen_ocupado_m3'], 1, ',', '.') }} de {{ number_format($mixta['resultado']['volumen_vehiculo_m3'], 1, ',', '.') }} m³</span>
                                        </div>
                                        @if ($mixta['resultado']['peso_kg'] > 0)
                                            <div class="flex justify-between py-1">
                                                <span class="text-neutral-500">Peso</span>
                                                <span class="font-medium tabular-nums text-neutral-900">
                                                    {{ number_format($mixta['resultado']['peso_kg'], 0, ',', '.') }} kg{{ $camion->peso_max_kg ? ' de '.number_format($camion->peso_max_kg, 0, ',', '.') : '' }}
                                                </span>
                                            </div>
                                            {{-- El peso también con BARRA, como la ocupación. Antes era el
                                                 único número sin una: dos cifras juntas no dicen si vas al
                                                 30% o al 95%, y con carga pesada ese es el dato que manda.
                                                 En rojo cuando pasa el 90%, que es donde deja de haber
                                                 margen para un error de tara. --}}
                                            @if ($p['tope_kg'])
                                                @php $usoPeso = min(100, round($p['cargado_kg'] / $p['tope_kg'] * 100)); @endphp
                                                <div class="h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                                    <div class="h-1.5 rounded-full {{ $usoPeso >= 90 ? 'bg-red-500' : 'bg-brand-600' }}"
                                                         style="width: {{ $usoPeso }}%"></div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- ═══ CÓMO CAE EL PESO ENTRE LOS EJES ═══
                                         Lote 5, con los datos de ejes del 12-08. Solo aparece en
                                         los camiones que tienen las DOS medidas; en el resto no se
                                         muestra nada y las notas del catálogo dicen qué falta.

                                         Va junto al peso porque responde la otra mitad de la misma
                                         pregunta: los kilos totales dicen si te pasás de la carga
                                         máxima, y esto dice si están puestos donde corresponde. Un
                                         camión puede ir por debajo del tope y aun así llevar el eje
                                         trasero pasado. --}}
                                    @if ($mixta['ejes'] !== null)
                                        @php $ej = $mixta['ejes']; @endphp
                                        <div class="mt-4 rounded-lg border border-neutral-200 p-3">
                                            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cómo cae el peso</p>
                                            <div class="mt-2 flex flex-wrap items-baseline gap-x-5 gap-y-1 text-sm" @if ($ej['total_kg'] <= 0) hidden @endif>
                                                @foreach ([
                                                    ['Eje delantero', $ej['delantero_kg'], $ej['delantero_pct'], $ej['tope_delantero_kg'], $ej['se_pasa_delantero']],
                                                    ['Eje trasero', $ej['trasero_kg'], $ej['trasero_pct'], $ej['tope_trasero_kg'], $ej['se_pasa_trasero']],
                                                ] as [$rotulo, $kg, $pct, $tope, $sePasa])
                                                    <span class="{{ $sePasa ? 'text-red-700' : 'text-neutral-500' }}">{{ $rotulo }}
                                                        <span class="font-semibold tabular-nums {{ $sePasa ? 'text-red-700' : 'text-neutral-900' }}">{{ number_format($kg, 0, ',', '.') }} kg</span>
                                                        <span class="tabular-nums {{ $sePasa ? 'text-red-600' : 'text-neutral-400' }}">({{ $pct }}%)</span>
                                                        {{-- El tope al lado del número: «1.900 kg» no dice nada solo;
                                                             «1.900 de 1.700» se lee de un vistazo. --}}
                                                        @if ($tope)
                                                            <span class="tabular-nums {{ $sePasa ? 'font-medium text-red-600' : 'text-neutral-400' }}">de {{ number_format($tope, 0, ',', '.') }}</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                            {{-- Una barra que se lee de un vistazo: el reparto entre los
                                                 dos apoyos. Los porcentajes negativos se acotan solo en la
                                                 barra —no en el número— porque un ancho negativo no existe;
                                                 el caso lo grita el aviso de abajo. --}}
                                            @if ($ej['total_kg'] > 0)
                                                <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-neutral-200">
                                                    <div class="h-1.5 bg-brand-600" style="width: {{ max(0, min(100, $ej['delantero_pct'])) }}%"></div>
                                                    <div class="h-1.5 bg-neutral-500" style="width: {{ max(0, min(100, $ej['trasero_pct'])) }}%"></div>
                                                </div>
                                            @endif

                                            {{-- Lo que quedó fuera del reparto, con nombre y apellido. La
                                                 mitad del catálogo todavía no tiene el peso cargado —está
                                                 en null a propósito, no se inventa— y antes eso hacía
                                                 desaparecer la sección entera sin decir por qué. --}}
                                            @if ($ej['sin_peso'] !== [])
                                                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800">
                                                    @if ($ej['total_kg'] <= 0)
                                                        <strong>No se puede repartir esta carga.</strong>
                                                    @else
                                                        <strong>Falta peso.</strong> El reparto de arriba deja afuera
                                                    @endif
                                                    {{ implode(', ', $ej['sin_peso']) }}: no {{ count($ej['sin_peso']) === 1 ? 'tiene' : 'tienen' }}
                                                    el peso cargado en el catálogo. Con ese dato el número sale solo.
                                                </p>
                                            @endif

                                            {{-- ═══ SE PASA DE UN EJE ═══
                                                 Pedido del dueño (12-08): «si me pasé, para evitar una
                                                 multa, que salga un mensaje en rojo». Va con el mismo
                                                 peso visual que el «No cabe todo», porque es la misma
                                                 clase de noticia: algo que hay que cambiar antes de
                                                 salir. En la balanza no se pesa el camión entero — se
                                                 pesa eje por eje. --}}
                                            @php
                                                $pasados = collect([
                                                    ['delantero', $ej['se_pasa_delantero'], $ej['delantero_kg'], $ej['tope_delantero_kg']],
                                                    ['trasero', $ej['se_pasa_trasero'], $ej['trasero_kg'], $ej['tope_trasero_kg']],
                                                ])->filter(fn ($e) => $e[1] === true);
                                            @endphp
                                            @if ($pasados->isNotEmpty())
                                                <div class="mt-2 rounded-lg border-2 border-red-300 bg-red-50 px-3 py-2">
                                                    <p class="text-sm font-semibold text-red-700">
                                                        Se pasa del eje {{ $pasados->pluck(0)->join(' y del eje ') }}
                                                    </p>
                                                    <p class="mt-0.5 text-xs leading-relaxed text-red-700">
                                                        @foreach ($pasados as [$cual, , $kg, $tope])
                                                            El {{ $cual }} lleva
                                                            <strong class="tabular-nums">{{ number_format($kg, 0, ',', '.') }} kg</strong>
                                                            y aguanta {{ number_format($tope, 0, ',', '.') }}:
                                                            <strong class="tabular-nums">{{ number_format($kg - $tope, 0, ',', '.') }} kg de más</strong>.
                                                        @endforeach
                                                        En la balanza se pesa eje por eje, así que esto es multa aunque
                                                        el total esté dentro de la carga máxima. Corré carga hacia el otro eje.
                                                    </p>
                                                </div>
                                            @endif

                                            @if ($ej['aliviana_el_delantero'])
                                                <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs leading-relaxed text-red-700">
                                                    <strong>La carga está toda detrás del eje trasero.</strong>
                                                    En vez de apoyar sobre el delantero, lo LEVANTA: se pierde dirección y freno.
                                                    Hay que correr carga hacia la cabina.
                                                </p>
                                            @endif

                                            <p class="mt-2 text-xs leading-relaxed text-neutral-400">
                                                Reparte solo la CARGA, no el peso del camión vacío.
                                                @if ($ej['tope_delantero_kg'] === null || $ej['tope_trasero_kg'] === null)
                                                    Para avisar que un eje se pasa falta cargar cuánto aguanta cada uno
                                                    (está en el padrón del vehículo).
                                                @endif
                                            </p>
                                        </div>
                                    @endif

                                    @if ($mixta['peligrosas'] !== [])
                                        <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">
                                            <strong>Mercancía peligrosa en la carga
                                                ({{ collect($mixta['peligrosas'])->map(fn ($p) => $p->peligrosa_codigo ?: $p->nombre)->implode(', ') }}).</strong>
                                            El cálculo es solo de espacio: el transporte tiene reglas propias de rotulado y
                                            segregación. Que quepa no significa que se pueda cargar así.
                                        </p>
                                    @endif

                                    <p class="mt-4 text-xs leading-relaxed text-neutral-400">
                                        Acomodo por zonas, como se estiba de verdad: lo grande al fondo, sin apilar un
                                        producto arriba de otro. Capacidad práctica, no promesa.
                                    </p>
