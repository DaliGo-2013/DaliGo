<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sopladores" subtitle="Entra a un soplador para ver su historial de asignaciones y producción.">
            <x-slot name="action">
                <x-secondary-link :href="route('admin.produccion.index')">← Producción</x-secondary-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <x-status-alert :status="session('status')" class="mb-6" />

            <x-list-card title="Sopladores" :count="$sopladores->count()" :countLabel="\Illuminate\Support\Str::plural('soplador', $sopladores->count())">
                @forelse ($sopladores as $soplador)
                    @php $st = $stats->get($soplador->id); @endphp
                    <x-list-row>
                        <x-slot name="leading">
                            <x-avatar>{{ mb_substr($soplador->name, 0, 1) }}</x-avatar>
                        </x-slot>

                        <p class="truncate font-medium text-neutral-900">{{ $soplador->name }}</p>
                        <p class="truncate text-sm text-neutral-500">
                            @if ($st)
                                {{ $st->total }} {{ \Illuminate\Support\Str::plural('reporte', $st->total) }}
                                · último {{ \Illuminate\Support\Carbon::parse($st->ultima)->format('d-m-Y') }}
                            @else
                                Sin reportes todavía
                            @endif
                        </p>

                        <x-slot name="actions">
                            <a href="{{ route('admin.produccion.soplador', $soplador) }}"
                               class="whitespace-nowrap text-sm font-medium text-brand-600 transition duration-150 hover:text-brand-700">
                                Ver historial
                            </a>
                        </x-slot>
                    </x-list-row>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-neutral-500">
                        No hay sopladores registrados (usuarios con permiso de reportar producción).
                    </li>
                @endforelse
            </x-list-card>
        </div>
    </div>
</x-app-layout>
