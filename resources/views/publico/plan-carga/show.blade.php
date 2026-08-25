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
        {{-- El camión y sus medidas NO se repiten acá: desde el 10-08 van en la franja
             de datos que vive dentro del recuadro del visor, y escritas dos veces en la
             misma pantalla eran justo el exceso de texto que el dueño pidió recortar. --}}
        <p class="mt-1 text-sm text-neutral-500">
            @if ($camion)
                Cómo va cargado el camión, bulto por bulto.
            @else
                Sin camión seleccionado.
            @endif
        </p>
    </div>

    {{-- ═══ LA VISTA DE JEFATURA ═══
         El mismo link, dos vistas (pedido del dueño 11-08). El cliente ve la página de
         solo mirar de siempre; quien TIENE el permiso ve además que está mirando la
         versión del cliente y el atajo para abrirla adentro y tocarla.

         La diferencia la hace quién abre, NO la URL: un segundo link «editable» sería
         una puerta al simulador sin login para cualquiera que tenga la dirección. Acá el
         botón lleva a la ruta interna, donde el permiso se vuelve a chequear. --}}
    @if ($puedeEditar)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3">
            <p class="text-sm text-brand-800">
                <strong>Así lo ve el cliente.</strong> Esta página es de solo mirar.
            </p>
            <a href="{{ $urlEditar }}"
               class="inline-flex min-h-12 items-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 active:scale-[0.98]">
                Abrir en el simulador para editar
            </a>
        </div>
    @endif

    @if ($escena)
        @include('admin.carga._visor', ['publico' => true])
        {{-- LOS DATOS DE LA ESCENA. Sin esto el lienzo queda EN BLANCO: `montarCarga3d`
             (app.js) sale sin hacer nada si no encuentra este <script>, así que el link
             compartido mostraba el recuadro vacío desde que se publicó (10-08) — la
             pantalla interna sí lo emitía y acá se olvidó. Encontrado al abrir el link
             para revisar el rompeviento del HINO (11-08). --}}
        <script type="application/json" id="carga3d-datos">@json($escena)</script>
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
                            {{-- Una línea ABIERTA no pidió un número, pidió «lo que quepa».
                                 `number_format(null)` da «0», y del otro lado de este link
                                 hay un cliente o un conductor leyendo «Pedidas 0 · Van 420»
                                 — que se entiende como que el plan está mal armado. --}}
                            <td class="px-3 py-2 text-right tabular-nums text-neutral-500">
                                {{ $l['pedidas_unidades'] === null ? 'Lo que quepa' : number_format($l['pedidas_unidades'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ number_format($l['cargadas_unidades'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- El veredicto NO se repite acá: desde el 12-08 va pegado al borde de arriba
             del lienzo, en la misma franja que ve la pantalla interna. Dicho dos veces
             en la misma página era el exceso de texto que el dueño pidió recortar.

             Y ya no hay una rama «un solo producto» con su propio «Entran N»: con la fusión
             del 21-08 un producto es una carga de una línea, así que entra por la tabla de
             arriba y su número lo dice el mismo veredicto del lienzo. Dos textos distintos
             para la misma respuesta era la forma de que uno de los dos quedara viejo. --}}
    @endif

    {{-- El mismo aviso que lleva la planilla: afuera de la app, un número sin
         contexto se lee como una promesa. --}}
    <p class="mt-5 border-t border-neutral-100 pt-4 text-xs leading-relaxed text-neutral-500">
        Cálculo geométrico de referencia (sin pasillo y sin factor de corrección): es un
        máximo, no un compromiso de despacho. Este enlace vence el
        {{ $vence->format('d-m-Y') }}.
    </p>
</x-guest-layout>
