@props([
    'form' => null,              // id del <form> que envía (submit asociado por atributo form=)
    'submitLabel' => 'Guardar',
])

{{-- Acciones del formulario para el slot `action` del page-header: hoy solo el
     submit, como ícono. El submit vive FUERA del <form> y se asocia por el
     atributo form=.

     Ya NO lleva "Cancelar" ni "Volver" (doctrina del botón único, 24-07): la
     única salida de un formulario es el <x-volver> del encabezado. Antes este
     componente ofrecía las dos cosas —una X rotulada "Cancelar" y una flecha—
     que hacían exactamente lo mismo (salir sin guardar) y terminaron repartidas
     sin criterio: 10 vistas usaban la X y 1 la flecha. El candado
     `no_quedan_formas_viejas` de VolverTest impide que vuelvan. --}}
<div class="flex items-center gap-2">
    <x-icon-button type="submit" :form="$form" size="lg" variant="primary" :label="$submitLabel" :title="$submitLabel">
        <x-icon.check class="h-5 w-5" />
    </x-icon-button>
</div>
