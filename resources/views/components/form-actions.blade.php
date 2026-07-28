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
{{-- En CELULAR el ícono de la cabecera no alcanza: la banda del encabezado no
     es sticky, así que tras llenar ~14 campos el técnico tiene que subir toda
     la página hasta un cuadrado naranja que no dice qué hace. Abajo va la misma
     acción, rotulada y fija sobre la barra de gestos. En desktop no cambia
     nada: sigue siendo el ícono del encabezado y la doctrina del botón único.

     El `hidden` va en un wrapper y NO en el class del x-icon-button: el merge
     de atributos CONCATENA y entre `hidden` e `inline-flex` gana el que ordene
     el CSS, no el que se escriba último (mismo gotcha que documenta
     icon-button.blade.php). --}}
<div class="hidden items-center gap-2 sm:flex">
    <x-icon-button type="submit" :form="$form" size="lg" variant="primary" :label="$submitLabel" :title="$submitLabel">
        <x-icon.check class="h-5 w-5" />
    </x-icon-button>
</div>

{{-- z-20: por debajo del scrim del drawer (z-30) y de los modales (z-50), para
     que cualquiera de los dos la tape cuando corresponde.
     La clase `dg-barra-accion` la usa app.css para darle aire al cuerpo. --}}
<div class="dg-barra-accion fixed inset-x-0 bottom-0 z-20 border-t border-neutral-200 bg-white/95 px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur sm:hidden">
    <x-primary-button type="submit" :form="$form" class="h-12 w-full">{{ $submitLabel }}</x-primary-button>
</div>
