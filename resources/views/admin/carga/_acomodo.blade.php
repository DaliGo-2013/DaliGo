{{--
    EL TABLERO PARA ACOMODAR A MANO. Requiere: $escena.

    Pedido del dueño, tres veces (la última el 11-08-2026, textual): «que te dé la
    opción de dar vuelta la caja y acomodar como uno quiero». Las dos primeras la
    respuesta fue que no: arrastrar bultos deja armar en pantalla una carga que el
    cálculo dice que no cabe. El reparo sigue en pie y por eso está el cartel de
    «el cálculo no verificó estas posiciones» — pero la decisión es del dueño, que
    es quien carga los camiones.

    ES UNA VISTA DE PLANTA, no un editor 3D. El motor razona en huellas sobre el
    piso —el reparto por regiones es 2D y la altura sale del apilado—, así que un
    tablero de planta habla exactamente el mismo idioma que el cálculo. Arrastrar
    en perspectiva, en cambio, obliga a adivinar la profundidad con el mouse: se
    ve más vistoso y se acomoda peor.

    SE MUEVEN BLOQUES, no cajas sueltas. Un bloque es una rejilla de cajas iguales,
    que es la unidad con la que el motor coloca y con la que se carga de verdad
    (una estiba entera, no una caja). Y la caja suelta igual se puede: una línea de
    UNA unidad es un bloque de uno — ahí está el «cargar de a un bulto» del pedido.

    LO QUE NO HACE, a propósito:
      · No recuenta. Mover no descubre lugar nuevo; los números siguen siendo los
        que dio el motor (ver `AcomodoManual`).
      · No corrige lo que quedó mal. Dos bloques encimados se marcan, no se separan
        solos: separarlos sería volver a decidir por el usuario.
      · No vuelca cajas. Girar es sobre el piso; volcar cambia cuántas se apilan y
        eso es una pregunta para el motor («Cómo viaja»), no para el mouse.
--}}
@php
    $acomodo = $escena['acomodo'] ?? null;
@endphp

@if ($acomodo && $acomodo['piezas'] !== [])
    {{-- `tablero` vive en el x-data del visor (`_visor.blade.php`): el botón que lo abre
         está en el menú lateral, junto al resto de las herramientas, y no acá adentro —
         un panel que solo se puede cerrar desde adentro no se encuentra para abrirlo. --}}
    <div x-show="tablero" x-cloak
         class="border-t border-neutral-200 bg-neutral-50/70 px-4 py-3"
         x-data="{
            piso: @js($acomodo['piso']),
            piezas: @js($acomodo['piezas']),
            sel: null,
            arrastre: null,

            /* IMÁN, en centímetros. Sin él, arrastrar deja huecos de 2 o 3 cm entre
               bloques que están pegados en el camión, y esos huecos se acumulan hasta
               que la carga «no entra» por una razón que no existe. Pega contra las
               paredes y contra los cantos de los vecinos. */
            IMAN: 4,

            cmPorPx() { return this.piso.largo / this.$refs.piso.getBoundingClientRect().width; },

            estilo(p) {
                return `left:${p.x / this.piso.largo * 100}%;`
                     + `top:${p.y / this.piso.ancho * 100}%;`
                     + `width:${p.largo / this.piso.largo * 100}%;`
                     + `height:${p.ancho / this.piso.ancho * 100}%;`
                     + `background:${p.color};`;
            },

            tomar(i, ev) {
                const r = this.$refs.piso.getBoundingClientRect(), k = this.cmPorPx();
                this.sel = i;
                this.arrastre = {
                    i,
                    dx: (ev.clientX - r.left) * k - this.piezas[i].x,
                    dy: (ev.clientY - r.top) * k - this.piezas[i].y,
                };
                /* La captura va en el PISO y no en la pieza: con la captura en la
                   pieza, mover rápido saca el puntero del rectángulo y el bloque se
                   queda clavado a mitad de camino. */
                this.$refs.piso.setPointerCapture(ev.pointerId);
            },

            mover(ev) {
                if (! this.arrastre) return;
                const r = this.$refs.piso.getBoundingClientRect(), k = this.cmPorPx();
                const p = this.piezas[this.arrastre.i];
                p.x = this.ubicar(Math.round((ev.clientX - r.left) * k - this.arrastre.dx),
                                  p.largo, this.piso.largo, this.arrastre.i, 'x', 'largo');
                p.y = this.ubicar(Math.round((ev.clientY - r.top) * k - this.arrastre.dy),
                                  p.ancho, this.piso.ancho, this.arrastre.i, 'y', 'ancho');
            },

            soltar() { this.arrastre = null; },

            /* Imanta contra las paredes y los cantos de los vecinos, y después NO deja
               salir del camión. El recorte es del tablero, no de la regla: el servidor
               vuelve a chequearlo porque la URL se puede editar a mano. */
            ubicar(v, propio, piso, i, eje, lado) {
                const candidatos = [0, piso - propio];
                this.piezas.forEach((q, j) => {
                    if (j === i) return;
                    candidatos.push(q[eje], q[eje] + q[lado], q[eje] - propio);
                });
                for (const c of candidatos) {
                    if (Math.abs(v - c) <= this.IMAN) { v = c; break; }
                }

                return Math.max(0, Math.min(v, Math.max(0, piso - propio)));
            },

            girar() {
                if (this.sel === null) return;
                const p = this.piezas[this.sel];
                [p.largo, p.ancho] = [p.ancho, p.largo];
                p.girado = ! p.girado;
                p.x = Math.max(0, Math.min(p.x, Math.max(0, this.piso.largo - p.largo)));
                p.y = Math.max(0, Math.min(p.y, Math.max(0, this.piso.ancho - p.ancho)));
            },

            /* TECLADO. No es un extra de accesibilidad: acomodar con el mouse llega hasta
               el centímetro que da el píxel —en un camión de 8 m sobre 1000 px, cada píxel
               son 0,8 cm— así que el ajuste fino de verdad se hace con las flechas. Y de
               paso el tablero se puede usar sin mouse. Sin imán, que acá estorbaría: el
               que aprieta la flecha ya sabe adónde va. */
            teclado(i, ev) {
                const p = this.piezas[i];
                const paso = ev.shiftKey ? 10 : 1;
                const mov = {
                    ArrowLeft: [-paso, 0], ArrowRight: [paso, 0],
                    ArrowUp: [0, -paso], ArrowDown: [0, paso],
                }[ev.key];

                this.sel = i;
                if (ev.key === 'r' || ev.key === 'R') { ev.preventDefault(); this.girar(); return; }
                if (! mov) return;

                ev.preventDefault();
                p.x = Math.max(0, Math.min(p.x + mov[0], Math.max(0, this.piso.largo - p.largo)));
                p.y = Math.max(0, Math.min(p.y + mov[1], Math.max(0, this.piso.ancho - p.ancho)));
            },

            /* Los mismos dos chequeos que hace el servidor, en vivo: tocarse no es
               pisarse (comparación estricta), así que dos bloques pegados —que es como
               se carga— no salen en rojo. */
            choca(i) {
                const a = this.piezas[i];

                return this.piezas.some((b, j) => j !== i
                    && a.x < b.x + b.largo && b.x < a.x + a.largo
                    && a.y < b.y + b.ancho && b.y < a.y + a.ancho);
            },
            get encimados() { return this.piezas.filter((p, i) => this.choca(i)).length; },

            /* Se conserva TODA la query actual y solo se reemplaza el acomodo: la URL es
               el escenario entero (camión, líneas, estiba, apilado), así que armar una
               nueva perdería la carga que el usuario acaba de calcular. */
            url(conAcomodo) {
                const u = new URL(window.location.href);
                [...u.searchParams.keys()]
                    .filter(k => k === 'acomodo_de' || k.startsWith('acomodo['))
                    .forEach(k => u.searchParams.delete(k));

                if (conAcomodo) {
                    this.piezas.forEach((p, i) => u.searchParams.set(
                        `acomodo[${i}]`, `${p.x},${p.y}${p.girado ? ',g' : ''}`,
                    ));
                    u.searchParams.set('acomodo_de', this.piezas.length);
                }

                return u.toString();
            },
         }"
         @pointermove="mover($event)" @pointerup="soltar()" @pointercancel="soltar()">

        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                Acomodar a mano · vista de planta
            </div>
            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                <button type="button" @click="girar()" :disabled="sel === null"
                        class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-40">
                    ⟳ Dar vuelta
                </button>
                <a :href="url(false)"
                   class="rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                    Volver al automático
                </a>
                <a :href="url(true)"
                   class="rounded-lg bg-neutral-800 px-2.5 py-1.5 font-semibold text-white transition hover:bg-neutral-900">
                    Aplicar al camión
                </a>
            </div>
        </div>

        {{-- EL PISO. La proporción sale de las medidas útiles del camión, así que un
             centímetro a lo largo mide en pantalla lo mismo que uno a lo ancho: sin eso,
             un bloque cuadrado se vería alargado y nadie podría acomodar mirándolo.
             El fondo (la cabina) va a la izquierda y la puerta a la derecha, el mismo
             sentido en que el motor reparte y en que se lee el orden de carga. --}}
        <div x-ref="piso"
             class="relative w-full touch-none select-none overflow-hidden rounded-xl border-2 border-neutral-400 bg-neutral-200"
             :style="`aspect-ratio: ${piso.largo} / ${piso.ancho}`">
            <template x-for="(p, i) in piezas" :key="i">
                <div class="absolute cursor-grab overflow-hidden border text-white transition-shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
                     :class="[
                        choca(i) ? 'border-red-600 ring-2 ring-red-500' : 'border-black/40',
                        sel === i ? 'z-10 shadow-lg ring-2 ring-neutral-900' : '',
                     ]"
                     :style="estilo(p)"
                     tabindex="0" role="button"
                     @pointerdown.prevent="tomar(i, $event)"
                     @keydown="teclado(i, $event)"
                     @focus="sel = i"
                     @dblclick="sel = i; girar()"
                     :aria-label="`${p.nombre}, bloque ${p.letra}: ${p.largo} por ${p.ancho} centímetros, en ${p.x}, ${p.y}. Flechas para mover, R para girar.`"
                     :title="`${p.nombre} · ${p.cantidad} · ${p.largo} × ${p.ancho} cm`">
                    <span class="absolute left-1 top-0.5 text-[11px] font-bold leading-tight drop-shadow"
                          x-text="p.letra"></span>
                </div>
            </template>
        </div>

        <p class="mt-2 text-xs leading-relaxed text-neutral-500">
            Arrastrá los bloques; tocá uno y usá <span class="font-medium text-neutral-700">Dar vuelta</span>
            (o doble clic) para girarlo 90° sobre el piso. Se pegan solos a las paredes y entre ellos.
            Con el bloque marcado, las <span class="font-medium text-neutral-700">flechas</span> lo mueven
            de a 1 cm (10 con Shift) y la <span class="font-medium text-neutral-700">R</span> lo gira.
            La cabina queda a la izquierda y la puerta a la derecha.
            <span x-show="encimados > 0" x-cloak class="font-semibold text-red-600">
                Hay <span x-text="encimados"></span> bloque(s) encimado(s).
            </span>
        </p>
    </div>
@endif
