<x-app-layout>
    <x-slot name="header">
        {{-- Ítem del menú (doctrina P-NAV-08: sin Volver). --}}
        <x-page-header title="Recetas"
                       subtitle="Componentes que consume una unidad de cada botellón. Al aprobar un reporte, el kardex descuenta (buenos + merma) × cantidad." />
    </x-slot>

    <div class="space-y-6 py-12">
        <x-status-alert :status="session('status')" />

        <x-list-card title="Botellones" :count="$botellones->count()" :countLabel="\Illuminate\Support\Str::plural('receta', $botellones->count())">
            @forelse ($botellones as $botellon)
                @php
                    // La cadena se arma acá y se pinta una vez (el @endif@if
                    // encadenado inline NO compila — bitácora 2026-06-15).
                    $filas = $recetas[$botellon->id] ?? collect();
                    $preforma = $filas->firstWhere('rol', \App\Models\Receta::ROL_PREFORMA);
                    $tapa = $filas->firstWhere('rol', \App\Models\Receta::ROL_TAPA);
                    $fmt = fn ($c) => rtrim(rtrim(number_format((float) $c, 4, ',', '.'), '0'), ',');
                    $detalle = collect([
                        $preforma ? $fmt($preforma->cantidad).' preforma(s) — la del turno asignado' : '1 preforma(s) — receta implícita',
                        $tapa ? $fmt($tapa->cantidad).' tapa(s)'.($tapa->componente ? ' · '.$tapa->componente->nombre : ' · sin producto enlazado') : null,
                    ])->filter()->implode(' + ');
                    $porConfirmar = $filas->isEmpty() || $filas->contains(fn ($f) => ! $f->confirmada);
                @endphp
                <x-list-row>
                    {{-- La fila entera lleva a editar (mismo patrón que el
                         inventario: la fila ES el enlace, sin segundo control). --}}
                    <a href="{{ route('admin.recetas.edit', $botellon) }}" class="block">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900 hover:text-brand-600">{{ $botellon->nombre }}</p>
                            @if ($porConfirmar)
                                <x-badge variant="brand">por confirmar</x-badge>
                            @endif
                        </div>
                        <p class="truncate text-sm text-neutral-500">{{ $botellon->sku }} · {{ $detalle }}</p>
                    </a>

                    <x-slot name="actions">
                        <x-icon.chevron-right class="h-4 w-4 text-neutral-300" aria-hidden="true" />
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    Aún no hay botellones enlazados a un producto del catálogo. Enlaza los tipos de botellón a sus productos y las recetas aparecerán acá.
                </li>
            @endforelse
        </x-list-card>
    </div>
</x-app-layout>
