<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Asignar producción" subtitle="Define las preformas asignadas a un soplador para el día." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
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
                            <x-input-hint>No hay usuarios con el rol Soplador todavía. Crea uno en Usuarios.</x-input-hint>
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
                        <x-input-label for="asignadas" value="Preformas asignadas" />
                        <x-text-input id="asignadas" class="mt-1.5" type="number" min="1" max="100000" name="asignadas" :value="old('asignadas')" required placeholder="Ej. 1200" />
                        <x-input-hint>Cada "Asignar" crea una producción nueva e independiente (un soplador puede tener varias el mismo día).</x-input-hint>
                        <x-input-error :messages="$errors->get('asignadas')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="preforma_id" value="Preforma (opcional)" />
                        <x-select id="preforma_id" name="preforma_id" class="mt-1.5">
                            <option value="" @selected(! old('preforma_id'))>Sin especificar</option>
                            @foreach ($preformas as $preforma)
                                <option value="{{ $preforma->id }}" @selected((int) old('preforma_id') === $preforma->id)>{{ $preforma->nombre }} ({{ $preforma->sku }})</option>
                            @endforeach
                        </x-select>
                        <x-input-hint>Qué preforma trabaja este turno. Al aprobar el reporte se descontará en el kardex de producción.</x-input-hint>
                        <x-input-error :messages="$errors->get('preforma_id')" class="mt-2" />
                    </div>

                    <x-form-footer :cancel="route('admin.produccion.index')">
                        <x-primary-button>Guardar asignación</x-primary-button>
                    </x-form-footer>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
