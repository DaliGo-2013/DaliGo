<x-app-layout ancho="formulario">
    <x-slot name="header">
        <x-page-header :title="$molde->nombre"
                       :subtitle="'Molde'.($molde->tipoBotellon ? ' · '.$molde->tipoBotellon->nombre : '')"
                       :back="route('admin.moldes.index')" backTitle="Volver a moldes">
            <x-slot name="action">
                <x-button-link :href="route('admin.moldes.edit', $molde)">
                    <x-icon.pencil class="h-4 w-4" />
                    <span class="ms-1.5">Editar</span>
                </x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        {{-- El estado que cambia cómo leer todo lo demás va PRIMERO (idioma M18). --}}
        @if ($molde->estado !== \App\Models\Molde::ESTADO_ACTIVO)
            <div class="dg-enter rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700 sm:px-6">
                Este molde está <span class="font-medium">{{ mb_strtolower($molde->estadoLabel()) }}</span>: no recibe ciclos ni aparece al aprobar reportes.
            </div>
        @endif
        @if ($correctiva = $molde->correctivaPendiente())
            <div class="dg-enter rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 sm:px-6">
                <span class="font-medium">Mantención correctiva pendiente</span> — nació de una parada «Molde dañado» ({{ $correctiva->created_at->enChile()->format('d-m-Y H:i') }}). Regístrala abajo cuando el molde quede reparado.
            </div>
        @endif

        {{-- El corazón de la ficha: el contador contra su umbral. --}}
        <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Ciclos y mantención</h3>
            </div>
            <div class="space-y-3 p-4 sm:p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-neutral-400">Ciclos acumulados</p>
                        <p class="mt-1 text-2xl font-semibold {{ $molde->umbralCruzado() ? 'text-brand-600' : 'text-neutral-900' }}">{{ number_format($molde->ciclos_acumulados, 0, ',', '.') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-neutral-400">Umbral</p>
                        <p class="mt-1 text-sm font-medium text-neutral-700">{{ $molde->umbral_mantencion !== null ? number_format($molde->umbral_mantencion, 0, ',', '.') : 'Sin umbral' }}</p>
                    </div>
                </div>
                @if ($molde->umbral_mantencion !== null)
                    <div class="h-2 w-full overflow-hidden rounded-full bg-neutral-200">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ min(100, round($molde->ciclos_acumulados / max(1, $molde->umbral_mantencion) * 100)) }}%"></div>
                    </div>
                    <p class="text-sm text-neutral-600">{{ $molde->umbralLabel() }}</p>
                @else
                    <p class="text-sm text-neutral-500">Sin umbral declarado: el molde acumula ciclos pero no avisa. Cárgalo en Editar.</p>
                @endif
            </div>
        </div>

        {{-- Identificación --}}
        <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Identificación</h3>
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-4 sm:p-6 sm:grid-cols-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-400">Tipo de botellón</dt>
                    <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $molde->tipoBotellon?->nombre ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-400">Cavidades</dt>
                    <dd class="mt-1 text-sm font-medium text-neutral-900">{{ $molde->cavidades ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-400">Ciclo ideal (receta)</dt>
                    <dd class="mt-1 text-sm font-medium text-neutral-900">
                        {{-- El ciclo vive en la RECETA (única portadora, P-M11-12): acá solo se muestra. --}}
                        @if ($ciclo = $molde->cicloIdealDeReceta())
                            {{ $ciclo }} s
                        @else
                            —
                        @endif
                        @if ($molde->tipoBotellon?->producto_id)
                            · <a href="{{ route('admin.recetas.edit', $molde->tipoBotellon->producto_id) }}" class="text-brand-600 transition duration-150 hover:text-brand-700">editar receta</a>
                        @endif
                    </dd>
                </div>
                @if ($molde->notas)
                    <div class="col-span-2 sm:col-span-3">
                        <dt class="text-xs uppercase tracking-wide text-neutral-400">Notas</dt>
                        <dd class="mt-1 text-sm text-neutral-700">{{ $molde->notas }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Registrar mantención (resetea el contador y re-arma el aviso). --}}
        <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Registrar mantención</h3>
            </div>
            <form method="POST" action="{{ route('admin.moldes.mantencion.store', $molde) }}" class="space-y-4 p-4 sm:p-6">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="tipo" value="Tipo" />
                        <x-select id="tipo" name="tipo" class="mt-1.5 w-full" required>
                            @foreach (\App\Models\MoldeMantencion::TIPOS as $valor => $label)
                                <option value="{{ $valor }}" @selected(old('tipo', $correctiva ? \App\Models\MoldeMantencion::TIPO_CORRECTIVA : \App\Models\MoldeMantencion::TIPO_PREVENTIVA) === $valor)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('tipo')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="nota" value="Nota" />
                        <x-text-input id="nota" name="nota" type="text" maxlength="191" class="mt-1.5 w-full" :value="old('nota')" placeholder="Opcional" />
                        <x-input-error :messages="$errors->get('nota')" class="mt-2" />
                    </div>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <p class="text-xs text-neutral-500">Al registrarla, el contador vuelve a cero y el aviso de umbral se re-arma.{{ $correctiva ? ' La correctiva pendiente se marca como realizada.' : '' }}</p>
                    <x-primary-button>Registrar</x-primary-button>
                </div>
            </form>
        </div>

        {{-- Historial --}}
        <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div class="border-b border-neutral-100 px-4 py-3 sm:px-6">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Historial de mantenciones</h3>
            </div>
            <ul class="divide-y divide-neutral-100">
                @forelse ($molde->mantenciones as $mantencion)
                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-neutral-900">{{ $mantencion->tipoLabel() }}</p>
                                @if ($mantencion->pendiente())
                                    <x-badge variant="brand">pendiente</x-badge>
                                @endif
                            </div>
                            @php
                                $detalle = collect([
                                    $mantencion->realizada_at ? 'realizada el '.$mantencion->realizada_at->enChile()->format('d-m-Y H:i') : 'creada el '.$mantencion->created_at->enChile()->format('d-m-Y H:i'),
                                    $mantencion->user_nombre,
                                    $mantencion->nota,
                                ])->filter()->implode(' · ');
                            @endphp
                            <p class="truncate text-sm text-neutral-500">{{ $detalle }}</p>
                        </div>
                        <span class="text-sm tabular-nums text-neutral-500">{{ number_format($mantencion->ciclos_al_momento, 0, ',', '.') }} ciclos</span>
                    </li>
                @empty
                    <li class="px-6 py-6 text-center text-sm text-neutral-500">Sin mantenciones registradas todavía.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-app-layout>
