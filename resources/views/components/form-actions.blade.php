@props([
    'cancel',                    // URL de cancelar (vuelve al listado)
    'form' => null,              // id del <form> que envía (submit asociado por atributo form=)
    'submitLabel' => 'Guardar',
    'cancelLabel' => 'Cancelar',
])

{{-- Acciones del formulario como íconos, pensadas para ir arriba (slot action del
     page-header). El submit vive fuera del <form> y se asocia por el atributo form. --}}
<div class="flex items-center gap-2">
    <x-icon-button :href="$cancel" size="lg" variant="secondary" :label="$cancelLabel" :title="$cancelLabel">
        <x-icon.x-mark class="h-5 w-5" />
    </x-icon-button>
    <x-icon-button type="submit" :form="$form" size="lg" variant="primary" :label="$submitLabel" :title="$submitLabel">
        <x-icon.check class="h-5 w-5" />
    </x-icon-button>
</div>
