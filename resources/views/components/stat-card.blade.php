@props(['label', 'valor', 'href', 'alerta' => false])

{{-- Indicador del Inicio: número grande que enlaza a la pantalla donde se
     actúa. Con alerta=true el número se destaca en color de marca cuando > 0. --}}
<a href="{{ $href }}"
    class="block rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm transition duration-150 hover:border-neutral-300 hover:shadow active:scale-[0.98]">
    <p class="text-3xl font-semibold {{ $alerta && $valor > 0 ? 'text-brand-600' : 'text-neutral-900' }}">
        {{ number_format($valor, 0, ',', '.') }}
    </p>
    <p class="mt-1 text-sm text-neutral-500">{{ $label }}</p>
</a>
