<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Receta de botellón" :subtitle="$producto->nombre"
                       :back="route('admin.recetas.index')" backTitle="Volver a recetas">
            <x-slot name="action">
                <x-form-actions form="receta-form" submitLabel="Guardar receta" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form id="receta-form" method="POST" action="{{ route('admin.recetas.update', $producto) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Producto terminado</p>
                    <p class="mt-1 text-sm font-medium text-neutral-900">{{ $producto->nombre }}</p>
                    <p class="text-sm text-neutral-500">{{ $producto->sku }}</p>
                </div>

                <x-seccion titulo="Preforma">
                    <div>
                        <x-input-label for="cantidad_preforma" value="Preformas por unidad" />
                        <x-text-input id="cantidad_preforma" name="cantidad_preforma" type="number"
                                      step="0.0001" min="0.0001" max="1000" required class="mt-1.5 w-full"
                                      :value="old('cantidad_preforma', (float) ($preforma->cantidad ?? 1))" />
                        <x-input-error class="mt-2" :messages="$errors->get('cantidad_preforma')" />
                        <x-input-hint class="mt-2">
                            La preforma concreta del movimiento es la que el jefe asigna al turno; acá solo se define cuántas consume una unidad. La merma también consume.
                        </x-input-hint>
                    </div>

                    <div>
                        <x-input-label for="ciclo_ideal_seg" value="Ciclo ideal (segundos por unidad)" />
                        <x-text-input id="ciclo_ideal_seg" name="ciclo_ideal_seg" type="number"
                                      step="1" min="1" max="600" class="mt-1.5 w-full"
                                      :value="old('ciclo_ideal_seg', $preforma->ciclo_ideal_seg ?? null)" />
                        <x-input-error class="mt-2" :messages="$errors->get('ciclo_ideal_seg')" />
                        <x-input-hint class="mt-2">
                            Segundos que tarda un ciclo de soplado de este botellón en condiciones normales. Lo usa el rendimiento del OEE; vacío = el informe dirá «sin ciclo cargado».
                        </x-input-hint>
                    </div>
                </x-seccion>

                <x-seccion titulo="Tapa">
                    <div>
                        <x-input-label for="componente_tapa" value="Producto tapa" />
                        <x-select id="componente_tapa" name="componente_tapa" class="mt-1.5 w-full">
                            <option value="">— Sin enlazar todavía —</option>
                            @foreach ($tapas as $opcion)
                                <option value="{{ $opcion->id }}" @selected((string) old('componente_tapa', $tapa->componente_id ?? '') === (string) $opcion->id)>
                                    {{ $opcion->nombre }} ({{ $opcion->sku }})
                                </option>
                            @endforeach
                        </x-select>
                        <x-input-error class="mt-2" :messages="$errors->get('componente_tapa')" />
                        <x-input-hint class="mt-2">
                            Sin producto enlazado, el consumo de tapas igual se registra en el kardex (sin producto) hasta que lo enlaces.
                        </x-input-hint>
                    </div>

                    <div>
                        <x-input-label for="cantidad_tapa" value="Tapas por unidad" />
                        <x-text-input id="cantidad_tapa" name="cantidad_tapa" type="number"
                                      step="0.0001" min="0.0001" max="1000" class="mt-1.5 w-full"
                                      :value="old('cantidad_tapa', $tapa ? (float) $tapa->cantidad : null)" />
                        <x-input-error class="mt-2" :messages="$errors->get('cantidad_tapa')" />
                        <x-input-hint class="mt-2">
                            Déjala vacía si este botellón no lleva tapa: la fila se elimina de la receta.
                        </x-input-hint>
                    </div>
                </x-seccion>

                <p class="text-xs text-neutral-500">
                    Al guardar, la receta queda confirmada y el badge «por confirmar» desaparece. La receta editada solo afecta las aprobaciones futuras: el kardex ya generado no se reescribe.
                </p>
            </form>
        </div>
    </div>
</x-app-layout>
