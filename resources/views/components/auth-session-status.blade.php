@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-300']) }}>
        {{ $status }}
    </div>
@endif
