@props(['name', 'value', 'checked' => false, 'disabled' => false])

<label {{ $attributes->class(['flex items-center gap-3 rounded-lg border border-neutral-200 px-3.5 py-2.5 text-sm transition duration-150 hover:bg-neutral-50', 'opacity-75' => $disabled]) }}>
    <x-checkbox :name="$name" :value="$value" :checked="$checked" :disabled="$disabled" />
    <span class="font-medium text-neutral-700">{{ $slot }}</span>
    @isset($note)
        <span class="ms-auto text-xs text-neutral-400">{{ $note }}</span>
    @endisset
</label>
