<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Notificaciones" subtitle="Todas las notificaciones del sistema y su estado de envío.">
            <x-slot name="action">
                <form method="POST" action="{{ route('admin.notificaciones.prueba') }}">
                    @csrf
                    <x-primary-button>Enviar prueba</x-primary-button>
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            <x-status-alert :status="session('status')" />

            {{-- Filtros --}}
            <form method="GET" action="{{ route('admin.notificaciones.index') }}"
                  class="flex flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end">
                <div class="flex-1">
                    <x-input-label for="estado" value="Estado" />
                    <x-select id="estado" name="estado" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($estados as $e)
                            <option value="{{ $e }}" @selected(($filtros['estado'] ?? null) === $e)>{{ ucfirst($e) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1">
                    <x-input-label for="canal" value="Canal" />
                    <x-select id="canal" name="canal" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($canales as $c)
                            <option value="{{ $c }}" @selected(($filtros['canal'] ?? null) === $c)>{{ ucfirst($c) }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1">
                    <x-input-label for="evento" value="Evento" />
                    <x-select id="evento" name="evento" class="mt-1.5">
                        <option value="">Todos</option>
                        @foreach ($eventos as $clave => $label)
                            <option value="{{ $clave }}" @selected(($filtros['evento'] ?? null) === $clave)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex items-center gap-3">
                    <x-primary-button>Filtrar</x-primary-button>
                    @if (array_filter($filtros))
                        <x-secondary-link :href="route('admin.notificaciones.index')">Limpiar</x-secondary-link>
                    @endif
                </div>
            </form>

            <x-list-card title="Notificaciones" :count="$notificaciones->total()" :countLabel="\Illuminate\Support\Str::plural('notificación', $notificaciones->total())">
                @forelse ($notificaciones as $notificacion)
                    @php
                        $tono = match ($notificacion->estado) {
                            \App\Models\Notificacion::ENVIADA => 'brand',
                            \App\Models\Notificacion::FALLIDA => 'danger',
                            default => 'neutral',
                        };
                    @endphp
                    <x-list-row>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium text-neutral-900">{{ $notificacion->titulo }}</p>
                            <x-badge :variant="$tono">{{ ucfirst($notificacion->estado) }}</x-badge>
                            <x-badge variant="neutral">{{ ucfirst($notificacion->canal) }}</x-badge>
                            @if ($notificacion->intentos > 0)
                                <x-badge variant="neutral">{{ $notificacion->intentos }} {{ \Illuminate\Support\Str::plural('intento', $notificacion->intentos) }}</x-badge>
                            @endif
                        </div>
                        @php
                            // Correo/teléfono de destino SIEMPRE visible (micro-backlog M15-a):
                            // con usuario interno se muestra nombre + destino; sin usuario, el destino solo.
                            $destino = $notificacion->destinatario ?: $notificacion->user?->email;
                        @endphp
                        <p class="truncate text-sm text-neutral-500">
                            {{ $eventos[$notificacion->evento] ?? $notificacion->evento }}
                            · {{ $notificacion->user?->name ?? $destino ?? 'Sin destinatario' }}@if ($notificacion->user && $destino) · <span class="text-neutral-400">{{ $destino }}</span>@endif
                        </p>
                        @if ($notificacion->ultimo_error)
                            {{-- Error completo, expandible (micro-backlog M15-b): el resumen corto abre el texto íntegro sin truncar. --}}
                            <details class="mt-1 text-xs text-red-600">
                                <summary class="cursor-pointer truncate">{{ \Illuminate\Support\Str::limit($notificacion->ultimo_error, 80) }}</summary>
                                <p class="mt-1 whitespace-pre-wrap break-words rounded-lg bg-red-50 p-2">{{ $notificacion->ultimo_error }}</p>
                            </details>
                        @endif

                        <x-slot name="meta">
                            <div class="text-sm text-neutral-500 sm:w-48 sm:shrink-0 sm:text-right">
                                {{ $notificacion->enviada_at?->format('d-m-Y H:i') ?? $notificacion->created_at?->format('d-m-Y H:i') }}
                            </div>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-8 text-center text-sm text-neutral-500">No hay notificaciones registradas.</li>
                @endforelse
            </x-list-card>

            @if ($notificaciones->hasPages())
                <div>{{ $notificaciones->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
