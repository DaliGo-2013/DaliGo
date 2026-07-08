@php
    use Illuminate\Support\Str;

    $o = $orden ?? null;
    // Al registrar (no editar), el estado queda fijo en "recibido" y la fecha
    // estimada se muestra pero no se puede tocar (la fija el servidor).
    $esCreacion = $o === null;
    $productoActual = $o?->producto;
    $productoActualLabel = $productoActual ? ($productoActual->sku.' — '.$productoActual->nombre) : '';
@endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2"
    x-data="ordenServicioForm({
        cond: '{{ old('facturacion', $o?->facturacion ?? '') }}',
        fechaEntrega: '{{ old('fecha_entrega', $o?->fecha_entrega?->format('Y-m-d')) }}',
        feriados: @js($feriados),
        soloLectura: @js($esCreacion),
    })">
    {{-- Cliente: nombre y RUT obligatorios; autocompleta si existe en el catálogo. --}}
    <x-cliente-ingreso class="sm:col-span-2"
        :endpoint="route('admin.servicio-tecnico.buscar-cliente')"
        :inicialRut="$o?->cliente_rut ?? ''"
        :inicialNombre="$o?->cliente_nombre ?? ''"
        :inicialTelefono="$o?->cliente_telefono ?? ''"
        :inicialClienteId="$o?->cliente_id ?? 0" />

    {{-- Codigo (producto Dali) + N° de serie en el mismo renglon. --}}
    <x-buscador-remoto
        name="producto_id"
        label="Código (producto Dali)"
        chip="Producto"
        :required="true"
        :endpoint="route('admin.servicio-tecnico.buscar-producto')"
        :inicialId="$productoActual?->id ?? 0"
        :inicialLabel="$productoActualLabel"
        placeholder="Escribe el código (SKU) o el nombre…"
        hint="Búscalo por el código (SKU) o el nombre en el catálogo." />

    <div>
        <x-input-label for="numero_serie">N° de serie <span class="text-red-500">*</span></x-input-label>
        <x-text-input id="numero_serie" class="mt-1.5" type="text" name="numero_serie" :value="old('numero_serie', $o?->numero_serie)" minlength="3" maxlength="191" required />
        <x-input-error :messages="$errors->get('numero_serie')" class="mt-2" />
        <x-ayuda-serie />
    </div>

    <div>
        <x-input-label for="fecha_ingreso" value="Fecha de ingreso" />
        <x-text-input id="fecha_ingreso" class="mt-1.5" type="date" name="fecha_ingreso" x-ref="fechaIngreso"
            x-on:change="recalcularEntrega()"
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
        <x-input-label for="sucursal_id">Sucursal de recepción <span class="text-red-500">*</span></x-input-label>
        <x-select id="sucursal_id" name="sucursal_id" class="mt-1.5" required
            x-ref="sucursal" x-on:change="recalcularEntrega()">
            <option value="" disabled @selected(old('sucursal_id', $o?->sucursal_id) === null)>— Selecciona —</option>
            @foreach ($sucursales as $s)
                <option value="{{ $s->id }}" data-dias="{{ $s->dias_reparacion }}"
                    @selected((int) old('sucursal_id', $o?->sucursal_id) === $s->id)>{{ $s->nombre }}</option>
            @endforeach
        </x-select>
        <x-input-hint>Define el plazo de entrega: Mirador 10 días hábiles; Coquimbo, Abate Molina y Buzeta 15.</x-input-hint>
        <x-input-error :messages="$errors->get('sucursal_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="facturacion">Condición <span class="text-red-500">*</span></x-input-label>
        <x-select id="facturacion" name="facturacion" class="mt-1.5" required x-model="cond">
            <option value="" disabled @selected(old('facturacion', $o?->facturacion ?? '') === '')>— Selecciona —</option>
            @foreach ($facturaciones as $f)
                <option value="{{ $f }}" @selected(old('facturacion', $o?->facturacion) === $f)>{{ ucfirst($f) }}</option>
            @endforeach
        </x-select>
        <x-input-hint>Garantía: no se cobra (si está vigente). Reparación: se cobra al cliente.</x-input-hint>
        <x-input-error :messages="$errors->get('facturacion')" class="mt-2" />
    </div>

    {{-- Respaldo de garantia: solo visible si la condicion es «garantia».
         La garantia dura 6 meses desde la compra (la valida el servidor). --}}
    <div class="rounded-lg border border-brand-200 bg-brand-50 p-4 sm:col-span-2"
        x-show="cond === 'garantia'" x-cloak x-transition>
        <p class="mb-3 text-sm font-medium text-brand-700">
            Documento de compra (respalda la garantía · 6 meses desde la compra)
        </p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="garantia_doc_tipo" value="Documento" />
                <x-select id="garantia_doc_tipo" name="garantia_doc_tipo" class="mt-1.5"
                    x-bind:required="cond === 'garantia'">
                    <option value="">— Selecciona —</option>
                    @foreach ($garantiaDocTipos as $gt)
                        <option value="{{ $gt }}" @selected(old('garantia_doc_tipo', $o?->garantia_doc_tipo) === $gt)>{{ ucfirst($gt) }}</option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('garantia_doc_tipo')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="garantia_doc_numero" value="N° de documento" />
                <x-text-input id="garantia_doc_numero" class="mt-1.5" type="text" name="garantia_doc_numero"
                    :value="old('garantia_doc_numero', $o?->garantia_doc_numero)" maxlength="191"
                    x-bind:required="cond === 'garantia'" />
                <x-input-error :messages="$errors->get('garantia_doc_numero')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="garantia_doc_fecha" value="Fecha de compra" />
                <x-text-input id="garantia_doc_fecha" class="mt-1.5" type="date" name="garantia_doc_fecha"
                    :value="old('garantia_doc_fecha', $o?->garantia_doc_fecha?->format('Y-m-d'))"
                    x-bind:required="cond === 'garantia'" />
                <x-input-error :messages="$errors->get('garantia_doc_fecha')" class="mt-2" />
            </div>
        </div>
    </div>

    <div>
        <x-input-label value="Estado" />
        @if ($esCreacion)
            {{-- Toda orden nueva parte en "recibido"; el estado se avanza despues
                 (editar o pantalla de reparacion). El servidor lo fuerza igual. --}}
            <div class="mt-1.5 block w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3.5 py-2.5 text-sm text-neutral-500 shadow-sm">
                Recibido
            </div>
            <x-input-hint>Toda orden nueva parte en «Recibido».</x-input-hint>
        @else
            <x-select id="estado" name="estado" class="mt-1.5" required>
                @foreach ($estados as $e)
                    <option value="{{ $e }}" @selected(old('estado', $o?->estado ?? 'recibido') === $e)>{{ Str::headline($e) }}</option>
                @endforeach
            </x-select>
            <x-input-error :messages="$errors->get('estado')" class="mt-2" />
        @endif
    </div>

    <div>
        <x-input-label for="fecha_entrega" value="Fecha de entrega (estimada)" />
        @if ($esCreacion)
            {{-- Solo informativa: la calcula el servidor (sucursal + dias habiles);
                 el JS la muestra en vivo al elegir sucursal/fecha. --}}
            <x-text-input id="fecha_entrega" class="mt-1.5 pointer-events-none bg-neutral-50 text-neutral-500" type="date" name="fecha_entrega"
                x-model="fechaEntrega" readonly tabindex="-1" />
            <x-input-hint>Se calcula sola según la sucursal (días hábiles, sin fines de semana ni feriados).</x-input-hint>
        @else
            <x-text-input id="fecha_entrega" class="mt-1.5" type="date" name="fecha_entrega"
                x-model="fechaEntrega" x-on:input="entregaManual = true" />
            <x-input-hint>Se calcula sola según la sucursal (días hábiles, sin fines de semana ni feriados). Puedes editarla.</x-input-hint>
        @endif
        <x-input-error :messages="$errors->get('fecha_entrega')" class="mt-2" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="falla_reportada">Falla reportada <span class="text-red-500">*</span></x-input-label>
        <x-textarea id="falla_reportada" class="mt-1.5" name="falla_reportada" rows="2" minlength="3" required>{{ old('falla_reportada', $o?->falla_reportada) }}</x-textarea>
        <x-input-hint>Lo que reportó el cliente (con sus palabras).</x-input-hint>
        <x-input-error :messages="$errors->get('falla_reportada')" class="mt-2" />
    </div>

    {{-- Falla observada por el TECNICO: aparte de la del cliente, para no mezclar
         ni cambiar lo que dijo. El tecnico agrega lo que el cliente no indico. --}}
    <div class="sm:col-span-2">
        <x-input-label for="falla_tecnico" value="Falla reportada (técnico)" />
        <x-textarea id="falla_tecnico" class="mt-1.5" name="falla_tecnico" rows="2">{{ old('falla_tecnico', $o?->falla_tecnico) }}</x-textarea>
        <x-input-hint>Opcional. Lo que agrega el técnico (fallas que el cliente no indicó). No modifica lo que reportó el cliente.</x-input-hint>
        <x-input-error :messages="$errors->get('falla_tecnico')" class="mt-2" />
    </div>
</div>
