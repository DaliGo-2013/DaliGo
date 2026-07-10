{{-- Fotos de recepción (respaldo del estado físico del equipo). Servidas con
     sesión (disco privado); se usa en el detalle y al editar la orden. Solo
     aparece si la orden tiene fotos. Requiere $orden. --}}
@if ($orden->fotos->isNotEmpty())
    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Fotos del equipo (recepción)</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($orden->fotos as $foto)
                <a href="{{ route('admin.servicio-tecnico.foto', $foto) }}" target="_blank" rel="noopener"
                   class="block overflow-hidden rounded-lg border border-neutral-200">
                    <img src="{{ route('admin.servicio-tecnico.foto', $foto) }}" loading="lazy"
                         alt="Foto del equipo al recibirlo"
                         class="aspect-square w-full object-cover transition hover:opacity-90">
                </a>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-neutral-400">Respaldo del estado físico al ingresar. Toca una foto para verla completa.</p>
    </div>
@endif
