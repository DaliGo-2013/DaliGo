{{-- Selector de motivo táctil para operarios: una grilla de chips (sobre
     <x-chip-radio>) en vez de un <select> o texto libre. Si `allowOther`, agrega
     un chip "Otro" que revela un campo de texto como salida de escape.
     - name: nombre del campo (el texto libre viaja en "{name}_otro").
     - options: array de strings (cada uno es un chip).
     - selected: valor actual / old(); si no está en options y no es vacío, se
       asume que fue un "Otro" y precarga su texto.
     - label, allowOther (bool), cols (2 ó 3). --}}
@props([
    'name',
    'options' => [],
    'selected' => null,
    'label' => null,
    'allowOther' => false,
    'cols' => 2,
])

@php
    $otro = \App\Models\ProduccionReporte::MOTIVO_OTRO;
    $selectedStr = (string) ($selected ?? '');
    $known = $selectedStr !== '' && in_array($selectedStr, $options, true);
    $isOther = $allowOther && $selectedStr !== '' && ! $known;
    $initialSel = $isOther ? $otro : ($known ? $selectedStr : '');
    $initialOther = $isOther ? $selectedStr : '';
    $gridClass = (int) $cols === 3
        ? 'grid grid-cols-2 gap-2 sm:grid-cols-3'
        : 'grid grid-cols-2 gap-2';
@endphp

<div {{ $attributes }} x-data="{ sel: @js($initialSel) }">
    @if ($label)
        <x-input-label :value="$label" />
    @endif

    <div class="{{ $label ? 'mt-1.5 ' : '' }}{{ $gridClass }}">
        @foreach ($options as $op)
            <x-chip-radio :name="$name" :value="$op" :label="$op"
                          :checked="$op === $selectedStr" x-model="sel" />
        @endforeach

        @if ($allowOther)
            <x-chip-radio :name="$name" :value="$otro" label="Otro"
                          :checked="$isOther" x-model="sel" />
        @endif
    </div>

    @if ($allowOther)
        <div x-show="sel === @js($otro)" @if (! $isOther) x-cloak @endif class="mt-2">
            <x-text-input type="text" :name="$name.'_otro'" :value="$initialOther"
                          maxlength="255" placeholder="Escribe el motivo…" />
        </div>
    @endif

    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
