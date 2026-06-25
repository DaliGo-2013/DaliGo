@props([
    'cancel' => null,            // URL de cancelar (vuelve al listado); si es null no se muestra la X
    'back' => null,              // URL de volver (flecha); si es null no se muestra
    'form' => null,              // id del <form> que envía (submit asociado por atributo form=)
    'submitLabel' => 'Guardar',
    'cancelLabel' => 'Cancelar',
])

{{-- Acciones del formulario como íconos, pensadas para ir arriba (slot action del
     page-header). El submit vive fuera del <form> y se asocia por el atributo form. --}}
<div class="flex items-center gap-2">
    @isset($back)
        <x-icon-button :href="$back" size="lg" variant="secondary" label="Volver" title="Volver">
            <x-icon.arrow-left class="h-5 w-5" />
        </x-icon-button>
    @endisset
    @isset($cancel)
        <x-icon-button :href="$cancel" size="lg" variant="secondary" :label="$cancelLabel" :title="$cancelLabel">
            <x-icon.x-mark class="h-5 w-5" />
        </x-icon-button>
    @endisset
    <x-icon-button type="submit" :form="$form" size="lg" variant="primary" :label="$submitLabel" :title="$submitLabel">
        <x-icon.check class="h-5 w-5" />
    </x-icon-button>
</div>
