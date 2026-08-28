{{-- LOS CAMPOS DEL PRESUPUESTO: repuestos con su precio, mano de obra, descuento y
     el total con su desglose de IVA, mas la advertencia de gasto alto.

     ESTA EN UN PARTIAL porque desde el 20-08 vive en el PARTE DEL TECNICO (dueno:
     «que no exista un apartado… en la parte del tecnico se pueda modificar la
     informacion»). Una sola definicion: si estuviera copiado, un dia las dos
     pantallas cobrarian distinto.

     `$conPrecios = false` deja SOLO los repuestos (nombre y cantidad): es el caso
     GARANTIA, donde no hay cobro. Sin ese modo, la pantalla de una garantia mostraba
     un «Costo total a pagar» que contradice a toda la app —y al correo que recibe el
     cliente, que lista los repuestos SIN precios.

     Espera estar DENTRO de un formulario con `x-data="reparacionForm(...)"` — de ahi
     salen `repuestos`, `manoObra`, `descuentoPct`, `total` y `clp()`.
     Requiere: $orden, $precioHoraServicio, $precioVentaEquipo, $horasTrabajo, $clp.
--}}
@php $conPrecios = $conPrecios ?? true; @endphp
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $conPrecios ? 'Detalle del presupuesto' : 'Repuestos usados' }}</h3>
                    @unless ($conPrecios)
                        <span class="text-xs text-neutral-400">Garantía: no se cobra</span>
                    @endunless
                </div>

                {{-- Repuestos: se pueden agregar buscándolos del catálogo, con precio.
                     (También llegan los que declaró el técnico en su parte.) --}}
                <div>
                    <div class="flex items-center justify-between">
                        <x-input-label value="Repuestos" />
                        <x-agregar-fila-button x-on:click="agregar()">Agregar repuesto</x-agregar-fila-button>
                    </div>

                    <div class="mt-2 space-y-2">
                        <template x-for="(r, i) in repuestos" :key="i">
                            <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-2 sm:flex-row sm:items-start sm:gap-2 sm:rounded-none sm:border-0 sm:p-0">
                                {{-- SKU del catálogo (oculto): lo pone el buscador al elegir, y se
                                     conserva al re-guardar. Vacío si se escribió a mano. --}}
                                <input type="hidden" :name="`repuestos[${i}][sku]`" :value="r.sku ?? ''">
                                <div class="relative sm:flex-1" x-on:click.outside="filaActiva === i && cerrarSugerencias()">
                                    <input type="text" x-model="r.nombre" :name="`repuestos[${i}][nombre]`"
                                        placeholder="Código o nombre del repuesto" maxlength="191" autocomplete="off"
                                        x-on:input.debounce.250ms="buscarRepuesto(i)"
                                        x-on:focus="buscarRepuesto(i)"
                                        x-on:keydown.escape="cerrarSugerencias()"
                                        class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">

                                    <div x-show="filaActiva === i && (buscandoRepuesto || sugerencias.length)" x-cloak
                                        class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg">
                                        <template x-if="buscandoRepuesto && sugerencias.length === 0">
                                            <div class="px-3.5 py-2.5 text-sm text-neutral-400">Buscando…</div>
                                        </template>
                                        <ul class="max-h-60 divide-y divide-neutral-100 overflow-auto">
                                            <template x-for="(s, si) in sugerencias" :key="si">
                                                <li>
                                                    <button type="button" x-on:click="elegirRepuesto(i, s)"
                                                        class="flex w-full items-center justify-between gap-2 px-3.5 py-2.5 text-left text-sm text-neutral-700 transition hover:bg-neutral-50">
                                                        <span class="min-w-0">
                                                            <span x-show="s.sku" class="font-mono text-xs text-neutral-400" x-text="s.sku"></span>
                                                            <span x-text="s.nombre"></span>
                                                        </span>
                                                        <span x-show="s.precio !== null && s.precio !== undefined" class="shrink-0 text-xs font-medium text-neutral-500" x-text="'$' + Number(s.precio).toLocaleString('es-CL')"></span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Cantidad, precio, subtotal y quitar. --}}
                                <div class="flex items-start gap-2">
                                    <div class="w-20 sm:w-16">
                                        <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Cant.</label>
                                        <input type="number" min="1" x-model.number="r.cantidad" :name="`repuestos[${i}][cantidad]`"
                                            class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                    </div>
                                    @if ($conPrecios)
                                        <div class="flex-1 sm:w-28 sm:flex-none">
                                            <label class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Precio c/u</label>
                                            <input type="number" min="0" step="1" x-model.number="r.precio_unitario" :name="`repuestos[${i}][precio_unitario]`"
                                                placeholder="Precio"
                                                class="block w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                        </div>
                                        <div class="w-24 shrink-0 text-right text-sm text-neutral-600">
                                            <span class="mb-0.5 block text-xs text-neutral-400 sm:hidden">Subtotal</span>
                                            <span class="block sm:pt-2" x-text="clp(subtotal(r))"></span>
                                        </div>
                                    @else
                                        {{-- Garantía: el precio no se muestra, pero VIAJA igual.
                                             El guardado reemplaza los repuestos, así que un campo
                                             ausente los dejaría en $0 — y si mañana la garantía se
                                             cae (documento vencido) esos precios harían falta. --}}
                                        <input type="hidden" :name="`repuestos[${i}][precio_unitario]`" :value="r.precio_unitario ?? 0">
                                    @endif
                                    <button type="button" x-on:click="quitar(i)"
                                        class="shrink-0 self-end rounded-lg p-2 text-neutral-400 hover:bg-red-50 hover:text-red-600 sm:self-start" title="Quitar">
                                        <x-icon.trash class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>
                        </template>

                        <p x-show="repuestos.length === 0" class="py-2 text-sm text-neutral-400">
                            Sin repuestos. Usa «Agregar repuesto» y búscalos del catálogo.
                        </p>
                    </div>
                    <div class="mt-1 hidden gap-3 text-xs text-neutral-400 sm:flex">
                        <span class="flex-1">Repuesto</span>
                        <span class="w-16 text-center">Cant.</span>
                        @if ($conPrecios)
                            <span class="w-28">Precio c/u</span>
                            <span class="w-24 text-right">Subtotal</span>
                        @endif
                        <span class="w-9"></span>
                    </div>
                    @php $errBag = $errors->getMessages(); @endphp
                    @foreach ($errBag as $key => $msgs)
                        @if (\Illuminate\Support\Str::startsWith($key, 'repuestos.'))
                            <p class="mt-1 text-sm text-red-600">{{ $msgs[0] }}</p>
                        @endif
                    @endforeach
                </div>

                {{-- Mano de obra + descuento. TODO EL DINERO cuelga de $conPrecios: en
                     garantía no hay cobro, así que no hay mano de obra que fijar, ni
                     descuento que aplicar, ni total que mostrar. --}}
                @if ($conPrecios)
                <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-3">
                        {{-- Mano de obra FIJA por los trabajos marcados (no editable aquí):
                             horas a cobrar × valor hora.

                             LA NOTA ES REACTIVA y no estática de PHP: desde el 28-08 el monto
                             cambia al marcar y desmarcar chips más arriba en esta misma
                             pantalla, así que una nota escrita desde el estado GUARDADO diría
                             una cosa mientras el monto de al lado dice otra. La aritmética
                             completa (suma, tope, escritos a mano) vive junto a los chips, en
                             `_trabajo-realizado`; acá va la versión corta. --}}
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                            <p class="text-xs text-neutral-500">Mano de obra (fijada por el trabajo)</p>
                            <p class="mt-0.5 text-lg font-semibold text-neutral-900" x-text="clp(manoObra)"></p>
                            @if (! $precioHoraServicio)
                                {{-- Este SÍ es estático porque no depende de lo marcado: el SKU de
                                     la hora no tiene precio en la lista oficial, y con eso la mano
                                     de obra es $0 aunque se marque todo. Bloquea el envío. --}}
                                <p class="mt-0.5 text-xs text-brand-700">El código de hora de servicio técnico ({{ config('servicio_tecnico.sku_hora_servicio') }}) no tiene precio en la lista oficial de ventas, así que la mano de obra queda en $0.</p>
                            @else
                                <template x-if="marcados.length === 0">
                                    <p class="mt-0.5 text-xs text-brand-700">Marca arriba los trabajos que hiciste para fijar la mano de obra. Sin eso la cotización no se envía.</p>
                                </template>
                                <template x-if="marcados.length > 0">
                                    <div>
                                        <p class="mt-0.5 text-xs text-neutral-500">
                                            <span class="tabular-nums" x-text="fmtHoras(horasACobrar)"></span> h
                                            × ${{ number_format($precioHoraServicio, 0, ',', '.') }}
                                            · <span x-text="marcados.length"></span>
                                            <span x-text="marcados.length === 1 ? 'trabajo' : 'trabajos'"></span>
                                            <template x-if="topeRecorta">
                                                <span>(tope <span class="tabular-nums" x-text="fmtHoras(topeHoras)"></span> h)</span>
                                            </template>
                                        </p>
                                        <p class="mt-1 text-xs text-neutral-400">La define jefatura en «Costos generales de reparación»; el técnico no la modifica.</p>
                                    </div>
                                </template>
                            @endif
                        </div>
                        {{-- El descuento es decisión COMERCIAL: solo jefatura de ventas
                             (permiso 'aplicar descuento servicio tecnico') lo edita. El
                             técnico lo ve en solo lectura (el total ya lo refleja). --}}
                        @can('aplicar descuento servicio tecnico')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="descuento_pct" value="Descuento" />
                                    <x-select id="descuento_pct" name="descuento_pct" class="mt-1.5" x-model.number="descuentoPct">
                                        <option value="0">Sin descuento</option>
                                        @foreach (\App\Models\OrdenServicio::DESCUENTOS_PCT as $pct)
                                            <option value="{{ $pct }}">{{ $pct }}%</option>
                                        @endforeach
                                    </x-select>
                                    <x-input-error :messages="$errors->get('descuento_pct')" class="mt-2" />
                                </div>
                                <div x-show="descuentoPct > 0" x-cloak>
                                    <x-input-label for="descuento_motivo" value="Motivo *" />
                                    <x-select id="descuento_motivo" name="descuento_motivo" class="mt-1.5" x-bind:required="descuentoPct > 0">
                                        <option value="">— Selecciona —</option>
                                        @foreach (\App\Models\OrdenServicio::DESCUENTO_MOTIVOS as $val => $label)
                                            <option value="{{ $val }}" @selected(old('descuento_motivo', $orden->descuento_motivo) === $val)>{{ $label }}</option>
                                        @endforeach
                                    </x-select>
                                    <x-input-error :messages="$errors->get('descuento_motivo')" class="mt-2" />
                                </div>
                            </div>
                        @else
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm">
                                <p class="text-xs text-neutral-500">Descuento</p>
                                @if ($orden->descuento_pct > 0)
                                    <p class="mt-0.5 font-medium text-neutral-900">{{ $orden->descuento_pct }}%
                                        <span class="text-neutral-400">({{ $orden->descuento_motivo_label }})</span></p>
                                @else
                                    <p class="mt-0.5 text-neutral-500">Sin descuento.</p>
                                @endif
                                <p class="mt-1 text-xs text-neutral-400">Solo jefatura de ventas aplica descuentos.</p>
                            </div>
                        @endcan
                    </div>
                    <div class="flex flex-col justify-end">
                        <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
                            <p class="text-sm text-neutral-600">Costo total a pagar (IVA incluido)</p>
                            <p class="mt-0.5 text-2xl font-semibold text-neutral-900" x-text="clp(total)"></p>
                            {{-- Desglose de IVA en vivo: el total ya viene con IVA → neto = total/1,19. --}}
                            <div class="mt-1.5 space-y-0.5 border-t border-brand-200 pt-1.5 text-xs text-neutral-600">
                                <div class="flex justify-between"><span>Neto</span><span x-text="clp(Math.round(total / 1.19))"></span></div>
                                <div class="flex justify-between"><span>IVA (19%)</span><span x-text="clp(total - Math.round(total / 1.19))"></span></div>
                            </div>
                            <p class="mt-1.5 text-xs text-neutral-500">
                                Repuestos <span x-text="clp(totalRepuestos)"></span> + mano de obra.
                            </p>
                            <p x-show="descuentoPct > 0" x-cloak class="mt-1 text-xs font-medium text-brand-700">
                                Descuento <span x-text="descuentoPct"></span>%: −<span x-text="clp(descuentoMonto)"></span>
                                <span class="text-neutral-400">· subtotal <span x-text="clp(costoBruto)"></span></span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Advertencia de gasto: si la reparación supera el 40% del
                     valor del equipo (como la "pérdida total" de los autos).
                     No bloquea: avisa para consultar al cliente. --}}
                @if ($precioVentaEquipo)
                    @php $umbralAlto = (int) round($precioVentaEquipo * \App\Models\OrdenServicio::UMBRAL_REPARACION_ALTA); @endphp
                    <div x-show="total > {{ $umbralAlto }}" x-cloak class="mt-4 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-700">
                        <p class="font-semibold">⚠️ Costo de reparación alto</p>
                        <p class="mt-0.5">
                            El total (<span x-text="clp(total)"></span>) es el
                            <span class="font-semibold" x-text="Math.round(total / {{ $precioVentaEquipo }} * 100)"></span>%
                            del valor del equipo ({{ $clp($precioVentaEquipo) }}) y supera el 40%.
                            <span class="font-medium">Consulta con el cliente</span> si le conviene reparar o cambiar el equipo antes de continuar.
                        </p>
                    </div>
                @endif
                @endif {{-- $conPrecios --}}
