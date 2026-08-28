{{-- ═══ TRABAJO REALIZADO: SE MARCA LO QUE SE HIZO, Y PUEDE SER MÁS DE UNA COSA ═══

     Pedido del dueño (28-08-2026): «de repente hay trabajos donde el técnico hace como tres o
     cuatro trabajos sobre un dispensador —cambio de llave, cambio de estanque, cambio de caldera
     y se agrega espigón— y esa respuesta ya no existe; la lista tendría que ser una combinación
     infinita de reparaciones que sería muy extensa, se perdería buscando una respuesta fija».

     ANTES: UNA respuesta, y la mano de obra salía de que el TEXTO coincidiera palabra por palabra
     con una fila del catálogo. Con 21 trabajos hay más de mil combinaciones de tres, así que
     ninguna reparación mixta podía coincidir con nada: el técnico escribía la frase a mano, la
     mano de obra quedaba en $0 y eso TRABABA el envío de la cotización (lo vivió Fernando, y lo
     estaba rodeando cargando la hora de servicio técnico como si fuera un repuesto). De paso,
     ajustarle una coma a una respuesta de la lista también borraba el dinero.

     AHORA: se marcan los trabajos, las horas se suman con un TOPE (el desarme se paga una vez),
     el texto del cliente se arma solo y sigue editable — pero editarlo ya no mueve ni un peso,
     porque el dinero sale de los chips.

     La lista sale del CATÁLOGO (base de datos) y no de `config`, que es de donde salía: eran dos
     listas y ya divergían — un trabajo que jefatura agregaba en «Costos generales de reparación»
     no aparecía nunca acá.

     El estado vive en el x-data del formulario (`reparacionForm`, en resources/js/app.js) y no en
     uno propio: la mano de obra alimenta el total de la pantalla, así que tiene que ser el mismo
     componente. Además, un x-data anidado con métodos de nombre repetido es el footgun de la
     bitácora [2026-08-25].

     Requiere: $trabajosCatalogo, $marcadosInit, $extraInicial, $rematesTrabajo, $topeHoras,
     $textoInicial, $errors. --}}
<div>
    <x-input-label value="Trabajo realizado">
        <x-slot:ayuda>
            Marca todos los trabajos que hiciste: las horas se suman solas, con un tope de
            {{ \App\Models\TiempoReparacion::fmt($topeHoras) }} h (el desarme se paga una vez, no una
            hora por cada cambio). Lo que no esté en la lista escríbelo abajo.
            <strong>El texto final lo lee el cliente</strong> en el correo del retiro y en la
            cotización, y lo puedes ajustar: editarlo NO cambia la mano de obra. El navegador te
            subraya en rojo lo que esté mal escrito — clic derecho sobre la palabra para ver las
            sugerencias.
        </x-slot:ayuda>
    </x-input-label>

    {{-- Los chips, en un colapsable con el resumen de lo marcado: son 21 opciones y dejarlas
         siempre abiertas empuja el resto de la pantalla fuera del celular (doctrina de pantallas
         de operario). Se abre solo si no hay nada marcado —que es cuando hay que elegir— y ante un
         error de validación. --}}
    <div class="mt-1.5" x-data="{ abierto: {{ $marcadosInit->isEmpty() || $errors->has('trabajos') ? 'true' : 'false' }} }">
        <button type="button" x-on:click="abierto = ! abierto"
                class="flex min-h-12 w-full items-center justify-between gap-3 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-left text-sm shadow-sm transition duration-150 hover:bg-neutral-50"
                x-bind:aria-expanded="abierto ? 'true' : 'false'">
            <span class="text-neutral-700">
                <template x-if="marcados.length === 0">
                    <span class="text-neutral-500">Marca los trabajos que hiciste</span>
                </template>
                <template x-if="marcados.length > 0">
                    <span>
                        <span class="font-medium text-neutral-900" x-text="marcados.length"></span>
                        <span x-text="marcados.length === 1 ? 'trabajo marcado' : 'trabajos marcados'"></span>
                        <span class="text-neutral-400">·</span>
                        <span class="tabular-nums" x-text="fmtHoras(horasACobrar) + ' h'"></span>
                    </span>
                </template>
            </span>
            {{-- El binding va en un ENVOLTORIO y no en el componente del ícono: una directiva
                 Alpine sobre un `<x-componente>` sin el prefijo `:` no se compila y viaja como
                 texto literal (bitácora [2026-08-14], el chevron que no rotaba). --}}
            <span class="shrink-0 text-neutral-400 transition duration-150" x-bind:class="abierto ? 'rotate-180' : ''">
                <x-icon.chevron-down class="h-4 w-4" />
            </span>
        </button>

        <div x-show="abierto" x-cloak class="mt-2 space-y-4">
            @foreach ($trabajosCatalogo as $grupo => $opciones)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $grupo }}</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($opciones as $t)
                            <x-chip-trabajo name="trabajos[]" :value="$t->id"
                                            :label="$t->trabajo_corto"
                                            :horas="\App\Models\TiempoReparacion::fmt((float) $t->horas)"
                                            x-model.number="marcados"
                                            x-on:change="trabajosCambiaron()" />
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    {{-- El hidden garantiza que la clave `trabajos` SIEMPRE viaje, incluso con todo desmarcado.
         Sin él, «desmarqué todo» y «esta pantalla no preguntó por los trabajos» llegarían iguales
         al servidor, y el guardado no podría distinguir un conjunto vacío legítimo de un campo
         ausente — que es como se borran los datos en silencio (bitácora [2026-08-20]). El valor
         vacío lo descarta la validación. --}}
    <input type="hidden" name="trabajos[]" value="">
    <x-input-error :messages="$errors->get('trabajos')" class="mt-2" />
    <x-input-error :messages="$errors->get('trabajos.*')" class="mt-2" />

    {{-- Lo que NO está en la lista. Una línea por trabajo. Esto es lo que después jefatura ve
         junto en «Costos generales de reparación» para agregarlo al catálogo: así el catálogo se
         calibra con el uso real en vez de intentar adivinar combinaciones. --}}
    <div class="mt-4">
        <x-input-label for="trabajos_extra" value="Algo que no está en la lista">
            <x-slot:ayuda>
                Escribe acá lo que hiciste y no aparece arriba, una cosa por línea (por ejemplo
                «cambio de estanque» o «se agrega espigón»). Entra en el texto que lee el cliente,
                pero no tiene tiempo estándar todavía: jefatura ve lo que se escribe seguido acá y
                lo agrega al catálogo.
            </x-slot:ayuda>
        </x-input-label>
        <x-textarea id="trabajos_extra" name="trabajos_extra" rows="2" class="mt-1.5"
                    spellcheck="true" lang="es" autocapitalize="sentences"
                    maxlength="{{ \App\Models\OrdenServicio::TRABAJO_MAX }}"
                    x-model="trabajosExtra"
                    x-on:input="trabajosCambiaron()"
                    placeholder="Ej. cambio de estanque">{{ $extraInicial }}</x-textarea>
        <x-input-hint>Una cosa por línea. Opcional.</x-input-hint>
        <x-input-error :messages="$errors->get('trabajos_extra')" class="mt-2" />
    </div>

    {{-- El remate, UNA vez. El catálogo lo trae pegado a cada trabajo («Cambio de caldera —
         funciona normal») porque nació como respuesta completa de UNA reparación; con tres
         marcados, repetirlo tres veces sería absurdo. El `name` no es el de ningún campo del
         servidor a propósito: esto no se guarda aparte, queda dentro del texto. --}}
    @if ($rematesTrabajo)
        <div class="mt-4">
            <x-input-label value="¿Cómo quedó el equipo?" />
            <div class="mt-1.5 grid gap-2 sm:grid-cols-3">
                @foreach ($rematesTrabajo as $r)
                    <x-chip-radio name="remate_visual" :value="$r" :label="$r"
                                  x-model="remate"
                                  x-on:change="armarTexto()" />
                @endforeach
            </div>
            <x-input-hint>Cierra la frase que lee el cliente. No viaja al servidor: queda dentro del texto.</x-input-hint>
        </div>
    @endif

    {{-- LA MANO DE OBRA, CON LA ARITMÉTICA A LA VISTA. Que el técnico VEA el tope actuando es lo
         que evita la pregunta «¿por qué me dio esto?». --}}
    <div class="mt-4 rounded-lg bg-neutral-50 px-3 py-2 text-sm" x-cloak>
        <template x-if="marcados.length === 0">
            <p class="text-neutral-600">
                Mano de obra <span class="font-medium text-neutral-900">$0</span> · marca al menos un
                trabajo de la lista.
                <span class="text-neutral-400">La cotización no se envía hasta entonces; guardar sí se puede.</span>
            </p>
        </template>
        <template x-if="marcados.length > 0">
            <div>
                <p class="text-neutral-600">
                    <span x-text="marcados.length"></span>
                    <span x-text="marcados.length === 1 ? 'trabajo suma' : 'trabajos suman'"></span>
                    <span class="tabular-nums" x-text="fmtHoras(horasSumadas) + ' h'"></span>
                    <template x-if="topeRecorta">
                        <span>
                            <span class="text-neutral-400">·</span> tope del taller
                            <span class="tabular-nums" x-text="fmtHoras(topeHoras) + ' h'"></span>
                        </span>
                    </template>
                </p>
                <p class="mt-0.5 text-neutral-900">
                    Mano de obra
                    <span class="font-semibold" x-text="clp(manoObra)"></span>
                    <span class="text-neutral-500">
                        (<span class="tabular-nums" x-text="fmtHoras(horasACobrar)"></span> h ×
                        <span x-text="clp(precioHora)"></span>)
                    </span>
                </p>
                <template x-if="extraLista.length > 0">
                    <p class="mt-1 text-xs text-neutral-500">
                        <span x-text="extraLista.length"></span>
                        <span x-text="extraLista.length === 1 ? 'trabajo escrito a mano, sin tiempo estándar' : 'trabajos escritos a mano, sin tiempo estándar'"></span>
                        — no suman horas. Jefatura los ve y puede agregarlos al catálogo.
                    </p>
                </template>
                <p class="mt-1 text-xs text-neutral-400">La fija jefatura, no se edita acá.</p>
            </div>
        </template>
    </div>

    {{-- EL TEXTO QUE LEE EL CLIENTE. Se arma solo con lo de arriba y queda editable; en cuanto el
         técnico lo toca deja de re-armarse (pisar lo que alguien escribió es peor que dejarlo
         desalineado de los chips) y aparece el enlace para rehacerlo.

         El centinela sigue viajando en su hidden, así que el contrato con el controlador no
         cambia: `trabajo_realizado` = centinela y `trabajo_realizado_otro` = el texto, con su
         colapso de espacios y su tope ya aplicándose sin tocar una línea de PHP. --}}
    <div class="mt-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
            <x-input-label for="trabajo_realizado_otro" value="Lo que va a leer el cliente" />
            <button type="button" x-on:click="armarTexto()" x-show="textoTocado" x-cloak
                    class="text-xs font-medium text-brand-700 hover:text-brand-800">
                Rehacer con lo marcado
            </button>
        </div>
        <input type="hidden" name="trabajo_realizado"
               x-bind:value="texto.trim() ? @js(\App\Models\OrdenServicio::TRABAJO_OTRO) : ''">
        <x-textarea id="trabajo_realizado_otro" name="trabajo_realizado_otro" rows="2" class="mt-1.5"
            x-model="texto" x-on:input="textoTocado = true"
            spellcheck="true" lang="es" autocapitalize="sentences"
            maxlength="{{ \App\Models\OrdenServicio::TRABAJO_MAX }}"
            placeholder="Se arma con lo que marques arriba">{{ $textoInicial }}</x-textarea>
        <x-input-hint>Se arma solo; puedes ajustarlo. Editarlo no cambia la mano de obra.</x-input-hint>
        <x-input-error :messages="$errors->get('trabajo_realizado_otro')" class="mt-2" />
    </div>
    <x-input-error :messages="$errors->get('trabajo_realizado')" class="mt-2" />
</div>
