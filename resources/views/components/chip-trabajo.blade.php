{{-- Chip TÁCTIL de selección múltiple para marcar trabajos del catálogo (28-08-2026).

     Hermano de `chip-radio` (mismo peer-checked, mismo ≥48px táctil) pero con checkbox: un
     parte puede llevar varios trabajos, que es justamente el cambio que este componente
     habilita. No se reusó `chip-radio` con `type=checkbox` porque su `name` no lleva `[]` y un
     radio y un checkbox no comparten semántica de formulario.

     Muestra el trabajo SIN su remate (el remate se elige una vez al final de la frase) y sus
     horas a la derecha, porque el técnico necesita ver qué está sumando.

     Los atributos extra (x-model, x-on:change) van al input. --}}
@props(['name', 'value', 'label', 'horas' => null, 'checked' => false])

<label class="block cursor-pointer">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked)
           {{ $attributes->merge(['class' => 'peer sr-only']) }}>
    <span class="flex min-h-12 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-left text-sm font-medium text-neutral-700 shadow-sm transition duration-150 active:scale-[0.98] peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-1 peer-checked:ring-inset peer-checked:ring-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">
        <span class="flex-1">{{ $label }}</span>
        @if ($horas !== null)
            {{-- El color se HEREDA del chip (solo se baja la opacidad) en vez de pintarse con
                 una variante de estado: esta etiqueta es DESCENDIENTE del hermano del input, no
                 hermana, así que las variantes que dependen del input marcado no la alcanzarían
                 nunca — se compilarían a una regla que no matchea y el override quedaría inerte,
                 en silencio. Heredando, sigue al chip en los dos estados sola.

                 (Y el nombre de la utilidad de color heredado NO se escribe acá: Tailwind
                 escanea este archivo entero, comentarios incluidos, y nombrarla la mete al
                 bundle sin que ninguna plantilla la use — bitácora [2026-07-30].) --}}
            <span class="shrink-0 text-xs font-normal tabular-nums opacity-60">{{ $horas }} h</span>
        @endif
    </span>
</label>
