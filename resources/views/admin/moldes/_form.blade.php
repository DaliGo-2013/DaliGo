{{-- Campos compartidos por create y edit. Recibe $molde (con defaults al crear) y $tipos. --}}
<div>
    <x-input-label for="nombre" value="Nombre" />
    <x-text-input id="nombre" class="mt-1.5 w-full" type="text" name="nombre" :value="old('nombre', $molde->nombre)" required autofocus placeholder="Ej. Molde 20L A" />
    <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
</div>

<div>
    <x-input-label for="tipo_botellon_id" value="Tipo de botellón" />
    <x-select id="tipo_botellon_id" name="tipo_botellon_id" class="mt-1.5 w-full" required>
        <option value="">— Elige un tipo —</option>
        @foreach ($tipos as $tipo)
            <option value="{{ $tipo->id }}" @selected((string) old('tipo_botellon_id', $molde->tipo_botellon_id) === (string) $tipo->id)>{{ $tipo->nombre }}</option>
        @endforeach
    </x-select>
    <x-input-error :messages="$errors->get('tipo_botellon_id')" class="mt-2" />
    <x-input-hint class="mt-2">El ciclo ideal del botellón vive en su receta; la ficha del molde lo muestra desde ahí.</x-input-hint>
</div>

<div>
    <x-input-label for="cavidades" value="Cavidades" />
    <x-text-input id="cavidades" class="mt-1.5 w-full" type="number" name="cavidades" min="1" max="64" step="1" :value="old('cavidades', $molde->cavidades)" placeholder="Ej. 2" />
    <x-input-error :messages="$errors->get('cavidades')" class="mt-2" />
    <x-input-hint class="mt-2">Cuántas unidades salen por ciclo de este molde. Vacío = sin dato.</x-input-hint>
</div>

<div>
    <x-input-label for="umbral_mantencion" value="Umbral de mantención (ciclos)">
        <x-slot:ayuda>Al cruzarlo, producción recibe el aviso «le toca mantención» (una vez por cruce). Vacío = sin umbral.</x-slot:ayuda>
    </x-input-label>
    <x-text-input id="umbral_mantencion" class="mt-1.5 w-full" type="number" name="umbral_mantencion" min="1" step="1" :value="old('umbral_mantencion', $molde->umbral_mantencion)" placeholder="Ej. 50000" />
    <x-input-error :messages="$errors->get('umbral_mantencion')" class="mt-2" />
</div>

<div>
    <x-input-label for="estado" value="Estado">
        <x-slot:ayuda>Solo los moldes ACTIVOS reciben ciclos y aparecen al aprobar reportes; un retirado conserva su historia.</x-slot:ayuda>
    </x-input-label>
    <x-select id="estado" name="estado" class="mt-1.5 w-full" required>
        @foreach (\App\Models\Molde::ESTADOS as $valor => $label)
            <option value="{{ $valor }}" @selected(old('estado', $molde->estado) === $valor)>{{ $label }}</option>
        @endforeach
    </x-select>
    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
</div>

<div>
    <x-input-label for="notas" value="Notas" />
    <x-text-input id="notas" class="mt-1.5 w-full" type="text" name="notas" maxlength="191" :value="old('notas', $molde->notas)" placeholder="Opcional" />
    <x-input-error :messages="$errors->get('notas')" class="mt-2" />
</div>
