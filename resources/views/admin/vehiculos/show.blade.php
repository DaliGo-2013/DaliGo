<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="$vehiculo->ppu"
                       :subtitle="collect([$vehiculo->alias, $vehiculo->marca_modelo, $vehiculo->anio])->filter()->implode(' · ') ?: $vehiculo->tipo_label"
                       :back="route('admin.vehiculos.index')" backTitle="Volver a vehículos">
            @can('manage vehiculos')
                <x-slot name="action">
                    <x-button-link :href="route('admin.vehiculos.edit', $vehiculo)">
                        <x-icon.pencil class="h-4 w-4" />
                        Editar
                    </x-button-link>
                </x-slot>
            @endcan
        </x-page-header>
    </x-slot>

    <div class="space-y-5 py-8">
        <x-status-alert :status="session('status')" />

        @unless ($vehiculo->es_activo)
            {{-- Fuera de la flota: se dice PRIMERO, porque cambia cómo leer todo
                 lo de abajo (un documento vencido en un vehículo vendido no es un
                 problema). En la planilla esto vivía escrito a mano en la columna
                 del conductor. --}}
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-sm text-neutral-600 sm:p-4">
                <span class="font-medium text-neutral-900">{{ $vehiculo->estado_label }}.</span>
                {{ $vehiculo->baja_motivo }}@if ($vehiculo->baja_at) · {{ $vehiculo->baja_at->format('d-m-Y') }}@endif
            </div>
        @endunless

        {{-- Documentos: el corazón de la ficha. Es lo que en la planilla son las
             celdas pintadas a mano.

             RESPALDO DIGITAL (pedido del dueño 11-08): cada documento puede llevar
             la foto del papel, comprimida por el servidor a ~100-250 KB, para que
             el conductor la muestre desde el teléfono si lo controlan en ruta.
             «Ver» lo tiene cualquiera con acceso a la flota (el conductor
             incluido); subir es de quien gestiona. El input de archivo se envía
             SOLO (onchange): elegir la foto en el teléfono ya es la acción — un
             segundo botón de «enviar» sería un paso más parado en la vereda. --}}
        @php
            $respaldos = $vehiculo->respaldos->sortByDesc('id')->groupBy('documento');
        @endphp
        <x-seccion titulo="Documentos y vencimientos">
            <ul role="list" class="divide-y divide-neutral-100">
                @foreach ($vehiculo->documentos() as $doc)
                    @php $respaldo = $respaldos->get($doc['clave'])?->first(); @endphp
                    <li class="py-2.5 first:pt-0 last:pb-0">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $doc['label'] }}</p>
                                <p class="text-xs text-neutral-500">
                                    @if ($doc['estado'] === \App\Models\Vehiculo::DOC_NO_APLICA)
                                        No aplica a un {{ mb_strtolower($vehiculo->tipo_label) }}
                                    @elseif ($doc['vence'])
                                        {{ $doc['vence']->format('d-m-Y') }}
                                    @else
                                        Sin fecha cargada
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <x-badge :variant="\App\Models\Vehiculo::variante($doc['estado'])">
                                    {{ \App\Models\Vehiculo::estadoDocumentalLabel($doc['estado']) }}
                                </x-badge>
                                @if ($doc['dias'] !== null)
                                    <p class="mt-0.5 text-xs text-neutral-500">{{ \App\Models\Vehiculo::plazoLabel($doc['dias']) }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($doc['estado'] !== \App\Models\Vehiculo::DOC_NO_APLICA)
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                @if ($respaldo)
                                    <a href="{{ route('admin.vehiculos.documentos.show', [$vehiculo, $doc['clave']]) }}"
                                       class="min-h-8 inline-flex items-center gap-1 font-medium text-brand-700 hover:text-brand-600">
                                        Ver el documento
                                        <span class="font-normal text-neutral-400">· {{ $respaldo->tamano_kb }} KB</span>
                                    </a>
                                @endif
                                @can('manage vehiculos')
                                    {{-- <x-archivo-input> y NO un input nativo: el navegador
                                         recorta su rótulo y en 375 px no se entiende (candado
                                         ArchivoInputTest). `capture="environment"` abre la
                                         cámara de atrás directo, que es como se saca la foto
                                         del papel. Se envía SOLO al elegir el archivo: en el
                                         teléfono, elegir la foto YA es la acción — un segundo
                                         botón de «enviar» sería un paso más de más. --}}
                                    <form method="POST" enctype="multipart/form-data" class="w-full sm:w-64"
                                          action="{{ route('admin.vehiculos.documentos.store', [$vehiculo, $doc['clave']]) }}">
                                        @csrf
                                        <x-archivo-input name="archivo" required
                                                         accept="image/jpeg,image/png,image/webp,application/pdf"
                                                         capture="environment"
                                                         :texto="$respaldo ? 'Reemplazar el documento' : 'Subir el documento'"
                                                         vacio="Se comprime solo, queda liviano"
                                                         @change="$el.form.submit()" />
                                    </form>
                                @endcan
                                @unless ($respaldo || auth()->user()->can('manage vehiculos'))
                                    <span class="text-neutral-400">Sin respaldo digital</span>
                                @endunless
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @error('archivo')
                <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            @if ($vehiculo->es_activo)
                <p class="text-xs text-neutral-400">
                    El aviso sale solo: {{ \App\Models\Vehiculo::DIAS_AVISO }} días antes del vencimiento y el día que vence.
                </p>
            @endif
        </x-seccion>

        <x-seccion titulo="Asignación">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-neutral-400">Base</dt>
                    <dd class="text-sm text-neutral-900">{{ $vehiculo->base ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Conductor asignado</dt>
                    <dd class="text-sm text-neutral-900">{{ $vehiculo->conductor_nombre ?: 'Sin asignar' }}</dd>
                </div>
                @if ($vehiculo->extintor_capacidad_kg)
                    <div>
                        <dt class="text-xs text-neutral-400">Capacidad del extintor</dt>
                        <dd class="text-sm text-neutral-900">{{ rtrim(rtrim(number_format((float) $vehiculo->extintor_capacidad_kg, 1, ',', '.'), '0'), ',') }} kg</dd>
                    </div>
                @endif
            </dl>
        </x-seccion>

        <x-seccion titulo="Identificación">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-neutral-400">Patente</dt>
                    <dd class="font-mono text-sm text-neutral-900">{{ $vehiculo->ppu }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Tipo</dt>
                    <dd class="text-sm text-neutral-900">{{ $vehiculo->tipo_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Marca y modelo</dt>
                    <dd class="text-sm text-neutral-900">{{ $vehiculo->marca_modelo ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Año</dt>
                    <dd class="text-sm text-neutral-900">{{ $vehiculo->anio ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">Combustible</dt>
                    <dd class="text-sm text-neutral-900">{{ $vehiculo->combustible_label ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">VIN / chasis</dt>
                    <dd class="break-all font-mono text-xs text-neutral-900">{{ $vehiculo->vin ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-neutral-400">N° de motor</dt>
                    <dd class="break-all font-mono text-xs text-neutral-900">{{ $vehiculo->numero_motor ?: '—' }}</dd>
                </div>
            </dl>
        </x-seccion>

        @if ($vehiculo->cilindrada || $vehiculo->pbv_kg || $vehiculo->capacidad_carga_kg || $vehiculo->presion_psi)
            <x-seccion titulo="Dimensiones y capacidades">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                    @foreach ([
                        'Cilindrada' => $vehiculo->cilindrada ? $vehiculo->cilindrada.' cc' : null,
                        'PBV' => $vehiculo->pbv_kg ? number_format($vehiculo->pbv_kg, 0, ',', '.').' kg' : null,
                        'Capacidad de carga' => $vehiculo->capacidad_carga_kg ? number_format($vehiculo->capacidad_carga_kg, 0, ',', '.').' kg' : null,
                        'Presión' => $vehiculo->presion_psi ? $vehiculo->presion_psi.' PSI' : null,
                    ] as $label => $valor)
                        @if ($valor)
                            <div>
                                <dt class="text-xs text-neutral-400">{{ $label }}</dt>
                                <dd class="text-sm tabular-nums text-neutral-900">{{ $valor }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </x-seccion>
        @endif

        @if ($vehiculo->observaciones)
            <x-seccion titulo="Observaciones">
                <p class="whitespace-pre-line text-sm text-neutral-700">{{ $vehiculo->observaciones }}</p>
            </x-seccion>
        @endif
    </div>
</x-app-layout>
