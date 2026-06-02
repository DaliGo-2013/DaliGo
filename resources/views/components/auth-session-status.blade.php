@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg bg-neutral-100 px-4 py-3 text-sm font-medium text-neutral-700']) }}>
        {{ $status }}
    </div>
@endif
