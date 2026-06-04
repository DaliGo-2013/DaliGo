@props(['status' => null])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-neutral-200 bg-white px-4 py-3 text-sm font-medium text-neutral-700 shadow-sm']) }}>
        {{ $status }}
    </div>
@endif
