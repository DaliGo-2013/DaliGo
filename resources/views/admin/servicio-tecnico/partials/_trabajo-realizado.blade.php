{{-- ═══ TRABAJO REALIZADO: SE MARCA LO QUE SE HIZO, Y LA FRASE SE ARMA SOLA ═══

     Pedido del dueño (28-08-2026): «de repente hay trabajos donde el técnico hace como tres o
     cuatro trabajos sobre un dispensador —cambio de llave, cambio de estanque, cambio de caldera
     y se agrega espigón— y esa respuesta ya no existe; la lista tendría que ser una combinación
     infinita de reparaciones que sería muy extensa, se perdería buscando una respuesta fija».

     Y el cierre del 01-09-2026, con el gerente al lado: EL TÉCNICO YA NO ESCRIBE. «Al cliquear
     los trabajos que realice se forme la respuesta de lo que el técnico hizo, ya que el gerente
     no quiere que escriban por mala ortografía y agregar más información de la que no es
     necesaria». Se fueron los DOS campos de texto que había acá («algo que no está en la lista»
     y el editable «lo que va a leer el cliente»); lo que falte en el catálogo lo agrega jefatura
     en «Costos generales de reparación» y recién ahí se puede marcar («iré agregando respuestas
     con el paso del tiempo para que tenga más opciones de trabajos que ahora no están»).

     LAS HORAS YA NO SE MUESTRAN POR CHIP, del mismo pedido: el dueño las marcó una por una en la
     pantalla. «No le pongas hora a todos los arreglos porque va a generar un problema cuando se
     sume al cobro total». El tope de 2 h existe desde el 28-08 y ese ejemplo suyo (caldera 1,5 +
     relé 1 + ventilador 1) SIEMPRE cobró 2 h, no 3 — lo que fallaba era la pantalla, que ponía
     21 números sueltos invitando a sumarlos mentalmente. El técnico no decide esas horas ni las
     edita, así que verlas por chip no le servía para nada y solo prometía una acumulación que el
     guardado no hace. El único número que queda es el que se va a cobrar, con su aritmética.

     El estado vive en el x-data del formulario (`reparacionForm`, en resources/js/app.js) y no en
     uno propio: la mano de obra alimenta el total de la pantalla, así que tiene que ser el mismo
     componente. Además, un x-data anidado con métodos de nombre repetido es el footgun de la
     bitácora [2026-08-25].

     Requiere: $trabajosCatalogo, $marcadosInit, $rematesTrabajo, $topeHoras, $errors. --}}
<div>
    <x-input-label value="Trabajo realizado">
        <x-slot:ayuda>
            Marca todos los trabajos que hiciste y la frase que lee el cliente se arma sola: no hay
            que escribir nada. Las horas se suman con un tope de
            {{ \App\Models\TiempoReparacion::fmt($topeHoras) }} h por orden (el desarme se paga una
            vez, no una hora por cada cambio). Si hiciste algo que no está en la lista, pídele a
            jefatura que lo agregue en «Costos generales de reparación».
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

    {{-- El remate, UNA vez. El catálogo lo trae pegado a cada trabajo («Cambio de caldera —
         funciona normal») porque nació como respuesta completa de UNA reparación; con tres
         marcados, repetirlo tres veces sería absurdo.

         SÍ viaja al servidor (`remate`), a diferencia de antes: ahora que la frase se arma allá,
         el servidor necesita saber con cuál cerrarla. Se valida contra la misma lista que la
         pantalla ofrece, así que sigue sin ser texto libre. --}}
    @if ($rematesTrabajo)
        <div class="mt-4">
            {{-- La explicación va en la ⓘ y abajo queda UNA línea corta con lo operativo
                 (doctrina del dueño 2026-08-17: el tope son ~95 caracteres POR CAMPO, y lo
                 vigila AyudaEnIconoTest nombrando archivo:línea). --}}
            <x-input-label value="¿Cómo quedó el equipo?">
                <x-slot:ayuda>
                    Cierra la frase que lee el cliente, una sola vez para todos los trabajos marcados
                    (el catálogo trae el cierre pegado a cada uno, y repetirlo tres veces sería absurdo).
                    Se propone según lo que marcaste; si lo cambias, manda tu elección.
                </x-slot:ayuda>
            </x-input-label>
            <div class="mt-1.5 grid gap-2 sm:grid-cols-3">
                @foreach ($rematesTrabajo as $r)
                    <x-chip-radio name="remate" :value="$r" :label="$r"
                                  x-model="remate"
                                  x-on:change="remateElegido()" />
                @endforeach
            </div>
            <x-input-hint>Cierra la frase del cliente.</x-input-hint>
            <x-input-error :messages="$errors->get('remate')" class="mt-2" />
        </div>
    @endif

    {{-- LO QUE VA A LEER EL CLIENTE: se muestra, no se edita. Que el técnico VEA la frase antes de
         guardar es lo que hace que marcar bien importe; poder tocarla es justo lo que se quitó.
         Va en un <p> y no en un input deshabilitado a propósito: un campo gris apagado se lee como
         «esto se llena después», y esto no se llena nunca a mano. --}}
    <div class="mt-4">
        <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que va a leer el cliente</p>
        <div class="mt-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm" x-cloak>
            <template x-if="textoCliente">
                <p class="text-neutral-900" x-text="textoCliente"></p>
            </template>
            <template x-if="! textoCliente">
                <p class="text-neutral-400">Se arma con los trabajos que marques arriba.</p>
            </template>
        </div>
        {{-- SIN `x-input-hint`, y no es un olvido: esto no es un campo —no hay nada que llenar— y
             una ayuda debajo se leería como que algo falta por hacer. Que se arma solo ya lo dicen
             la ⓘ de «Trabajo realizado» y el propio texto en gris cuando todavía no hay nada
             marcado. Además el tope de la doctrina se mide POR CAMPO (dueño 2026-08-17) y este
             renglón se le sumaba al del cierre de la frase, que sí es un control. --}}
    </div>

    {{-- LA MANO DE OBRA, CON LA ARITMÉTICA A LA VISTA. Que el técnico VEA el tope actuando es lo
         que evita la pregunta «¿por qué me dio esto?» — y ahora es, además, el único lugar de la
         pantalla donde aparecen horas. --}}
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
                <template x-if="topeRecorta">
                    <p class="text-neutral-600">
                        <span x-text="marcados.length"></span> trabajos suman
                        <span class="tabular-nums" x-text="fmtHoras(horasSumadas) + ' h'"></span>
                        <span class="text-neutral-400">·</span> se cobra el tope del taller
                        <span class="tabular-nums" x-text="fmtHoras(topeHoras) + ' h'"></span>
                    </p>
                </template>
                <p class="mt-0.5 text-neutral-900">
                    Mano de obra
                    <span class="font-semibold" x-text="clp(manoObra)"></span>
                    <span class="text-neutral-500">
                        (<span class="tabular-nums" x-text="fmtHoras(horasACobrar)"></span> h ×
                        <span x-text="clp(precioHora)"></span>)
                    </span>
                </p>
                <p class="mt-1 text-xs text-neutral-400">La fija jefatura, no se edita acá.</p>
            </div>
        </template>
    </div>
</div>
