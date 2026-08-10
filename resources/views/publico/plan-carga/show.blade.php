{{--
    Plan de carga en 3D compartido por link, SIN login (pedido del dueño 10-08).

    Ancho `listado` y no el de siempre: un visor 3D dentro de los 448 px del card
    de invitado no se puede mirar.

    La página es de SOLO MIRAR. No trae los controles que navegan hacia adentro de
    la app —eso lo apaga `$publico` en el partial del visor— ni enlaces al resto
    del sistema: quien abre esto es un cliente o un conductor, no un usuario.
--}}
<x-guest-layout ancho="listado">
    <div class="mb-4">
        <h1 class="text-xl font-semibold text-neutral-900">Plan de carga</h1>
        <p class="mt-1 text-sm text-neutral-500">
            @if ($camion)
                {{ $camion->nombre }} ·
                {{ number_format($camion->largo_cm / 100, 2, ',', '.') }} ×
                {{ number_format($camion->ancho_cm / 100, 2, ',', '.') }} ×
                {{ number_format($camion->alto_cm / 100, 2, ',', '.') }} m
            @else
                Sin camión seleccionado.
            @endif
        </p>
    </div>

    @if ($escena)
        @include('admin.carga._visor', ['publico' => true])
    @else
        <p class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-600">
            Este plan no tiene carga que mostrar.
        </p>
    @endif

    {{-- El resumen en palabras, para quien abre el link en el celular y no va a
         girar el dibujo. Es lo mismo que dice la pantalla interna. --}}
    @if ($mixta)
        <div class="mt-5 overflow-hidden rounded-xl border border-neutral-200">
            <table class="w-full text-sm">
                <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Producto</th>
                        <th class="px-3 py-2 text-right font-medium">Pedidas</th>
                        <th class="px-3 py-2 text-right font-medium">Van</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($mixta['lineas'] as $l)
                        <tr>
                            <td class="px-3 py-2 text-neutral-800">{{ $l['modelo']->nombre }}</td>
                            <td class="px-3 py-2 text-right tabular-nums text-neutral-500">{{ number_format($l['pedidas_unidades'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ number_format($l['cargadas_unidades'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm font-semibold {{ $mixta['cabeTodo'] ? 'text-neutral-900' : 'text-red-600' }}">
            {{ $mixta['cabeTodo'] ? 'Cabe todo en un viaje.' : 'No cabe todo en un viaje.' }}
        </p>
    @elseif ($resultado && $bulto)
        <p class="mt-5 text-sm text-neutral-700">
            Entran <span class="text-lg font-semibold text-neutral-900">{{ number_format($resultado['unidades'], 0, ',', '.') }}</span>
            unidades de {{ $bulto->nombre }}.
        </p>
    @endif

    {{-- El mismo aviso que lleva la planilla: afuera de la app, un número sin
         contexto se lee como una promesa. --}}
    <p class="mt-5 border-t border-neutral-100 pt-4 text-xs leading-relaxed text-neutral-500">
        Cálculo geométrico de referencia (sin pasillo y sin factor de corrección): es un
        máximo, no un compromiso de despacho. Este enlace vence el
        {{ $vence->format('d-m-Y') }}.
    </p>
</x-guest-layout>
