{{--
    Listado de devoluciones (M13). Ítem del menú (Operación) → SIN «Volver»
    (doctrina P-NAV-08). Filtro por estado + los QR/links firmados del
    formulario público por sucursal (colapsados: son de consulta ocasional,
    para imprimir y pegar donde se reciben devoluciones).
--}}
<x-app-layout ancho="listado">
    <x-slot name="header">
        <x-page-header title="Devoluciones" subtitle="Lo que los clientes declararon devolver y su estado (flujo completo: recibir → categorizar → resolver).">
            <x-slot name="action">
                <x-button-link :href="route('admin.devoluciones.informe')">Informe</x-button-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-4 py-6" x-data="{ paneles: { qr: false } }">
        <x-status-alert :status="session('status')" />

        {{-- QR del formulario público, por sucursal (colapsado por defecto). --}}
        <x-collapsible label="QR y links del formulario público" model="paneles.qr">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($linksQr as $nombre => $url)
                    <div class="flex flex-col items-center rounded-2xl border border-neutral-200 bg-white p-3 sm:p-4">
                        <p class="text-sm font-semibold text-neutral-900">{{ $nombre }}</p>
                        <div class="mt-3 rounded-xl border border-neutral-200 p-3">
                            <canvas data-qr="{{ $url }}" width="224" height="224" class="h-56 w-56"></canvas>
                        </div>
                        <input type="text" readonly value="{{ $url }}"
                               class="mt-3 w-full truncate rounded-lg border border-neutral-300 bg-neutral-50 px-3 py-2 text-xs text-neutral-500"
                               onclick="this.select()">
                    </div>
                @endforeach
            </div>
        </x-collapsible>

        {{-- Filtro por estado: enlaces tenues, no un form (consulta rápida). --}}
        <div class="flex flex-wrap items-center gap-2">
            <x-secondary-link :href="route('admin.devoluciones.index')"
                class="{{ $estado === null ? 'font-semibold text-brand-700' : '' }}">Todas</x-secondary-link>
            @foreach (\App\Models\Devolucion::ESTADOS as $e)
                <x-secondary-link :href="route('admin.devoluciones.index', ['estado' => $e])"
                    class="{{ $estado === $e ? 'font-semibold text-brand-700' : '' }}">{{ ucfirst($e) }}</x-secondary-link>
            @endforeach
        </div>

        <x-list-card title="Devoluciones" :subtitle="$devoluciones->total().' registro(s)'">
            @forelse ($devoluciones as $devolucion)
                <x-list-row>
                    <x-slot name="leading">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-xs font-semibold tabular-nums text-neutral-600">
                            {{ $devolucion->items->sum('cantidad') }}×
                        </span>
                    </x-slot>

                    <a href="{{ route('admin.devoluciones.show', $devolucion->id) }}" class="block truncate text-sm font-medium text-neutral-900 hover:text-brand-700">
                        {{ $devolucion->folio }} · {{ $devolucion->cliente_nombre }}
                    </a>
                    <p class="truncate text-sm text-neutral-500">
                        {{ $devolucion->items->first()?->descripcion }}
                        · {{ \App\Models\Devolucion::CANALES[$devolucion->canal] ?? $devolucion->canal }}
                        @if ($devolucion->causa) · {{ \App\Models\Devolucion::CAUSAS[$devolucion->causa] ?? $devolucion->causa }} @endif
                    </p>

                    <x-slot name="meta">
                        <x-badge :variant="$devolucion->esResuelta() ? 'neutral' : 'brand'">{{ ucfirst($devolucion->estado) }}</x-badge>
                        <span class="text-xs text-neutral-400">{{ $devolucion->created_at?->enChile()->format('d-m-Y H:i') }}</span>
                    </x-slot>
                </x-list-row>
            @empty
                <li class="px-6 py-8 text-center text-sm text-neutral-500">
                    No hay devoluciones{{ $estado ? ' en este estado' : ' todavía' }}.
                </li>
            @endforelse
        </x-list-card>

        {{ $devoluciones->links() }}
    </div>
</x-app-layout>
