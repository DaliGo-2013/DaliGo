{{--
    Cierre del taller (dueño 07-08): el TÉCNICO le avisa al cliente que su equipo
    está listo y que pague en SALA DE VENTAS al retirar. Sirve igual para
    reparación (con el monto que el cliente aceptó) y para garantía (sin costo).

    Un aviso por orden: después queda la constancia de quién avisó y cuándo.
    Espera la orden en «Reparado» — «está listo» significa trabajo cerrado con su
    causa de la falla, y eso lo garantiza el parte del técnico.
--}}
@php
    $aceptada = $orden->cotizaciones()->where('estado', 'aceptada')->latest('id')->first();
    $esGarantiaRetiro = $orden->condicion_efectiva === 'garantia';
    $cobro = match (true) {
        $esGarantiaRetiro => 'La carta dice «sin costo — cubierto por la garantía».',
        $aceptada !== null => 'La carta lleva el total que el cliente aceptó ($'.number_format((int) $aceptada->costo_total, 0, ',', '.').') y lo manda a pagar a sala de ventas.',
        default => 'Sin cotización aceptada: la carta manda a coordinar el costo en sala de ventas.',
    };
@endphp
<div class="mt-5 rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm sm:p-6">
    <h3 class="text-sm font-semibold text-neutral-900">Listo para retirar</h3>

    @if ($orden->listo_avisado_at)
        <div class="mt-2 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-700">
            <p class="font-semibold">Ya se le avisó al cliente</p>
            <p class="mt-0.5">
                A {{ $orden->cliente_email }} el {{ $orden->listo_avisado_at->format('d-m-Y H:i') }}
                (avisó {{ $orden->listoAvisadoPor?->name ?? '—' }}). Paga en sala de ventas al retirar.
            </p>
        </div>
    @elseif ($orden->estado === 'reparado' && filled($orden->cliente_email))
        <p class="mt-2 text-sm text-neutral-600">
            Avísale al cliente que puede pasar a retirar su equipo. {{ $cobro }}
        </p>
        <form method="POST" action="{{ route('admin.servicio-tecnico.listo-para-retiro', $orden) }}" class="mt-3" data-una-vez
              onsubmit="return confirm('Se le avisará a {{ $orden->cliente_email }} que su equipo está listo para retirar. ¿Continuar?');">
            @csrf
            <x-primary-button type="submit">Avisar que está listo para retirar</x-primary-button>
            <span class="ml-2 text-xs text-neutral-400">Un solo aviso; queda registrado quién avisó.</span>
        </form>
    @else
        <p class="mt-2 text-sm text-neutral-500">
            @if (blank($orden->cliente_email))
                La orden no tiene correo del cliente (agrégalo en la recepción): hay que llamarlo.
            @else
                Cuando termines el trabajo, marca la orden como «Reparado» en Parte del técnico y acá le avisas al cliente que puede retirar.
            @endif
        </p>
    @endif
</div>
