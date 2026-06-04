@props(['cancel' => null, 'cancelLabel' => 'Cancelar'])

<div class="flex items-center justify-end gap-4 pt-2">
    @isset($cancel)
        <x-secondary-link :href="$cancel">{{ $cancelLabel }}</x-secondary-link>
    @endisset
    {{ $slot }}
</div>
