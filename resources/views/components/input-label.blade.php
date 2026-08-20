@props(['value'])

{{-- Etiqueta de campo, con AYUDA OPCIONAL detrás de una ⓘ.

     Regla de diseño (dueño, 17-08-2026: «dejarlo como un icono informativo y no como texto
     plano en la app»): una explicacion larga —mas de una linea— no va como parrafo gris debajo
     del campo, va en el globo de la ⓘ. Como en el listado de Inventario. El formulario se lee
     de un vistazo y la explicacion sigue estando, a un toque.

     Se usa asi:
         <x-input-label for="x" value="Titulo">
             <x-slot:ayuda>La explicacion larga.</x-slot:ayuda>
         </x-input-label>

     LA ⓘ VA COMO HERMANA DEL <label>, NUNCA ADENTRO: un <button> dentro de un <label> es
     interactivo-dentro-de-interactivo — al tocar la ayuda el navegador ADEMAS enfoca o
     conmuta el campo (mismo criterio que la ⓘ de las tarjetas-enlace, ver «Ayuda contextual»
     en CLAUDE.md). Por eso, cuando hay ayuda, el componente emite una fila. --}}

@isset($ayuda)
    <div class="flex items-center gap-1.5">
        <label {{ $attributes->merge(['class' => 'block text-sm font-medium text-neutral-700']) }}>
            {{ $value ?? $slot }}
        </label>
        <x-info-tip>{{ $ayuda }}</x-info-tip>
    </div>
@else
    <label {{ $attributes->merge(['class' => 'block text-sm font-medium text-neutral-700']) }}>
        {{ $value ?? $slot }}
    </label>
@endisset
