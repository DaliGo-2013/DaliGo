{{--
    Ayuda del N° de serie (SOLO dispensadores): un boton CLARO y visible, ubicado
    JUSTO DEBAJO del campo N° de serie, que abre un modal con la FOTO de ejemplo
    (etiqueta trasera del dispensador). Si aun no esta la foto real
    (public/img/ejemplo-serie.jpg) muestra una ilustracion de respaldo, asi nunca
    queda una imagen rota.

    Solo aparece cuando el "Tipo de equipo" (#tipo_equipo) es "dispensador" —
    bombas/herramientas no tienen serie unica. FAIL-OPEN: el boton se ve por
    defecto (sin x-cloak); si el equipo no es dispensador, Alpine lo oculta.
--}}
<div x-data="{
        abierto: false,
        esDispensador: true,
        init() {
            const sel = document.getElementById('tipo_equipo');
            const actualizar = () => {
                this.esDispensador = !sel || sel.value === 'dispensador';
                if (!this.esDispensador) this.abierto = false;
            };
            actualizar();
            if (sel) sel.addEventListener('change', actualizar);
        },
     }" x-show="esDispensador">
    <button type="button" @click="abierto = true"
        class="mt-2 inline-flex items-center gap-2 rounded-lg border border-brand-300 bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-brand-100 active:scale-[0.99]">
        <x-icon.eye class="h-5 w-5" />
        Ver ejemplo del N° de serie
    </button>

    {{-- Modal con la foto/ilustracion de ejemplo --}}
    <div x-show="abierto" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4"
         @click.self="abierto = false" @keydown.escape.window="abierto = false">
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl" x-transition>
            <h3 class="text-sm font-semibold text-neutral-900">¿Dónde está el N° de serie?</h3>
            <p class="mt-1 text-xs text-neutral-500">
                En el dispensador está en la <span class="font-medium">etiqueta trasera</span> y empieza con <span class="font-medium">«EST»</span>. Escríbelo tal cual.
            </p>

            <div class="mt-3 overflow-hidden rounded-lg border border-neutral-200">
                @php
                    // La foto va EMBEBIDA como data URI (base64) leyendo el archivo del
                    // filesystem de la app. Asi NO depende de que el servidor sirva
                    // /img/ como estatico (el docroot de staging no lo hace -> 404); la
                    // imagen viaja dentro del HTML y siempre se ve.
                    $rutaEjemploSerie = public_path('img/ejemplo-serie.jpg');
                @endphp
                @if (is_file($rutaEjemploSerie))
                    <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($rutaEjemploSerie)) }}" alt="Ejemplo: etiqueta trasera del dispensador con el N° de serie" class="block w-full">
                @else
                    {{-- Ilustración de respaldo mientras no esté la foto real. --}}
                    <svg viewBox="0 0 300 180" class="block w-full" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ejemplo del número de serie">
                        <rect x="8" y="8" width="284" height="164" rx="8" fill="#f5f5f5" stroke="#e5e5e5"/>
                        <rect x="70" y="22" width="180" height="58" rx="4" fill="#ffffff" stroke="#d4d4d4"/>
                        <text x="160" y="38" text-anchor="middle" font-size="8" font-weight="bold" fill="#404040">DISPENSADOR DALI · LB-07D</text>
                        <text x="160" y="50" text-anchor="middle" font-size="6.5" fill="#a3a3a3">220V · 50Hz · Fabricado en China</text>
                        <line x1="82" y1="56" x2="238" y2="56" stroke="#ececec"/>
                        <text x="160" y="70" text-anchor="middle" font-size="6.5" fill="#a3a3a3">Tensión / Potencia / Clase climática…</text>
                        <rect x="76" y="100" width="168" height="42" rx="5" fill="#fff7ed" stroke="#ea580c" stroke-width="2"/>
                        <text x="160" y="116" text-anchor="middle" font-size="7.5" font-weight="bold" fill="#9a3412">N° DE SERIE</text>
                        <text x="160" y="132" text-anchor="middle" font-size="12" font-weight="bold" fill="#c2410c" font-family="monospace">EST20260100251</text>
                        <text x="160" y="162" text-anchor="middle" font-size="8" fill="#525252">↑ Etiqueta trasera del equipo</text>
                    </svg>
                @endif
            </div>

            <button type="button" @click="abierto = false"
                class="mt-4 w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                Entendido
            </button>
        </div>
    </div>
</div>
