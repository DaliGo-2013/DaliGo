@props(['title', 'subtitle' => null, 'back' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex min-w-0 items-center gap-3">
        @isset($back)
            <x-icon-button :href="$back" size="lg" variant="secondary" label="Volver" title="Volver">
                <x-icon.arrow-left class="h-5 w-5" />
            </x-icon-button>
        @endisset
        <div class="min-w-0">
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">{{ $title }}</h2>
            @isset($subtitle)
                <p class="mt-1 text-sm text-neutral-500">{{ $subtitle }}</p>
            @endisset
        </div>
    </div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
