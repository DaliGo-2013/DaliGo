@props(['aviso' => null])

{{-- Mini-notificacion de BLOQUEO (403/404): la escribe el render() de
     bootstrap/app.php cuando manda al usuario al Inicio, y explica por que la
     pantalla se movio.

     Usa una clave de sesion PROPIA ('aviso'), distinta de session('status'):
     `status` lo pintan 35 vistas a mano con <x-status-alert> y el dashboard NO
     lo tiene (un flash que aterrice ahi se pierde en silencio). Con un canal
     aparte, esto se renderiza UNA sola vez en layouts/app.blade.php, cubre el
     dashboard y las ~40 vistas sin status-alert, y no duplica nada.

     Color BRAND, no rojo: la paleta reserva el rojo para destructivo/negativo
     (eliminar, validacion, devuelto). Un 403 no es culpa del usuario ni destruye
     nada; la regla de intencion dice "requiere accion / atencion -> brand".

     Persistente con boton de cierre (sin auto-cierre): es la explicacion de por
     que estas en el Inicio — si se esconde solo, quien mire el celular tarde
     queda sin saber que paso. Muere igual en la siguiente navegacion. --}}
@if ($aviso)
    <div data-aviso x-data="{ visible: true }" x-show="visible"
         class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
        <div role="status" aria-live="polite"
             class="dg-enter flex items-start gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 shadow-sm">
            <x-icon.information-circle class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
            <p class="min-w-0 flex-1 text-sm font-medium text-neutral-800">{{ $aviso }}</p>
            <button type="button" x-on:click="visible = false"
                    class="-me-2 -mt-2 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-brand-600 transition duration-150 hover:bg-brand-100 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                <x-icon.x-mark class="h-5 w-5" />
                <span class="sr-only">Cerrar aviso</span>
            </button>
        </div>
    </div>
@endif
