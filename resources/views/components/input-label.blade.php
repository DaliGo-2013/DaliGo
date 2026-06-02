@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-neutral-700']) }}>
    {{ $value ?? $slot }}
</label>
