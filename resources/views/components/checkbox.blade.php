@props(['checked' => false, 'disabled' => false])

<input type="checkbox" @checked($checked) @disabled($disabled) {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-neutral-300 text-brand-600 transition duration-150 focus:ring-2 focus:ring-brand-500/30']) }}>
