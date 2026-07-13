{{-- Fotos de recepción (respaldo del estado físico del equipo). Servidas con
     sesión (disco privado); se usa en el detalle y al editar la orden. Al tocar
     una miniatura se abre un visor (lightbox) dentro de la app, con X / Esc /
     clic-afuera para cerrar. Solo aparece si la orden tiene fotos. Requiere $orden. --}}
@if ($orden->fotos->isNotEmpty())
    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm" x-data="{ abierta: null }">
        <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Fotos del equipo (recepción)</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($orden->fotos as $foto)
                <button type="button" @click="abierta = '{{ route('admin.servicio-tecnico.foto', $foto) }}'"
                        class="block w-full overflow-hidden rounded-lg border border-neutral-200 p-0">
                    <img src="{{ route('admin.servicio-tecnico.foto', $foto) }}" loading="lazy"
                         alt="Foto del equipo al recibirlo"
                         class="aspect-square w-full object-cover transition hover:opacity-90">
                </button>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-neutral-400">Respaldo del estado físico al ingresar. Toca una foto para verla más grande.</p>

        {{-- Visor (lightbox): imagen grande, se cierra con la X, Esc o clic afuera. --}}
        <div x-show="abierta" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/80 p-4"
             @click.self="abierta = null" @keydown.escape.window="abierta = null">
            <button type="button" @click="abierta = null"
                    class="absolute right-4 top-4 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                    aria-label="Cerrar">
                <x-icon.x-mark class="h-6 w-6" />
            </button>
            <img :src="abierta" alt="Foto del equipo" class="max-h-[85vh] max-w-full rounded-lg shadow-xl">
        </div>
    </div>
@endif
