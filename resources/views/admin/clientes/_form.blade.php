@php $c = $cliente ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="rut" value="RUT" />
        <x-text-input id="rut" class="mt-1.5" type="text" name="rut" :value="old('rut', $c?->rut)" maxlength="20" placeholder="ej. 12.345.678-9" />
        <x-input-hint>Opcional. Se guarda normalizado (sin puntos).</x-input-hint>
        <x-input-error :messages="$errors->get('rut')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="razon_social" value="Razón social / Nombre" />
        <x-text-input id="razon_social" class="mt-1.5" type="text" name="razon_social" :value="old('razon_social', $c?->razon_social)" required maxlength="191" />
        <x-input-error :messages="$errors->get('razon_social')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="giro" value="Giro" />
        <x-text-input id="giro" class="mt-1.5" type="text" name="giro" :value="old('giro', $c?->giro)" maxlength="191" />
        <x-input-error :messages="$errors->get('giro')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="Correo electrónico" />
        <x-text-input id="email" class="mt-1.5" type="email" name="email" :value="old('email', $c?->email)" maxlength="191" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="telefono" value="Teléfono" />
        <x-text-input id="telefono" class="mt-1.5" type="text" name="telefono" :value="old('telefono', $c?->telefono)" maxlength="191" />
        <x-input-error :messages="$errors->get('telefono')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="direccion" value="Dirección" />
        <x-text-input id="direccion" class="mt-1.5" type="text" name="direccion" :value="old('direccion', $c?->direccion)" maxlength="191" />
        <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="comuna" value="Comuna" />
        <x-text-input id="comuna" class="mt-1.5" type="text" name="comuna" :value="old('comuna', $c?->comuna)" maxlength="191" />
        <x-input-error :messages="$errors->get('comuna')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="ciudad" value="Ciudad" />
        <x-text-input id="ciudad" class="mt-1.5" type="text" name="ciudad" :value="old('ciudad', $c?->ciudad)" maxlength="191" />
        <x-input-error :messages="$errors->get('ciudad')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="segmento" value="Segmento" />
        <x-select id="segmento" name="segmento" class="mt-1.5">
            <option value="">— Sin segmento —</option>
            @foreach ($segmentos as $s)
                <option value="{{ $s }}" @selected(old('segmento', $c?->segmento) === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('segmento')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="vendedor_id" value="Vendedor asignado" />
        <x-select id="vendedor_id" name="vendedor_id" class="mt-1.5">
            <option value="">— Sin vendedor —</option>
            @foreach ($vendedores as $v)
                <option value="{{ $v->id }}" @selected((int) old('vendedor_id', $c?->vendedor_id) === $v->id)>{{ $v->name }}</option>
            @endforeach
        </x-select>
        @if ($vendedores->isEmpty())
            <x-input-hint>No hay usuarios con rol vendedor o jefe de ventas todavía.</x-input-hint>
        @endif
        <x-input-error :messages="$errors->get('vendedor_id')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="notas" value="Notas internas" />
        <x-textarea id="notas" class="mt-1.5" name="notas" rows="2">{{ old('notas', $c?->notas) }}</x-textarea>
        <x-input-hint>Solo visibles en DaliGo (Bsale no las conoce).</x-input-hint>
        <x-input-error :messages="$errors->get('notas')" class="mt-2" />
    </div>

    <div class="space-y-3 sm:col-span-2">
        <x-checkbox-item name="es_empresa" value="1" :checked="(bool) old('es_empresa', $c?->es_empresa ?? false)">
            Es empresa
        </x-checkbox-item>
        <x-checkbox-item name="envio_factura_email" value="1" :checked="(bool) old('envio_factura_email', $c?->envio_factura_email ?? false)">
            Envío automático de factura por correo
            <x-slot name="note">En clientes enlazados, la sincronización lo realinea con Bsale.</x-slot>
        </x-checkbox-item>
        <x-checkbox-item name="activo" value="1" :checked="(bool) old('activo', $c?->activo ?? true)">
            Activo
        </x-checkbox-item>
    </div>
</div>
