<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Editar bodega" :subtitle="$bodega->nombre"
                       :back="route('admin.bodegas.show', $bodega)" backTitle="Volver a la bodega">
            <x-slot name="action">
                <x-form-actions form="bodega-form" submitLabel="Guardar clasificación" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form id="bodega-form" method="POST" action="{{ route('admin.bodegas.update', $bodega) }}" class="space-y-5">
                @csrf
                @method('PUT')

                {{-- Lo que viene de Bsale no se edita acá: la office es de ellos. --}}
                <p class="text-sm text-neutral-500">
                    El nombre <span class="font-medium text-neutral-700">{{ $bodega->nombre }}</span>, la dirección y el estado
                    vienen de Bsale y se actualizan solos con el espejo. Acá se clasifica la capa local.
                </p>

                <div>
                    <x-input-label for="sucursal_id" value="Sucursal" />
                    @php
                        // old() devuelve string y el modelo int/null: se compara
                        // todo como string ('' = transversal) para que la opcion
                        // elegida sobreviva a un redisplay por error de validacion.
                        $sucursalElegida = (string) old('sucursal_id', $bodega->sucursal_id ?? '');
                    @endphp
                    <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5">
                        <option value="" @selected($sucursalElegida === '')>— Transversal (sin sucursal) —</option>
                        @foreach ($sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}" @selected($sucursalElegida === (string) $sucursal->id)>{{ $sucursal->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-hint>MERMAS o RESERVA no pertenecen a una sucursal: quedan transversales.</x-input-hint>
                    <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="proposito" value="Propósito" />
                    <x-select id="proposito" name="proposito" class="mt-1.5" required>
                        <option value="" disabled @selected(old('proposito', $bodega->proposito) === null)>Elige un propósito…</option>
                        @foreach (\App\Models\Bodega::PROPOSITOS as $clave => $etiqueta)
                            <option value="{{ $clave }}" @selected(old('proposito', $bodega->proposito) === $clave)>{{ $etiqueta }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('proposito')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="alias" value="Nombre local (alias)" />
                    <x-text-input id="alias" class="mt-1.5" type="text" name="alias" :value="old('alias', $bodega->alias)" placeholder="Opcional" />
                    <x-input-hint>Cómo la llaman en la operación, si difiere del nombre de Bsale.</x-input-hint>
                    <x-input-error :messages="$errors->get('alias')" class="mt-2" />
                </div>

                <div class="space-y-2">
                    <x-checkbox-item name="en_operacion" value="1" :checked="old('en_operacion', $bodega->en_operacion)">
                        En operación
                        <x-slot name="note">desmarcada = invisible en pantallas operativas</x-slot>
                    </x-checkbox-item>
                </div>

                <p class="text-xs text-neutral-500">
                    Al guardar, la clasificación queda <span class="font-medium text-neutral-700">confirmada</span>
                    y el badge «por confirmar» desaparece.
                </p>
            </form>
        </div>
    </div>
</x-app-layout>
