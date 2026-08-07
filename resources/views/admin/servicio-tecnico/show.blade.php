<x-app-layout ancho="formulario">
    @php
        $clp = fn ($n) => '$'.number_format((int) $n, 0, ',', '.');
        $tieneReparacion = $orden->trabajo_realizado || $orden->repuestos->isNotEmpty()
            || $orden->mano_obra || $orden->descuento_pct || $orden->fecha_aviso || $orden->fecha_retiro || $orden->causa_falla;
        $esGarantia = $orden->condicion_efectiva === 'garantia';
        $esReparacion = ! $esGarantia;
        // En Coquimbo y Abate Molina se RECIBE pero no se repara: la reparacion es
        // en Mirador (casa matriz). Se rotula cuando la recepcion fue en otra sucursal.
        $reparaEnMatriz = $orden->sucursal && ! $orden->sucursal->es_central && $sucursalCentral;
    @endphp

    <x-slot name="header">
        <x-page-header :title="'Orden '.$orden->folio" :subtitle="$orden->cliente_nombre"
                       :back="route('admin.servicio-tecnico.index')" backTitle="Volver al listado">
            <x-slot name="action">
                <div class="flex items-center gap-2">
                    @can('manage servicio tecnico')
                        <x-icon-button :href="route('admin.servicio-tecnico.reparacion', $orden)" size="lg" variant="secondary" label="Reparación" title="Reparación (taller)">
                            <x-icon.wrench-screwdriver class="h-5 w-5" />
                        </x-icon-button>
                    @endcan
                    @can('editar recepcion servicio tecnico')
                        <x-icon-button :href="route('admin.servicio-tecnico.edit', $orden)" size="lg" variant="primary" label="Editar" title="Editar recepción">
                            <x-icon.pencil class="h-5 w-5" />
                        </x-icon-button>
                    @endcan
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-12">

        {{-- Etapas de la orden: recepción (esta ficha) · cotización · parte del técnico. --}}
        @include('admin.servicio-tecnico._tabs', ['activa' => 'recepcion'])

        <x-status-alert :status="session('status')" />

        {{-- Por confirmar: llego por QR y aun no se autoriza la recepcion. --}}
        @can('confirmar servicio tecnico')
            @if ($orden->por_confirmar)
                {{-- El aviso distingue el ORIGEN: por_confirmar es true para el QR del
                     cliente Y para el retiro en ruta del conductor, y decirle «el
                     cliente lo envió desde su celular» a una máquina que trajo el
                     conductor manda a revisar la pantalla equivocada. --}}
                @php $esRetiroEnRuta = $orden->fuente === \App\Models\OrdenServicio::FUENTE_RUTA; @endphp
                <div class="flex flex-col gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-brand-700">
                            {{ $esRetiroEnRuta ? 'Retiro en ruta — por confirmar' : 'Ingreso por QR — por confirmar' }}
                        </h3>
                        <p class="mt-0.5 text-sm text-brand-700">
                            @if ($esRetiroEnRuta)
                                La trajo el conductor desde la ruta. Revisa la máquina al llegar y confirma la recepción.
                            @else
                                El cliente lo envió desde su celular. Revisa los datos con el equipo físico y confirma la recepción.
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.servicio-tecnico.confirmar', $orden) }}"
                          class="shrink-0"
                          onsubmit="return confirm('¿Confirmar la recepción de la orden {{ $orden->folio }}? Se le enviará el detalle al cliente por correo.');">
                        @csrf
                        <x-primary-button>Confirmar recepción</x-primary-button>
                    </form>
                </div>
            @endif
        @endcan

        {{-- Estado + condicion --}}
        <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
            <span class="text-sm text-neutral-500">Estado actual:</span>
            <x-badge :variant="$orden->estado_variante">{{ \Illuminate\Support\Str::headline($orden->estado) }}</x-badge>
            <x-badge variant="neutral">{{ $esGarantia ? 'Garantía' : 'Reparación' }}</x-badge>
        </div>

        {{-- Cliente --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
            <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Cliente</h3>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div><dt class="text-xs text-neutral-400">Nombre</dt><dd class="text-sm text-neutral-900">{{ $orden->cliente_nombre }}</dd></div>
                <div><dt class="text-xs text-neutral-400">RUT</dt><dd class="text-sm text-neutral-900">{{ $orden->cliente_rut }}</dd></div>
                <div><dt class="text-xs text-neutral-400">Teléfono</dt><dd class="text-sm text-neutral-900">{{ $orden->cliente_telefono ?: '—' }}</dd></div>
                {{-- El correo es el dato del que dependen la cotización y los avisos:
                     sin él, la pestaña Cotización se bloquea con «la orden no tiene
                     correo del cliente». Antes solo se veía DENTRO de una cotización
                     ya enviada, o sea justo cuando ya era tarde. --}}
                <div>
                    <dt class="text-xs text-neutral-400">Correo</dt>
                    <dd class="text-sm {{ $orden->cliente_email ? 'text-neutral-900' : 'text-red-600' }}">
                        {{ $orden->cliente_email ?: 'Falta — sin correo no se puede cotizar' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Equipo --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
            <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Equipo</h3>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div><dt class="text-xs text-neutral-400">Tipo</dt><dd class="text-sm text-neutral-900">{{ $orden->tipo_equipo_label }}</dd></div>
                <div><dt class="text-xs text-neutral-400">Código (producto Dali)</dt><dd class="text-sm text-neutral-900">{{ $orden->producto ? $orden->producto->sku.' — '.$orden->producto->nombre : '—' }}</dd></div>
                {{-- Lo que el CLIENTE escribió del equipo. Se le pide en el QR (y es
                     obligatorio en el ingreso por cantidad) y no se mostraba en
                     ninguna pantalla del taller: el técnico trabajaba sin saber qué
                     declaró el dueño de la máquina. --}}
                <div>
                    <dt class="text-xs text-neutral-400">Equipo según el cliente</dt>
                    <dd class="text-sm text-neutral-900">{{ $orden->modelo ?: '—' }}</dd>
                </div>
                <div><dt class="text-xs text-neutral-400">N° de serie</dt><dd class="text-sm text-neutral-900">{{ $orden->numero_serie ?: '—' }}</dd></div>
                <div>
                    <dt class="text-xs text-neutral-400">Sucursal/Ruta</dt>
                    <dd class="text-sm text-neutral-900">
                        {{ $orden->sucursal?->nombre ?: ($orden->ruta ? 'Ruta · '.$orden->ruta : '—') }}
                        @if ($reparaEnMatriz)
                            <span class="mt-0.5 block text-xs text-neutral-500">Se repara en {{ $sucursalCentral->nombre }} (casa matriz)</span>
                        @elseif ($orden->sucursal?->es_central)
                            <span class="mt-0.5 block text-xs text-neutral-500">Recepción y reparación (casa matriz)</span>
                        @endif
                        {{-- Dónde está FÍSICAMENTE la máquina. Antes la ficha decía
                             «se repara en Mirador» sin decir si ya había llegado:
                             entre la recepción en sucursal y el taller no había
                             ningún dato (pedido del dueño 03-08). --}}
                        @if ($orden->en_transito)
                            <span class="mt-1 block text-xs font-medium text-amber-700">
                                {{ $orden->motivo_no_llego }}
                            </span>
                        @elseif ($orden->traslado)
                            <span class="mt-1 block text-xs text-neutral-500">
                                Llegó al taller el {{ $orden->traslado_recibida_at?->enChile()->format('d-m-Y') }}
                                · <a href="{{ route('admin.traslados.show', $orden->traslado) }}" class="text-brand-600 hover:underline">traslado {{ $orden->traslado->codigo }}</a>
                            </span>
                        @endif
                    </dd>
                </div>
                <div><dt class="text-xs text-neutral-400">Fecha de ingreso</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_ingreso?->format('d-m-Y') ?: '—' }}</dd></div>
                <div><dt class="text-xs text-neutral-400">Fecha de entrega (estimada)</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_entrega?->format('d-m-Y') ?: '—' }}</dd></div>
                <div><dt class="text-xs text-neutral-400">Recibido por</dt><dd class="text-sm text-neutral-900">{{ $orden->recibida_por ?: '—' }}</dd></div>
                {{-- Falla reportada: dentro de Equipo (a ancho completo). --}}
                <div class="border-t border-neutral-100 pt-3 sm:col-span-2">
                    <dt class="text-xs text-neutral-400">Falla reportada</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-neutral-900">{{ $orden->falla_reportada ?: '—' }}</dd>
                </div>
                @if ($orden->falla_tecnico)
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-neutral-400">Condiciones de entrega</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-sm text-neutral-900">{{ $orden->falla_tecnico }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Retiro en ruta: quién la trajo y de dónde. Se captura al cargar el lote
             (conductor + ciudad de origen) y no se veía en ninguna pantalla: si algo
             venía mal, nadie sabía a quién preguntarle. --}}
        @if ($orden->lote)
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Retiro en ruta</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-3">
                    <div><dt class="text-xs text-neutral-400">Conductor</dt><dd class="text-sm text-neutral-900">{{ $orden->lote->conductor_nombre ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Ciudad de origen</dt><dd class="text-sm text-neutral-900">{{ $orden->lote->origen_ciudad ?: '—' }}</dd></div>
                    <div>
                        <dt class="text-xs text-neutral-400">Máquinas del lote</dt>
                        <dd class="text-sm text-neutral-900">{{ $orden->lote->total_ordenes }}</dd>
                    </div>
                </dl>
            </div>
        @endif

        @include('admin.servicio-tecnico._fotos')

        {{-- Garantia (solo si esta vigente; si vencio se trata como reparacion) --}}
        @if ($esGarantia)
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
                <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Documento de garantía</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-3">
                    <div><dt class="text-xs text-neutral-400">Documento</dt><dd class="text-sm text-neutral-900">{{ $orden->garantia_doc_tipo ? ucfirst($orden->garantia_doc_tipo) : '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">N° de documento</dt><dd class="text-sm text-neutral-900">{{ $orden->garantia_doc_numero ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Fecha de compra</dt><dd class="text-sm text-neutral-900">{{ $orden->garantia_doc_fecha?->format('d-m-Y') ?: '—' }}</dd></div>
                </dl>
            </div>
        @endif

        {{-- Reparacion (taller) --}}
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Reparación (taller)</h3>
                @can('manage servicio tecnico')
                    <a href="{{ route('admin.servicio-tecnico.reparacion', $orden) }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">Editar →</a>
                @endcan
            </div>

            @unless ($tieneReparacion)
                <p class="text-sm text-neutral-400">Aún sin registro de reparación.</p>
            @else
                @if ($orden->trabajo_realizado)
                    <div class="mb-4">
                        <dt class="text-xs text-neutral-400">Trabajo realizado</dt>
                        <dd class="whitespace-pre-line text-sm text-neutral-900">{{ $orden->trabajo_realizado }}</dd>
                    </div>
                @endif

                @if ($orden->causa_falla)
                    <div class="mb-4">
                        <dt class="text-xs text-neutral-400">Causa de la falla</dt>
                        <dd class="text-sm text-neutral-900">{{ $orden->causa_falla_label }}</dd>
                    </div>
                @endif

                @if ($orden->es_propia && $orden->categoria)
                    <div class="mb-4">
                        <dt class="text-xs text-neutral-400">Categoría (reventa)</dt>
                        <dd class="text-sm text-neutral-900">{{ $orden->categoria_label }}</dd>
                    </div>
                @endif

                @if ($orden->repuestos->isNotEmpty())
                    <div class="mb-4">
                        <dt class="mb-1 text-xs text-neutral-400">Repuestos</dt>
                        <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200 text-sm">
                            @foreach ($orden->repuestos as $r)
                                <li class="flex items-center justify-between px-3 py-2">
                                    <span class="text-neutral-900">{{ $r->nombre }} <span class="text-neutral-400">× {{ $r->cantidad }}</span></span>
                                    @if ($esReparacion)
                                        <span class="text-neutral-600">{{ $clp($r->subtotal) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    @if ($esReparacion)
                        <div><dt class="text-xs text-neutral-400">Mano de obra</dt><dd class="text-sm text-neutral-900">{{ $clp($orden->mano_obra ?? 0) }}</dd></div>
                        @if ($orden->descuento_pct > 0)
                            <div>
                                <dt class="text-xs text-neutral-400">Descuento</dt>
                                <dd class="text-sm text-neutral-900">{{ $orden->descuento_pct }}% · −{{ $clp($orden->descuento_monto) }}
                                    <span class="text-neutral-400">({{ $orden->descuento_motivo_label }})</span></dd>
                            </div>
                        @endif
                        {{-- Desglose de IVA (los precios ya vienen con IVA): neto + IVA = total. --}}
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-neutral-400">Costo (IVA incluido)</dt>
                            <dd class="mt-1 space-y-0.5 text-sm">
                                <div class="flex justify-between"><span class="text-neutral-500">Neto</span><span class="text-neutral-900">{{ $clp($orden->costo_neto) }}</span></div>
                                <div class="flex justify-between"><span class="text-neutral-500">IVA (19%)</span><span class="text-neutral-900">{{ $clp($orden->costo_iva) }}</span></div>
                                <div class="flex justify-between border-t border-neutral-100 pt-0.5 font-semibold text-neutral-900"><span>Total con IVA</span><span>{{ $clp($orden->costo_total) }}</span></div>
                            </dd>
                        </div>
                    @endif
                    <div><dt class="text-xs text-neutral-400">Fecha de aviso al cliente</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_aviso?->format('d-m-Y') ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-neutral-400">Fecha de retiro</dt><dd class="text-sm text-neutral-900">{{ $orden->fecha_retiro?->format('d-m-Y') ?: '—' }}</dd></div>
                </dl>

                {{-- Advertencia de gasto: reparación > 40% del valor del equipo. --}}
                @if ($esReparacion && ($precioVentaEquipo ?? null) && $orden->costo_total > $precioVentaEquipo * \App\Models\OrdenServicio::UMBRAL_REPARACION_ALTA)
                    <div class="mt-3 rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-700">
                        <p class="font-semibold">⚠️ Costo de reparación alto</p>
                        <p class="mt-0.5">
                            El total ({{ $clp($orden->costo_total) }}) es el {{ round($orden->costo_total / $precioVentaEquipo * 100) }}% del valor del equipo ({{ $clp($precioVentaEquipo) }}) y supera el 40%. Conviene consultar al cliente si le conviene reparar o cambiar el equipo.
                        </p>
                    </div>
                @endif
            @endunless
        </div>

        {{-- Cotizaciones enviadas al cliente: la ruta completa visible para
             todo el que ve la orden (transparencia pedida por el dueño). --}}
        @php
            $dgCotizaciones = $orden->cotizaciones()->latest('id')->get();
            // Cotización ACEPTADA por el cliente (candidata a autorizar / mostrar el pago).
            $dgAceptada = $dgCotizaciones->firstWhere('estado', 'aceptada');
        @endphp
        @if ($dgCotizaciones->isNotEmpty())
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Cotizaciones al cliente</h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($dgCotizaciones as $c)
                        <li class="flex flex-wrap items-center gap-2 text-sm">
                            <x-badge :variant="$c->estado_variante">{{ $c->estado_label }}</x-badge>
                            <span class="text-neutral-600">
                                {{ $c->created_at->format('d-m-Y H:i') }} · ${{ number_format((int) $c->costo_total, 0, ',', '.') }}
                                · a {{ $c->cliente_email }}@if ($c->respondida_at) · respondida el {{ $c->respondida_at->format('d-m-Y H:i') }}@endif
                            </span>
                            {{-- El «¿por qué?» que escribió el cliente al responder (dueño 06-08). --}}
                            @if (filled($c->respuesta_motivo))
                                <span class="w-full pl-1 text-xs italic text-neutral-500">Motivo del cliente: «{{ $c->respuesta_motivo }}»</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                {{-- Pago + autorización de la reparación (cotización aceptada). --}}
                @if ($dgAceptada)
                    <div class="mt-4 border-t border-neutral-100 pt-4">
                        @if ($dgAceptada->esta_autorizada)
                            {{-- Ya autorizada: info del pago visible para todo el equipo. --}}
                            <div class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-700">
                                <p class="font-semibold text-brand-700">Reparación autorizada</p>
                                <p class="mt-0.5">
                                    {{ $dgAceptada->pago_forma_label }}
                                    · autorizó {{ $dgAceptada->autorizadaPor?->name ?? '—' }}
                                    · {{ $dgAceptada->autorizada_at?->format('d-m-Y H:i') }}
                                </p>
                                @if (filled($dgAceptada->pago_nota))<p class="mt-0.5 text-brand-700">“{{ $dgAceptada->pago_nota }}”</p>@endif
                                @if ($dgAceptada->pago_comprobante_ruta)
                                    <a href="{{ route('admin.servicio-tecnico.cotizacion.comprobante', $dgAceptada) }}" target="_blank"
                                       class="mt-1 inline-block font-medium text-brand-600 underline">Ver comprobante de pago</a>
                                @endif
                            </div>
                        @elseif (auth()->user()->can('autorizar reparacion'))
                            {{-- Pendiente de autorizar: ventas coordina el pago y autoriza. --}}
                            <p class="text-sm font-medium text-neutral-700">El cliente aceptó. Coordina el pago y autoriza la reparación:</p>
                            <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.autorizar', $orden) }}"
                                  enctype="multipart/form-data" class="mt-3 space-y-3" data-una-vez>
                                @csrf
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="pago_forma">Forma de pago <span class="text-red-500">*</span></x-input-label>
                                        <x-select id="pago_forma" name="pago_forma" class="mt-1.5" required>
                                            <option value="">— Selecciona —</option>
                                            @foreach (\App\Models\OrdenServicioCotizacion::FORMAS_PAGO as $val => $et)
                                                <option value="{{ $val }}" @selected(old('pago_forma') === $val)>{{ $et }}</option>
                                            @endforeach
                                        </x-select>
                                        <x-input-error :messages="$errors->get('pago_forma')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="comprobante" value="Comprobante (opcional)" />
                                        <x-archivo-input id="comprobante" name="comprobante" accept="image/*" class="mt-1.5"
                                            texto="Elegir o fotografiar el comprobante"
                                            vacio="Sin comprobante adjunto" />
                                        <x-input-hint>Imagen de la transferencia, si el cliente la envió.</x-input-hint>
                                        <x-input-error :messages="$errors->get('comprobante')" class="mt-2" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="pago_nota" value="Nota (opcional)" />
                                    <x-textarea id="pago_nota" name="pago_nota" rows="2" class="mt-1.5" maxlength="1000"
                                                placeholder="Ej. pagó 50% ahora, resto al retiro">{{ old('pago_nota') }}</x-textarea>
                                </div>
                                <div>
                                    <x-primary-button>Autorizar reparación</x-primary-button>
                                    <span class="ml-2 text-xs text-neutral-400">Avisa al técnico para proceder. La info del pago la verá todo el equipo.</span>
                                </div>
                            </form>
                        @else
                            <p class="text-sm text-neutral-500">El cliente aceptó. Pendiente de que ventas coordine el pago y autorice la reparación.</p>
                        @endif
                    </div>
                @endif

                {{-- El otro desenlace: la ÚLTIMA cotización fue rechazada → avisar al
                     cliente que pase a retirar sin reparar (dueño 06-08). Solo la
                     última: si después se envió otra, la conversación sigue abierta. --}}
                @php $dgUltima = $dgCotizaciones->first(); @endphp
                @if (! $dgAceptada && $dgUltima && $dgUltima->estado === 'rechazada')
                    <div class="mt-4 border-t border-neutral-100 pt-4">
                        @if ($dgUltima->retiro_avisado_at)
                            <div class="rounded-xl bg-neutral-50 px-4 py-3 text-sm text-neutral-600">
                                <p class="font-semibold text-neutral-700">Retiro avisado</p>
                                <p class="mt-0.5">
                                    A {{ $dgUltima->cliente_email }} se le avisó el {{ $dgUltima->retiro_avisado_at->format('d-m-Y H:i') }}
                                    que puede pasar a retirar su equipo sin reparar
                                    (avisó {{ $dgUltima->retiroAvisadoPor?->name ?? '—' }}).
                                </p>
                            </div>
                        @elseif (auth()->user()->can('autorizar reparacion'))
                            <p class="text-sm font-medium text-neutral-700">El cliente no aceptó la reparación. Avísale por correo que puede pasar a retirar su equipo:</p>
                            <form method="POST" action="{{ route('admin.servicio-tecnico.cotizacion.avisar-retiro', [$orden, $dgUltima->id]) }}"
                                  class="mt-3" data-una-vez
                                  onsubmit="return confirm('Se le enviará a {{ $dgUltima->cliente_email }} un correo avisando que puede pasar a retirar su equipo sin reparar. ¿Continuar?');">
                                @csrf
                                <x-primary-button type="submit">Avisar: pasar a retirar</x-primary-button>
                                <span class="ml-2 text-xs text-neutral-400">Carta cortés, sin montos. Queda registrado quién avisó.</span>
                            </form>
                        @else
                            <p class="text-sm text-neutral-500">El cliente no aceptó. Pendiente de avisarle que puede pasar a retirar su equipo.</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

    </div>
</x-app-layout>
