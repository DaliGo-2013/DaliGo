{{--
    CUBICAR: medir un bulto que no está en el catálogo y verlo mientras se define.

    Pedido del dueño (12-08-2026), mostrando el panel de EasyCargo: «un botón que diga
    cubicar y se desprenda una vista con las medidas, y uno puede ver una caja como va
    quedando de tamaño, cuántas unidades agregar y los kilos que van haciendo, creo que
    sería muy bueno para darle más personalidad».

    ────────────────────────────────────────────────────────────────────────────────────
    LO QUE SE CALCULA ACÁ Y LO QUE NO. Esta vista hace SOLO la aritmética del bulto:
    volumen de uno, volumen de todos y kilos totales. Tres multiplicaciones que se pueden
    verificar de memoria.

    Cuántos ENTRAN en el camión NO se calcula acá, y es a propósito: sería un segundo
    motor —en JavaScript, sin candados— dando un número que la pantalla de al lado
    calcula distinto. El día que difieran, nadie sabría a cuál creerle. Así que el botón
    agrega el bulto a la carga y el veredicto lo da el motor de siempre.
    ────────────────────────────────────────────────────────────────────────────────────

    LA CAJA SE DIBUJA CON LAS PROPORCIONES REALES (proyección de gabinete, tres caras en
    SVG). No es decoración: escribir «120 × 100 × 80» y ver que sale casi un cubo es lo
    que delata un tipeo —un 20 que era 200— antes de que entre a la carga.
--}}
<div x-show="cubicar" x-cloak
     class="border-t border-neutral-200 bg-neutral-50/70 p-3 sm:p-4"
     x-data="{
         nombre: '', l: 120, w: 100, h: 80, kg: 220, porUnidad: false, pcs: 10,

         /* Medidas válidas = las tres > 0. Sin esto el SVG divide por cero y la caja
            desaparece mientras se está tipeando. */
         get ok() { return +this.l > 0 && +this.w > 0 && +this.h > 0; },
         get m3() { return this.ok ? (+this.l * +this.w * +this.h) / 1e6 : 0; },
         get m3Total() { return this.m3 * Math.max(1, +this.pcs || 1); },
         /* Los kilos se pueden dictar TOTALES o POR UNIDAD, como en el panel de
            EasyCargo: la etiqueta del proveedor a veces dice una cosa y a veces la otra,
            y hacer la división a mano es donde se cuela el error. */
         get kgUnidad() { return this.porUnidad ? +this.kg || 0 : (+this.kg || 0) / Math.max(1, +this.pcs || 1); },
         get kgTotal() { return this.porUnidad ? (+this.kg || 0) * Math.max(1, +this.pcs || 1) : (+this.kg || 0); },

         /* ── El dibujo. Escala: el lado más largo mide 92 px. ── */
         get esc() { return 92 / Math.max(+this.l, +this.w, +this.h, 1); },
         get L() { return +this.l * this.esc; },
         get H() { return +this.h * this.esc; },
         get dx() { return +this.w * this.esc * 0.58; },
         get dy() { return +this.w * this.esc * 0.34; },
         get caja() { return `0 0 ${(this.L + this.dx + 4).toFixed(1)} ${(this.H + this.dy + 4).toFixed(1)}`; },
         pts(cara) {
             const L = this.L, H = this.H, dx = this.dx, dy = this.dy;
             const p = {
                 frente: [[0, dy + H], [L, dy + H], [L, dy], [0, dy]],
                 techo: [[0, dy], [L, dy], [L + dx, 0], [dx, 0]],
                 costado: [[L, dy + H], [L + dx, H], [L + dx, 0], [L, dy]],
             }[cara];
             return p.map(([x, y]) => `${(x + 2).toFixed(1)},${(y + 2).toFixed(1)}`).join(' ');
         },

         /* Al agregarlo se convierte en una LÍNEA A MEDIDA de la carga, que es la pieza
            que ya existía (y que estaba enterrada en el formulario de abajo). Así el
            bulto cubicado pasa por el mismo motor, el mismo dibujo y el mismo Excel que
            todo lo demás — cero camino paralelo. */
         /*
          * EL PANEL NO SE CIERRA AL AGREGAR (pedido del dueño 12-08): «le doy clic y se
          * sale todo y me deja la interfaz sin nada… quiero que se vayan agregando los
          * productos, que queden en una lista y me dé la opción de volver a cubicar otra
          * cosa. El mayor provecho se le saca por unidad».
          *
          * El cálculo vive en el servidor —un solo motor, ver el encabezado— así que
          * agregar SIEMPRE recarga la página: es la única forma de que el camión que se ve
          * sea el que el motor verificó. Lo que estaba mal no era la recarga, era volver
          * con el panel cerrado y la pantalla en otra parte. Ahora el `cubicar=1` viaja en
          * el formulario, así que la página vuelve con el panel ABIERTO, la lista de lo que
          * ya subió a la vista y el próximo bulto listo para tipear.
          *
          * Las medidas NO se limpian a propósito: el bulto que sigue suele ser parecido al
          * anterior (otra caja de la misma serie), así que se cambia el número que cambia y
          * listo. Vaciarlas obligaría a tipear tres campos de nuevo cada vez.
          */
         agregar() {
             if (! this.ok || this.lineas.length >= 8) return;
             this.lineas.push({
                 tipo: 0, cantidad: Math.max(1, +this.pcs || 1), estiba: 'auto', pallet: '',
                 medida_nombre: this.nombre || 'Bulto cubicado',
                 medida_largo: +this.l, medida_ancho: +this.w, medida_alto: +this.h,
                 medida_peso: this.kgUnidad ? this.kgUnidad.toFixed(2) : '',
             });
             this.modo = 'mixta';

             const form = this.$refs.formMixta;
             if (form && ! form.querySelector('input[name=\'cubicar\']')) {
                 const volver = document.createElement('input');
                 volver.type = 'hidden';
                 volver.name = 'cubicar';
                 volver.value = '1';
                 form.appendChild(volver);
             }

             this.$nextTick(() => form?.requestSubmit());
         },
     }">

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Cubicar un bulto</p>
        <button type="button" @click="cubicar = false"
                class="min-h-8 rounded-lg px-2 text-xs font-medium text-neutral-500 transition hover:text-neutral-800">Cerrar</button>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_15rem]">

        {{-- ① LOS DATOS --}}
        <div class="space-y-3">
            <div>
                <label class="text-xs font-medium text-neutral-600">Qué es</label>
                <input type="text" x-model="nombre" maxlength="60" placeholder="Caja de repuestos, jaula, tambor…"
                       class="mt-1 block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
            </div>

            {{-- Las tres medidas juntas y en cm, como en la cinta: escribirlas en metros
                 con coma es de donde salen los errores de coma flotante que el motor
                 evita trabajando en centímetros enteros. --}}
            <div class="grid grid-cols-3 gap-2">
                <template x-for="campo in [['l', 'Largo'], ['w', 'Ancho'], ['h', 'Alto']]" :key="campo[0]">
                    <div>
                        <label class="text-xs font-medium text-neutral-600" x-text="campo[1]"></label>
                        <div class="mt-1 flex items-center rounded-lg border border-neutral-300 bg-white focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30">
                            <input type="number" min="1" max="1200" step="1" inputmode="numeric"
                                   x-model="$data[campo[0]]"
                                   class="w-full border-0 bg-transparent px-2.5 py-2 text-sm tabular-nums focus:ring-0">
                            <span class="pr-2 text-xs text-neutral-400">cm</span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-medium text-neutral-600">Peso</label>
                    <div class="mt-1 flex items-center rounded-lg border border-neutral-300 bg-white focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30">
                        <input type="number" min="0" step="0.01" inputmode="decimal" x-model="kg"
                               class="w-full border-0 bg-transparent px-2.5 py-2 text-sm tabular-nums focus:ring-0">
                        <span class="pr-2 text-xs text-neutral-400">kg</span>
                    </div>
                    {{-- El interruptor total / por unidad, como el `total | pcs` de EasyCargo. --}}
                    <div class="mt-1.5 inline-flex rounded-lg border border-neutral-300 bg-white p-0.5 text-xs">
                        <button type="button" @click="porUnidad = false"
                                :class="! porUnidad ? 'bg-brand-600 text-white' : 'text-neutral-600'"
                                class="rounded px-2 py-1 font-medium transition">del total</button>
                        <button type="button" @click="porUnidad = true"
                                :class="porUnidad ? 'bg-brand-600 text-white' : 'text-neutral-600'"
                                class="rounded px-2 py-1 font-medium transition">por unidad</button>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-medium text-neutral-600">Unidades</label>
                    {{-- Los pasos van GRANDES (h-11) porque esto se toca en el galpón, con
                         el teléfono en una mano. Y en el medio se puede escribir: llegar a
                         137 con el botón son 137 toques. Misma decisión que los pasos de
                         carga del visor (07-08). --}}
                    <div class="mt-1 flex items-stretch gap-1">
                        <button type="button" @click="pcs = Math.max(1, (+pcs || 1) - 1)"
                                class="h-11 w-11 shrink-0 rounded-lg border border-neutral-300 bg-white text-lg font-semibold text-neutral-700 transition hover:bg-neutral-50">−</button>
                        <input type="number" min="1" max="9999" step="1" inputmode="numeric" x-model="pcs"
                               class="h-11 w-full min-w-0 rounded-lg border-neutral-300 text-center text-sm tabular-nums focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30">
                        <button type="button" @click="pcs = Math.min(9999, (+pcs || 0) + 1)"
                                class="h-11 w-11 shrink-0 rounded-lg border border-neutral-300 bg-white text-lg font-semibold text-neutral-700 transition hover:bg-neutral-50">+</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ② LA CAJA Y LOS NÚMEROS QUE VAN HACIENDO --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-3">
            <div class="flex h-32 items-center justify-center">
                <template x-if="ok">
                    <svg :viewBox="caja" class="h-full w-auto" aria-hidden="true">
                        <polygon :points="pts('frente')" fill="#f97316" fill-opacity="0.92" stroke="rgba(17,17,20,.5)" stroke-width="0.8" />
                        <polygon :points="pts('techo')" fill="#fdba74" stroke="rgba(17,17,20,.5)" stroke-width="0.8" />
                        <polygon :points="pts('costado')" fill="#ea580c" stroke="rgba(17,17,20,.5)" stroke-width="0.8" />
                    </svg>
                </template>
                <template x-if="! ok">
                    <p class="text-xs text-neutral-400">Escribí las tres medidas</p>
                </template>
            </div>

            <dl class="mt-3 space-y-1 border-t border-neutral-100 pt-2 text-xs">
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Uno mide</dt>
                    <dd class="font-medium tabular-nums text-neutral-900" x-text="m3.toFixed(3).replace('.', ',') + ' m³'"></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Los <span x-text="Math.max(1, +pcs || 1)"></span></dt>
                    <dd class="font-medium tabular-nums text-neutral-900" x-text="m3Total.toFixed(2).replace('.', ',') + ' m³'"></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-neutral-500">Cada uno pesa</dt>
                    <dd class="font-medium tabular-nums text-neutral-900" x-text="kgUnidad.toFixed(2).replace('.', ',') + ' kg'"></dd>
                </div>
                <div class="flex justify-between border-t border-neutral-100 pt-1">
                    <dt class="font-medium text-neutral-700">Peso total</dt>
                    <dd class="font-semibold tabular-nums text-brand-700" x-text="kgTotal.toFixed(2).replace('.', ',') + ' kg'"></dd>
                </div>
            </dl>

            <button type="button" @click="agregar()" :disabled="! ok || lineas.length >= 8"
                    class="mt-3 min-h-11 w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-40">
                Agregar a la carga
            </button>
            <p class="mt-1.5 text-[11px] leading-snug text-neutral-500">
                Cuántas entran lo dice el cálculo al agregarlo, con el mismo motor que el resto
                — acá solo se mide el bulto.
            </p>

            {{-- LO QUE YA VA EN EL CAMIÓN. Es la lista que pidió el dueño para poder seguir
                 agregando de a uno sin perder de vista lo anterior. Sale de `lineas`, el
                 mismo estado que manda el formulario, así que no puede desincronizarse de lo
                 que el motor calculó. Los colores y las letras son los del lienzo. --}}
            <template x-if="lineas.length">
                <div class="mt-3 border-t border-neutral-100 pt-2">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                        En el camión (<span x-text="lineas.length"></span> de 8)
                    </p>
                    <ul class="mt-1 space-y-1">
                        <template x-for="(l, i) in lineas" :key="i">
                            <li class="flex items-center gap-1.5 text-xs">
                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded text-[10px] font-bold text-white"
                                      :style="`background:${color(i)}`" x-text="letra(i)"></span>
                                <span class="min-w-0 flex-1 truncate text-neutral-600" x-text="resumen(l)"></span>
                                <span class="shrink-0 tabular-nums text-neutral-500" x-text="l.cantidad"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-1.5 text-[11px] leading-snug text-neutral-500">
                        Cubicá el siguiente y volvé a agregar. Para acomodarlos a mano, «Mover y
                        girar bloques» en el menú.
                    </p>
                </div>
            </template>
        </div>
    </div>
</div>
