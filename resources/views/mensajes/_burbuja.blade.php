{{-- UNA burbuja del hilo (MSG-5): la MISMA para el render inicial de show y
     para el endpoint `nuevos` que appendea sin reload — cero divergencia
     visual por construcción. El texto SIEMPRE por {{ }} (lo escribe un
     usuario); recibe $mensaje y $usuario. --}}
@php $esMio = $mensaje->emisor_id === $usuario->id; @endphp
<div class="flex {{ $esMio ? 'justify-end' : 'justify-start' }}" data-mensaje-id="{{ $mensaje->id }}">
    <div class="max-w-[85%] rounded-2xl px-4 py-2.5 {{ $esMio ? 'bg-brand-50 text-brand-900 ring-1 ring-inset ring-brand-100' : 'bg-neutral-100 text-neutral-900' }}">
        <p class="whitespace-pre-line break-words text-sm">{{ $mensaje->texto }}</p>
        <p class="mt-1 text-xs {{ $esMio ? 'text-brand-700/70' : 'text-neutral-400' }}">
            @unless ($esMio){{ $mensaje->emisor?->name ?? '—' }} · @endunless{{ $mensaje->created_at?->enChile()->format('d-m H:i') }}
        </p>
    </div>
</div>
