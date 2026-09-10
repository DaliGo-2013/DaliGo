<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header title="Asignar producción" subtitle="Define las preformas asignadas a un soplador para el día."
                       :back="route('admin.produccion.index')" backTitle="Volver a Producción" />
    </x-slot>

    <div class="py-12">
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-8">
            <form method="POST" action="{{ route('admin.produccion.asignar.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="soplador_id" value="Soplador" />
                    <x-select id="soplador_id" name="soplador_id" class="mt-1.5" required>
                        <option value="" disabled @selected(! old('soplador_id'))>Selecciona un soplador…</option>
                        @foreach ($sopladores as $soplador)
                            <option value="{{ $soplador->id }}" @selected((int) old('soplador_id') === $soplador->id)>{{ $soplador->name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('soplador_id')" class="mt-2" />
                    @if ($sopladores->isEmpty())
                        <x-input-hint>No hay usuarios con el rol {{ $rotuloRolesSoplador }} todavía. Asigna el rol en Usuarios.</x-input-hint>
                    @endif
                </div>

                <div>
                    <x-input-label for="turno" value="Turno" />
                    <x-select id="turno" name="turno" class="mt-1.5" required>
                        @foreach ($turnos as $turno)
                            <option value="{{ $turno }}" @selected(old('turno', 'dia') === $turno)>{{ ucfirst($turno) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('turno')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="fecha" value="Fecha" />
                    <x-text-input id="fecha" class="mt-1.5" type="date" name="fecha" :value="old('fecha', \App\Support\FechaNegocio::hoy())" required />
                    <x-input-error :messages="$errors->get('fecha')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="asignadas" value="Preformas asignadas">
                        <x-slot:ayuda>Cada "Asignar" crea una producción nueva e independiente (un soplador puede tener varias el mismo día).</x-slot:ayuda>
                    </x-input-label>
                    <x-text-input id="asignadas" class="mt-1.5" type="number" min="1" max="100000" name="asignadas" :value="old('asignadas')" required placeholder="Ej. 1200" />
                    <x-input-error :messages="$errors->get('asignadas')" class="mt-2" />
                </div>

                @php
                    // Etiqueta con sucursal solo si hay más de una: el jefe elige
                    // la máquina de la sucursal del soplador (la validación lo exige).
                    $multiSucursal = $maquinas->pluck('sucursal_id')->unique()->count() > 1;
                @endphp
                @if ($maquinas->isNotEmpty())
                    <div>
                        <x-input-label for="maquina_id" value="Máquina">
                            <x-slot:ayuda>La sopladora en la que trabaja este turno. El soplador no la elige: sus tandas y paradas quedan en esta máquina.</x-slot:ayuda>
                        </x-input-label>
                        <x-select id="maquina_id" name="maquina_id" class="mt-1.5" required>
                            <option value="" disabled @selected(! old('maquina_id'))>Selecciona la máquina…</option>
                            @foreach ($maquinas as $maquina)
                                <option value="{{ $maquina->id }}" @selected((int) old('maquina_id') === $maquina->id)>{{ $multiSucursal ? "{$maquina->nombre} · {$maquina->sucursal?->nombre}" : $maquina->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('maquina_id')" class="mt-2" />
                    </div>
                @endif

                @if ($tipos->isNotEmpty())
                    <div>
                        <x-input-label for="tipo_botellon_id" value="Tipo de botellón">
                            <x-slot:ayuda>Qué botellón se produce este turno. Define el molde y el producto que entra al kardex al aprobar.</x-slot:ayuda>
                        </x-input-label>
                        <x-select id="tipo_botellon_id" name="tipo_botellon_id" class="mt-1.5" required>
                            <option value="" disabled @selected(! old('tipo_botellon_id'))>Selecciona el tipo…</option>
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo->id }}" @selected((int) old('tipo_botellon_id') === $tipo->id)>{{ $tipo->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('tipo_botellon_id')" class="mt-2" />
                    </div>
                @endif

                <div>
                    <x-input-label for="preforma_id" value="Preforma (opcional)">
                        <x-slot:ayuda>Qué preforma trabaja este turno. Al aprobar el reporte se descontará en el kardex de producción.</x-slot:ayuda>
                    </x-input-label>
                    <x-select id="preforma_id" name="preforma_id" class="mt-1.5">
                        <option value="" @selected(! old('preforma_id'))>Sin especificar</option>
                        @foreach ($preformas as $preforma)
                            <option value="{{ $preforma->id }}" @selected((int) old('preforma_id') === $preforma->id)>{{ $preforma->nombre }} ({{ $preforma->sku }})</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('preforma_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="procedencia" value="Procedencia de la preforma (opcional)" />
                    <x-select id="procedencia" name="procedencia" class="mt-1.5">
                        <option value="" @selected(! old('procedencia'))>Sin especificar</option>
                        @foreach ($procedencias as $procedencia)
                            <option value="{{ $procedencia }}" @selected(old('procedencia') === $procedencia)>{{ ucfirst($procedencia) }}</option>
                        @endforeach
                    </x-select>
                    <x-input-hint>En qué formato llegó la preforma de este turno (saco o caja).</x-input-hint>
                    <x-input-error :messages="$errors->get('procedencia')" class="mt-2" />
                </div>

                <x-form-footer>
                    <x-primary-button>Guardar asignación</x-primary-button>
                </x-form-footer>
            </form>
        </div>
    </div>
</x-app-layout>
