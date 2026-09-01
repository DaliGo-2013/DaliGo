{{-- Chip TÁCTIL de selección múltiple para marcar trabajos del catálogo (28-08-2026).

     Hermano de `chip-radio` (mismo peer-checked, mismo ≥48px táctil) pero con checkbox: un
     parte puede llevar varios trabajos, que es justamente el cambio que este componente
     habilita. No se reusó `chip-radio` con `type=checkbox` porque su `name` no lleva `[]` y un
     radio y un checkbox no comparten semántica de formulario.

     Muestra el trabajo SIN su remate (el remate se elige una vez al final de la frase) y NADA
     MÁS. Nació mostrando también sus horas a la derecha; el dueño las mandó a sacar el
     01-09-2026 («no le pongas hora a todos los arreglos porque va a generar un problema cuando
     se sume al cobro total»): el técnico no decide ni edita esas horas, así que 21 números
     sueltos solo invitaban a sumarlos mentalmente y a temer un acumulado que el tope del taller
     nunca permitió. El único número de la pantalla es ahora el que se va a cobrar.

     Los atributos extra (x-model, x-on:change) van al input. --}}
@props(['name', 'value', 'label', 'checked' => false])

<label class="block cursor-pointer">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked)
           {{ $attributes->merge(['class' => 'peer sr-only']) }}>
    <span class="flex min-h-12 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-left text-sm font-medium text-neutral-700 shadow-sm transition duration-150 active:scale-[0.98] peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-1 peer-checked:ring-inset peer-checked:ring-brand-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">
        <span class="flex-1">{{ $label }}</span>
    </span>
</label>
