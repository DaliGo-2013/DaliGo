@props(['label' => 'Enviar'])

{{-- Barra de acción fija al pie SOLO en móvil: el botón de envío del formulario
     del QR queda siempre visible sin scrollear hasta el final (el cliente está
     de pie en el mostrador, con una mano). Desde sm: vuelve a ser el botón
     inline de siempre dentro de la tarjeta.

     El padding inferior respeta la barra de gestos del iPhone
     (env(safe-area-inset-bottom)) para que no tape el botón.

     Va DENTRO del <form>: envía por type=submit sin depender de un id. Recuerda
     dar clearance al contenido de abajo (pb-[calc(6rem_+_env(safe-area-inset-bottom))] sm:pb-0)
     para que "Volver al inicio" no quede detrás de la barra al hacer scroll. --}}
<div {{ $attributes->merge(['class' => 'fixed inset-x-0 bottom-0 z-30 border-t border-neutral-200 bg-white px-4 pt-3 pb-[calc(0.75rem_+_env(safe-area-inset-bottom))] shadow-[0_-2px_8px_rgba(0,0,0,0.06)] sm:static sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none']) }}>
    <button type="submit"
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3 text-base font-semibold text-white shadow-sm transition duration-150 hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40 active:scale-[0.99]">
        {{ $slot->isEmpty() ? $label : $slot }}
    </button>
</div>
