<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Nueva hoja de ruta" subtitle="Elige los documentos que salen en este viaje: nada se tipea. El folio se asigna solo."
                       :back="route('admin.hojas-ruta.index')" backTitle="Volver a hojas de ruta" />
    </x-slot>

    <div class="py-8 sm:py-12">
        <div>
            <form method="POST" action="{{ route('admin.hojas-ruta.store') }}" class="space-y-6" data-una-vez>
                @csrf

                <x-seccion titulo="El viaje">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="sucursal_id" value="Sucursal *" />
                            <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5" required>
                                <option value="">Elige…</option>
                                @foreach ($sucursales as $sucursal)
                                    <option value="{{ $sucursal->id }}" @selected(old('sucursal_id') == $sucursal->id)>{{ $sucursal->nombre }}</option>
                                @endforeach
                            </x-select>
                            <x-input-hint>El camión vuelve a su sucursal; sin rutas mixtas.</x-input-hint>
                            <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="zona_id" value="Zona *" />
                            <x-select id="zona_id" name="zona_id" class="mt-1.5" required>
                                <option value="">Elige…</option>
                                @foreach ($zonas as $zona)
                                    <option value="{{ $zona->id }}" @selected(old('zona_id') == $zona->id)>{{ $zona->nombre }}</option>
                                @endforeach
                            </x-select>
                            <x-input-hint>La hoja se arma por zona; el vendedor no es fijo.</x-input-hint>
                            <x-input-error :messages="$errors->get('zona_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="vehiculo_id" value="Vehículo *" />
                            <x-select id="vehiculo_id" name="vehiculo_id" class="mt-1.5" required>
                                <option value="">Elige de la flota…</option>
                                @foreach ($vehiculos as $vehiculo)
                                    <option value="{{ $vehiculo->id }}" @selected(old('vehiculo_id') == $vehiculo->id)>{{ $vehiculo->nombre }}</option>
                                @endforeach
                            </x-select>
                            <x-input-hint>Solo la flota activa. La patente queda registrada en la hoja.</x-input-hint>
                            <x-input-error :messages="$errors->get('vehiculo_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="conductor_id" value="Conductor *" />
                            <x-select id="conductor_id" name="conductor_id" class="mt-1.5" required>
                                <option value="">Elige…</option>
                                @foreach ($conductores as $conductor)
                                    <option value="{{ $conductor->id }}" @selected(old('conductor_id') == $conductor->id)>{{ $conductor->name }}</option>
                                @endforeach
                            </x-select>
                            <x-input-hint>Solo este conductor podrá entregar la carga de la hoja.</x-input-hint>
                            <x-input-error :messages="$errors->get('conductor_id')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="peoneta_nombre" value="Peoneta (opcional)" />
                        <x-text-input id="peoneta_nombre" name="peoneta_nombre" type="text" class="mt-1.5 block w-full"
                                      :value="old('peoneta_nombre')" placeholder="Nombre del acompañante, si va" />
                        <x-input-hint>Si va, su nombre queda por seguridad y el bono se parte a medias.</x-input-hint>
                        <x-input-error :messages="$errors->get('peoneta_nombre')" class="mt-2" />
                    </div>
                </x-seccion>

                <x-seccion titulo="Las paradas">
                    <p class="text-sm text-neutral-600">
                        Marca los documentos que van en este viaje y cómo se cobra cada uno.
                        Sin marcar «Pagado», el chofer cobra en la puerta.
                    </p>
                    <x-input-error :messages="$errors->get('documentos')" class="mt-2" />

                    <ul class="divide-y divide-neutral-100">
                        @forelse ($documentos as $doc)
                            <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:gap-4">
                                <label class="flex min-h-12 flex-1 cursor-pointer items-center gap-3">
                                    <x-checkbox name="documentos[]" value="{{ $doc->id }}"
                                                :checked="in_array($doc->id, old('documentos', []))" />
                                    <span class="text-sm">
                                        <span class="font-medium text-neutral-900">Folio {{ $doc->folio ?? $doc->bsale_document_id }}</span>
                                        <span class="text-neutral-500">
                                            · {{ $doc->cliente?->razon_social ?? 'Sin cliente' }}
                                            · ${{ number_format((float) $doc->total, 0, ',', '.') }}
                                        </span>
                                    </span>
                                </label>
                                <x-select name="cobros[{{ $doc->id }}]" class="sm:w-44" aria-label="Cobro del folio {{ $doc->folio }}">
                                    <option value="cobrar_en_entrega">Cobrar en entrega</option>
                                    <option value="pagado" @selected(old("cobros.$doc->id") === 'pagado')>Pagado</option>
                                    <option value="credito" @selected(old("cobros.$doc->id") === 'credito')>Crédito</option>
                                </x-select>
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-neutral-500">
                                No hay documentos disponibles para despachar.
                            </li>
                        @endforelse
                    </ul>
                    <x-input-hint>Se listan los últimos documentos espejados sin hoja. Un DTE anulado en Bsale se rechaza al crear.</x-input-hint>
                </x-seccion>

                <x-form-footer>
                    <x-primary-button>Crear hoja de ruta</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
