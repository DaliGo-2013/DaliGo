@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 shadow-sm transition duration-150 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30']) }}>
