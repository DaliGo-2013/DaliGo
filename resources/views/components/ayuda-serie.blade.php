{{--
    Ayuda del N° de serie (SOLO dispensadores): un enlace "ver ejemplo" que
    despliega una ilustración de dónde está la serie (etiqueta trasera, empieza
    con "EST"). Bombas y herramientas no tienen N° de serie único, por eso solo
    aparece cuando el "Tipo de equipo" (select #tipo_equipo) es "dispensador".

    Autocontenido: en init() lee el select por id y reacciona a su cambio, sin
    depender del x-data del formulario. FAIL-OPEN: el enlace se muestra por
    defecto (sin x-cloak en el contenedor); si el equipo no es dispensador Alpine
    lo oculta. Solo el bloque del ejemplo (SVG) usa x-cloak para no aparecer al cargar.
--}}
<div class="mt-1.5" x-show="esDispensador"
     x-data="{
        verEjemplo: false,
        esDispensador: true,
        init() {
            const sel = document.getElementById('tipo_equipo');
            const actualizar = () => {
                this.esDispensador = !sel || sel.value === 'dispensador';
                if (!this.esDispensador) this.verEjemplo = false;
            };
            actualizar();
            if (sel) sel.addEventListener('change', actualizar);
        },
     }">
    <button type="button" @click="verEjemplo = !verEjemplo"
        class="text-xs font-medium text-brand-600 underline hover:text-brand-700">
        ¿Dónde encuentro el N° de serie? Ver ejemplo
    </button>

    <div x-show="verEjemplo" x-cloak x-transition
         class="mt-2 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
        <p class="mb-2 text-xs text-neutral-600">
            En los dispensadores, el N° de serie está en la <span class="font-medium">etiqueta trasera</span>
            y empieza con <span class="font-medium">«EST»</span>. Escríbelo tal cual aparece.
        </p>

        {{-- Ilustración liviana (SVG en línea): etiqueta trasera con la serie resaltada. --}}
        <svg viewBox="0 0 300 180" class="mx-auto block w-full max-w-[280px]" xmlns="http://www.w3.org/2000/svg"
             role="img" aria-label="Ejemplo: etiqueta trasera del dispensador con el número de serie">
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
    </div>
</div>
