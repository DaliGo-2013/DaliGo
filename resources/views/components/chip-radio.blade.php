{{-- Radio estilo chip táctil para operarios (objetivo ≥48px). El input queda
     sr-only y el chip se pinta con peer-checked. Los atributos extra (x-model,
     x-on:change, etc.) van al input. --}}
@props(['name', 'value', 'label', 'checked' => false])

<label class="block cursor-pointer">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked($checked)
           {{ $attributes->merge(['class' => 'peer sr-only']) }}>
    <span class="flex min-h-12 items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 py-2 text-center text-sm font-medium text-neutral-700 shadow-sm transition duration-150 active:scale-[0.98] peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-1 peer-checked:ring-inset peer-checked:ring-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">{{ $label }}</span>
</label>
