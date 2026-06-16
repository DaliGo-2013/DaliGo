@php
    use Illuminate\Support\Str;

    $o = $orden ?? null;
    $clienteActual = $o?->cliente;
    $clienteActualLabel = $clienteActual
        ? (($clienteActual->rut ? $clienteActual->rut.' — ' : '').$clienteActual->razon_social)
        : '';
    $productoActual = $o?->producto;
    $productoActualLabel = $productoActual ? ($productoActual->sku.' — '.$productoActual->nombre) : '';
@endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    {{-- Cliente: se busca por RUT o razon social (autocompletado). --}}
    <x-buscador-remoto class="sm:col-span-2"
        name="cliente_id"
        label="Cliente (buscar por RUT o nombre)"
        chip="Cliente"
        :endpoint="route('admin.servicio-tecnico.buscar-cliente')"
        :inicialId="$clienteActual?->id ?? 0"
        :inicialLabel="$clienteActualLabel"
        placeholder="Escribe RUT o razón social…"
        hint="Opcional. Si el cliente no existe aún, puedes dejarlo en blanco." />

    {{-- Codigo: producto Dali del catalogo (por SKU). --}}
    <x-buscador-remoto class="sm:col-span-2"
        name="producto_id"
        label="Código (producto Dali)"
        chip="Producto"
        :endpoint="route('admin.servicio-tecnico.buscar-producto')"
        :inicialId="$productoActual?->id ?? 0"
        :inicialLabel="$productoActualLabel"
        placeholder="Escribe el código (SKU) o el nombre…"
        hint="Opcional. Es el producto Dali del catálogo." />

    <div>
        <x-input-label for="fecha_ingreso" value="Fecha de ingreso" />
        <x-text-input id="fecha_ingreso" class="mt-1.5" type="date" name="fecha_ingreso"
            :value="old('fecha_ingreso', $o?->fecha_ingreso?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
        <x-input-error :messages="$errors->get('fecha_ingreso')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="tipo_equipo" value="Tipo de equipo" />
        <x-select id="tipo_equipo" name="tipo_equipo" class="mt-1.5" required>
            @foreach ($tipos as $t)
                <option value="{{ $t }}" @selected(old('tipo_equipo', $o?->tipo_equipo) === $t)>{{ ucfirst($t) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('tipo_equipo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="modelo" value="Modelo" />
        <x-text-input id="modelo" class="mt-1.5" type="text" name="modelo" :value="old('modelo', $o?->modelo)" maxlength="191" />
        <x-input-error :messages="$errors->get('modelo')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="numero_serie" value="N° de serie" />
        <x-text-input id="numero_serie" class="mt-1.5" type="text" name="numero_serie" :value="old('numero_serie', $o?->numero_serie)" maxlength="191" />
        <x-input-error :messages="$errors->get('numero_serie')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="sucursal_id" value="Sucursal de recepción" />
        <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5">
            <option value="">— Sin sucursal —</option>
            @foreach ($sucursales as $s)
                <option value="{{ $s->id }}" @selected((int) old('sucursal_id', $o?->sucursal_id) === $s->id)>{{ $s->nombre }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="facturacion" value="Condición" />
        <x-select id="facturacion" name="facturacion" class="mt-1.5">
            <option value="">— Sin definir —</option>
            @foreach ($facturaciones as $f)
                <option value="{{ $f }}" @selected(old('facturacion', $o?->facturacion) === $f)>{{ ucfirst($f) }}</option>
            @endforeach
        </x-select>
        <x-input-hint>Garantía: no se cobra. Boleta: se cobra la reparación.</x-input-hint>
        <x-input-error :messages="$errors->get('facturacion')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="estado" value="Estado" />
        <x-select id="estado" name="estado" class="mt-1.5" required>
            @foreach ($estados as $e)
                <option value="{{ $e }}" @selected(old('estado', $o?->estado ?? 'recibido') === $e)>{{ Str::headline($e) }}</option>
            @endforeach
        </x-select>
        <x-input-error :messages="$errors->get('estado')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="fecha_entrega" value="Fecha de entrega" />
        <x-text-input id="fecha_entrega" class="mt-1.5" type="date" name="fecha_entrega"
            :value="old('fecha_entrega', $o?->fecha_entrega?->format('Y-m-d'))" />
        <x-input-hint>Opcional. Se completa al entregar el equipo.</x-input-hint>
        <x-input-error :messages="$errors->get('fecha_entrega')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="falla_reportada" value="Falla reportada" />
        <x-textarea id="falla_reportada" class="mt-1.5" name="falla_reportada" rows="2">{{ old('falla_reportada', $o?->falla_reportada) }}</x-textarea>
        <x-input-error :messages="$errors->get('falla_reportada')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="observaciones" value="Observaciones" />
        <x-textarea id="observaciones" class="mt-1.5" name="observaciones" rows="2">{{ old('observaciones', $o?->observaciones) }}</x-textarea>
        <x-input-error :messages="$errors->get('observaciones')" class="mt-2" />
    </div>
</div>
