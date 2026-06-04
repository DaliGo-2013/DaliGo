@props(['title', 'subtitle' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h2 class="text-xl font-semibold leading-tight text-neutral-900">{{ $title }}</h2>
        @isset($subtitle)
            <p class="mt-1 text-sm text-neutral-500">{{ $subtitle }}</p>
        @endisset
    </div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>
