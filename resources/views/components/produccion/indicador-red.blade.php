{{-- Indicador de red del operario (spike PWA, P-SPK-01). Informa, no bloquea:
     lee Alpine.store('red') (resources/js/app.js). El envio de un form estando
     offline aun muestra el error nativo del navegador — la cola offline que lo
     resuelve es P-SPK-02. Tono neutral oscuro (estado informativo, no error). --}}
<div x-data x-cloak x-show="! $store.red.online"
     {{ $attributes->merge(['class' => 'mb-4 flex items-center gap-2 rounded-lg bg-neutral-800 px-3 py-2.5 text-sm font-medium text-white']) }}
     role="status">
    <span class="h-2 w-2 shrink-0 rounded-full bg-white/60" aria-hidden="true"></span>
    Sin conexión — espera la señal antes de enviar.
</div>
