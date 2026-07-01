{{-- Chips de totales. $chips = array de ['label','valor','tono'] (tono: 'brand' | 'muted' | null). --}}
<div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
    @foreach ($chips as $chip)
        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $chip['label'] }}</p>
            <p class="mt-1 text-2xl font-semibold {{ ($chip['tono'] ?? null) === 'brand' ? 'text-brand-600' : (($chip['tono'] ?? null) === 'muted' ? 'text-neutral-500' : 'text-neutral-900') }}">{{ $chip['valor'] }}</p>
        </div>
    @endforeach
</div>
