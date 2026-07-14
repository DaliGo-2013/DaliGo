<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Nuevo despacho" subtitle="Elige el documento de venta y asigna zona y conductor. El documento se verifica contra Bsale al crear." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.despachos.store') }}"
                  class="space-y-6 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                @csrf

                <div>
                    <x-input-label for="documento_venta_id" value="Documento de venta *" />
                    <x-select id="documento_venta_id" name="documento_venta_id" class="mt-1.5" required>
                        <option value="">Elige un documento…</option>
                        @foreach ($documentos as $doc)
                            <option value="{{ $doc->id }}" @selected(old('documento_venta_id') == $doc->id)>
                                Folio {{ $doc->folio ?? $doc->bsale_document_id }}
                                · {{ $doc->cliente?->razon_social ?? 'Sin cliente' }}
                                · ${{ number_format((float) $doc->total, 0, ',', '.') }}
                                · {{ $doc->emitido_at?->format('d-m-Y') ?? 's/f' }}
                            </option>
                        @endforeach
                    </x-select>
                    <x-input-hint>Se listan los últimos documentos espejados sin despacho. Un DTE anulado en Bsale se rechaza al crear.</x-input-hint>
                    <x-input-error :messages="$errors->get('documento_venta_id')" class="mt-2" />
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <x-input-label for="zona_id" value="Zona" />
                        <x-select id="zona_id" name="zona_id" class="mt-1.5">
                            <option value="">Del cliente (automática)</option>
                            @foreach ($zonas as $zona)
                                <option value="{{ $zona->id }}" @selected(old('zona_id') == $zona->id)>{{ $zona->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-hint>Vacío = hereda la zona efectiva del cliente.</x-input-hint>
                        <x-input-error :messages="$errors->get('zona_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="conductor_id" value="Conductor" />
                        <x-select id="conductor_id" name="conductor_id" class="mt-1.5">
                            <option value="">Sin asignar</option>
                            @foreach ($conductores as $conductor)
                                <option value="{{ $conductor->id }}" @selected(old('conductor_id') == $conductor->id)>{{ $conductor->name }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('conductor_id')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="transportista" value="Transportista" />
                    <x-text-input id="transportista" name="transportista" type="text" class="mt-1.5 block w-full"
                                  :value="old('transportista')" placeholder="Ej. Transporte externo, retiro del cliente…" />
                    <x-input-error :messages="$errors->get('transportista')" class="mt-2" />
                </div>

                <x-form-footer :cancel="route('admin.despachos.index')">
                    <x-primary-button>Crear despacho</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
