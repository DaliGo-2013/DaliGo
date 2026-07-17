{{--
    BOCETO interno: cómo verá el cliente el estado de su equipo en el taller
    (estilo Blue Express). Datos de ejemplo, sin conexión a Servicio Técnico ni a
    búsqueda del cliente. El conmutador (Alpine) permite recorrer las etapas en
    vivo durante la demo y alternar el escenario "Sin solución".
--}}
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Seguimiento del equipo (boceto)" subtitle="Vista de prueba de cómo se le avisará al cliente. Aún no conectado.">
            <x-slot name="action">
                <x-icon-button :href="route('admin.servicio-tecnico.index')" size="lg" variant="secondary" label="Volver" title="Volver">
                    <x-icon.arrow-left class="h-5 w-5" />
                </x-icon-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6" x-data="{ esc: 'reparacion', i: 2, k: 3 }">
            {{-- Aviso de boceto --}}
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="font-semibold">Boceto / vista de prueba.</span>
                Datos de ejemplo. Todavía no está conectado a Servicio Técnico ni a la búsqueda del cliente — es solo un adelanto del diseño.
            </div>

            {{-- Controles SOLO del demo (no van en la vista real del cliente) --}}
            <div class="mb-6 space-y-3 rounded-xl border border-neutral-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-neutral-500">Simular etapa (solo demo)</p>
                <div class="flex flex-wrap gap-2" x-show="esc === 'reparacion'">
                    @foreach ($pasos as $n => $p)
                        <button type="button" x-on:click="i = {{ $n }}"
                            class="rounded-full border px-3 py-1 text-xs transition"
                            :class="i === {{ $n }} ? 'border-brand-600 bg-brand-600 text-white' : 'border-neutral-200 bg-white text-neutral-600 hover:border-brand-300'">
                            {{ $p['label'] }}
                        </button>
                    @endforeach
                </div>
                <div class="pt-1">
                    <button type="button" x-on:click="esc = (esc === 'reparacion' ? 'sin_solucion' : 'reparacion')"
                        class="rounded-lg border border-neutral-200 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 transition hover:border-brand-300">
                        <span x-text="esc === 'reparacion' ? 'Ver escenario: Sin solución' : '← Volver a: Reparación normal'"></span>
                    </button>
                </div>
            </div>

            {{-- Tarjeta con el look del futuro cliente (mobile-first) --}}
            <div class="mx-auto max-w-sm rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="mb-5 border-b border-neutral-100 pb-4">
                    <h2 class="text-lg font-bold tracking-tight text-neutral-900">Seguimiento de tu equipo</h2>
                    <p class="mt-1 font-mono text-sm font-semibold text-brand-600">ST-YQUW6P4E</p>
                    <p class="mt-1 text-xs text-neutral-500">Dispensador · Código 1040001 · N° Est2019233456</p>
                </div>

                {{-- Escenario 1: reparación (recibido → entregado) --}}
                <div x-show="esc === 'reparacion'">
                    <x-st.seguimiento-timeline :pasos="$pasos" curVar="i" />
                </div>

                {{-- Escenario 2: sin solución (cierre negativo, en rojo) --}}
                <div x-show="esc === 'sin_solucion'" x-cloak>
                    <x-st.seguimiento-timeline :pasos="$pasosSinSolucion" curVar="k" />
                </div>
            </div>

            <p class="mx-auto mt-4 max-w-sm text-center text-xs text-neutral-400">
                A futuro el cliente abrirá esta vista con el folio de su equipo y verá su etapa en tiempo real.
            </p>
        </div>
    </div>
</x-app-layout>
