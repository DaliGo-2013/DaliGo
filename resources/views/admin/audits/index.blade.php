<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Auditoría" subtitle="Quién cambió qué y cuándo." />
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.audits.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="user_id" value="Usuario" />
                    <x-select id="user_id" name="user_id" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($usuarios as $u)
                            <option value="{{ $u->id }}" @selected(($filtros['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1">
                    <x-input-label for="auditable_type" value="Modelo" />
                    <x-select id="auditable_type" name="auditable_type" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($modelos as $clase => $label)
                            <option value="{{ $clase }}" @selected(($filtros['auditable_type'] ?? null) === $clase)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.audits.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Eventos" :count="$audits->total()" :countLabel="\Illuminate\Support\Str::plural('evento', $audits->total())">
                @forelse ($audits as $audit)
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($audit->user?->name ?? 'S', 0, 1) }}</x-avatar>
                        </x-slot>

                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $audit->user?->name ?? 'Sistema' }}</p>
                            <span class="text-sm text-neutral-500">
                                {{ $eventos[$audit->event] ?? $audit->event }}
                                {{ $modelos[$audit->auditable_type] ?? class_basename($audit->auditable_type) }} #{{ $audit->auditable_id }}
                            </span>
                        </div>
                        @php $mods = $audit->getModified(); @endphp
                        @if (! empty($mods))
                            <p class="mt-1 truncate text-xs text-neutral-400">
                                @foreach (array_slice($mods, 0, 3, true) as $campo => $cambio)
                                    @php
                                        $old = $cambio['old'] ?? null;
                                        $new = $cambio['new'] ?? null;
                                        $fmt = fn ($v) => is_scalar($v) || $v === null ? ($v === null ? '∅' : (string) $v) : json_encode($v);
                                    @endphp
                                    <span class="mr-3">{{ $campo }}: {{ \Illuminate\Support\Str::limit($fmt($old), 20) }} → {{ \Illuminate\Support\Str::limit($fmt($new), 20) }}</span>
                                @endforeach
                            </p>
                        @endif

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-48 sm:shrink-0 sm:text-right">
                                {{ $audit->created_at?->format('d-m-Y H:i') }} · {{ $audit->ip_address ?? '—' }}
                            </div>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">No hay eventos registrados.</li>
                @endforelse
            </x-list-card>

            @if ($audits->hasPages())
                <div>{{ $audits->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
