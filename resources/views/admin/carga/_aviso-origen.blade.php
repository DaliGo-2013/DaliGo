{{--
    DE DÓNDE VINO LA CARGA Y QUÉ NO ENTRÓ AL CÁLCULO (traer factura / pegar planilla,
    10-09-2026).

    Se dibuja JUNTO A LA LISTA DE LA CARGA y no dentro del modal de importar, porque
    calcular es un GET que recarga la página: el modal muere y con él moría el aviso. El
    «No se pudieron leer N líneas» del importador de Excel se perdía SIEMPRE que la
    importación fuera parcial — o sea, justo cuando importaba. Los textos viajan en la URL
    (`origen`, `no_cargadas[]`), así que sobreviven la recarga, los recálculos siguientes
    (el x-data se siembra con ellos) y el link compartido: quien recibe el plan ve que es
    de una carga a la que le faltan líneas, no de una completa.

    Rojo cuando falta algo, porque es lo negativo del veredicto: «cabe todo» al lado de
    tres líneas que no se contaron NO es «cabe todo». Neutro cuando entró todo — ahí solo
    informa de dónde salió la carga.

    Todo llega de la URL y se imprime ESCAPADO ({{ }}): es texto que escribió un usuario.
--}}
@if (($avisoCarga ?? null) !== null)
    <div data-aviso-carga
         class="mt-3 rounded-lg px-3 py-2 text-xs leading-relaxed {{ $avisoCarga['lineas'] ? 'bg-red-50 text-red-700' : 'bg-neutral-50 text-neutral-500' }}">
        @if ($avisoCarga['origen'])
            <p>
                <span class="font-semibold">Traído de {{ $avisoCarga['origen'] }}.</span>
                @if (! $avisoCarga['lineas'])
                    Todas sus líneas entraron al cálculo.
                @endif
            </p>
        @endif
        @if ($avisoCarga['lineas'])
            <p class="font-semibold">
                {{ count($avisoCarga['lineas']) }}
                {{ \Illuminate\Support\Str::plural('línea', count($avisoCarga['lineas'])) }}
                {{ count($avisoCarga['lineas']) === 1 ? 'no entró' : 'no entraron' }} al cálculo — el veredicto es sobre la carga SIN eso:
            </p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($avisoCarga['lineas'] as $l)
                    <li>{{ $l }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
