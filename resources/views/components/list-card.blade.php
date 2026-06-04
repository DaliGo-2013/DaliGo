@props(['title' => null, 'count' => null, 'countLabel' => null])

<div {{ $attributes->merge(['class' => 'dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm']) }}>
    @isset($title)
        <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">{{ $title }}</h3>
            @isset($count)
                <span class="text-xs font-medium text-neutral-400">{{ $count }} {{ $countLabel }}</span>
            @endisset
        </div>
    @endisset

    <ul role="list" class="divide-y divide-neutral-100">
        {{ $slot }}
    </ul>
</div>
