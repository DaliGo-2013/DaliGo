import './bootstrap';

import Alpine from 'alpinejs';
import { encolar, pendientes, iniciarColaOffline } from './offline-queue';

// Cola offline de tandas (spike P-SPK-02). Se expone en window porque el x-data
// del form del soplador es inline en el Blade y no puede importar el modulo.
window.dgCola = { encolar, pendientes };

/**
 * "Señalar en vez de narrar": ante una acción bloqueada por una precondición, en
 * lugar de dejar un texto rojo lejano, llevamos la mirada al control exacto que
 * falta. dgDestacar() hace scroll hasta el elemento y reinicia la animación de
 * feedback sobre él:
 *   - ring:true  -> `.dg-destacado` (sacude + anillo rojo breve). Para un control
 *                   concreto (un colapsable, unos chips, un botón).
 *   - ring:false -> reusa `.dg-shake`. Para el texto de error (un anillo alrededor
 *                   de una lista se ve mal); solo lo sacude al llegar.
 * Respeta prefers-reduced-motion (scroll sin animar; el CSS ya recorta la duración
 * de las animaciones). El truco `void offsetWidth` fuerza un reflow para poder
 * re-disparar una animación que ya corrió.
 */
function dgDestacar(el, { ring = true } = {}) {
    if (!el) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    const cls = ring ? 'dg-destacado' : 'dg-shake';
    el.classList.remove(cls);
    void el.offsetWidth; // reflow: reinicia la animación aunque ya haya corrido
    el.classList.add(cls);
    if (ring) window.setTimeout(() => el.classList.remove(cls), 1400);
}
window.dgDestacar = dgDestacar;
Alpine.magic('destacar', () => (el) => dgDestacar(el)); // uso en vistas: $destacar($refs.x)

/**
 * Anti doble-envío. En formularios con [data-una-vez], al enviarse se
 * deshabilitan sus botones de submit —incluidos los EXTERNOS ligados por
 * form="<id>" (ej. el ✓ del mostrador)— para que un segundo clic (típico en
 * móvil con conexión lenta) NO cree órdenes ni correos duplicados. Si la
 * validación HTML5 falla, el evento submit no se dispara, así que el usuario
 * puede corregir y reenviar sin quedar bloqueado.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-una-vez')) return;
    if (form.dataset.enviando) { e.preventDefault(); return; }
    form.dataset.enviando = '1';
    setTimeout(() => {
        const propios = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])'));
        const externos = form.id ? Array.from(document.querySelectorAll(`[type="submit"][form="${form.id}"]`)) : [];
        [...propios, ...externos].forEach((b) => { b.disabled = true; b.style.opacity = '0.6'; b.style.pointerEvents = 'none'; });
    }, 0);
}, true);

/**
 * "Volver" unico (doctrina del dueno, 2026-07-24). Cada <x-volver> lleva un href
 * REAL a la pantalla padre: ese es el destino garantizado, lo que abre el
 * ctrl/cmd-clic y lo que pasa si este script no corre. Este handler solo agrega
 * una mejora: si la pagina anterior YA es ese mismo padre, usa el historial en
 * vez de navegar, y asi el listado vuelve con su scroll y su mes abierto. Antes
 * esto era un onclick copiado a mano en 5 vistas — y faltaba justo en una
 * hermana (reparacion, que si lo necesitaba).
 *
 * Compara SOLO el pathname a proposito: el href es /admin/agenda-terreno y el
 * referrer /admin/agenda-terreno?anio=2026&mes=7 — ese query string es
 * precisamente el estado que queremos recuperar. Si el referrer es otra pantalla
 * (llegaste desde el Inicio o desde una notificacion) no se toca el clic: el
 * usuario termina en el listado igual, que es el destino predecible.
 */
document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    const enlace = e.target.closest('a[data-dg-volver]');
    if (!enlace || !document.referrer || window.history.length <= 1) return;

    try {
        const destino = new URL(enlace.href);
        const anterior = new URL(document.referrer);
        if (anterior.origin === window.location.origin && anterior.pathname === destino.pathname) {
            e.preventDefault();
            window.history.back();
        }
    } catch (err) {
        // href o referrer no parseable: se sigue el enlace normal.
    }
});

/**
 * Panel flotante que SIEMPRE cabe (doctrina del dueno, 2026-07-26).
 *
 * El problema que resuelve: un panel `absolute` anclado con `start-0`/`end-0`
 * apuesta a que del lado elegido sobra espacio, y esa apuesta se hace al
 * escribir la vista, cuando todavia no se sabe donde va a caer el disparador ni
 * de que ancho es la pantalla. Cuando falla, el panel se sale por ese lado y el
 * contenido queda inalcanzable: la campanita (w-80 = 320px anclada `end-0`
 * dentro de una sidebar de 264px) perdia 72px por la izquierda. Es el MISMO
 * error que la bitacora ya habia diagnosticado el 2026-07-01 en los globos ⓘ
 * ("el align es estatico y no se puede acertar"); alla se arreglo a mano, aca
 * se arregla el mecanismo.
 *
 * Que hace: al abrirse, MIDE y decide.
 *   1. Horizontal: el panel nace hacia el lado con mas espacio libre.
 *   2. Si aun asi se sale, lo corre hacia adentro.
 *   3. Vertical: si no cabe abajo lo voltea sobre el disparador; si no cabe en
 *      ninguno de los dos lados, lo deja scrollear por dentro.
 * El paso 1 es el que reemplaza al `align` escrito a mano, y no es una
 * heuristica arbitraria: reproduce exactamente lo que las vistas ya elegian a
 * dedo (un ⓘ en la esquina de una tarjeta cae a la derecha del centro y abre
 * hacia la izquierda; uno pegado a un titulo cae a la izquierda y abre hacia la
 * derecha), con la diferencia de que ahora se acierta solo en cualquier ancho.
 *
 * Por que directiva y no estado del componente: asi sirve igual al x-dropdown
 * (abre por click) y al x-info-tip (abre por hover en desktop, con su
 * single-open por evento de ventana) SIN tocar la logica de apertura de
 * ninguno de los dos.
 *
 * Si el panel es `position: fixed` no hace nada: ese es el caso "ya anclado al
 * viewport" (la hoja inferior del ⓘ en movil), que no se puede desbordar.
 */
Alpine.directive('dg-anclar', (el, {}, { cleanup }) => {
    const MARGEN = 8; // respiro minimo contra el borde de la pantalla
    const ALTO_MINIMO = 160; // por debajo de esto el panel deja de ser util

    // La correccion se escribe en `left`/`top`, NO en `translate` ni en
    // `transform`: Tailwind v4 compila `scale-95` y `translate-y-1` a las
    // propiedades independientes `scale`/`translate`, y los dos componentes las
    // usan en su x-transition. Un estilo inline gana sobre la clase, asi que
    // escribir ahi apagaria la animacion de apertura (y es primo del gotcha de
    // la bitacora [2026-07-22], donde x-transition dejo estilos inline pegados).
    const limpiar = () => {
        el.style.left = '';
        el.style.right = '';
        el.style.top = '';
        el.style.bottom = '';
        el.style.maxHeight = '';
        el.style.overflowY = '';
    };

    const colocar = () => {
        const estilo = window.getComputedStyle(el);
        if (estilo.display === 'none') return; // cerrado: nada que medir
        if (estilo.position === 'fixed') { limpiar(); return; }

        // Volver a la posicion que manda el CSS antes de medir: si no, cada
        // reposicion mediria sobre la correccion anterior y se acumularian.
        limpiar();

        const contenedor = el.offsetParent;
        if (!contenedor) return;

        // Se mide en coordenadas de LAYOUT (offset*) y no con
        // getBoundingClientRect(): durante la animacion de apertura el panel
        // lleva `scale: 95%` y el rect devolveria la caja ESCALADA — 5% menos
        // de ancho y un borde corrido. Los offset* ignoran scale/translate, asi
        // que la medida es la misma en el primer frame y en el ultimo.
        const base = contenedor.getBoundingClientRect(); // = la caja del disparador
        const offsetX = el.offsetLeft;
        const offsetY = el.offsetTop;
        const ancho = el.offsetWidth;
        const alto = el.offsetHeight;
        const izquierda = base.left + offsetX; // borde izq. del panel en pantalla
        const arriba = base.top + offsetY;

        const vpAncho = document.documentElement.clientWidth;
        const vpAlto = document.documentElement.clientHeight;

        // Horizontal: el panel nace hacia el lado con MAS espacio libre. Esta
        // sola regla reproduce todas las elecciones que hoy estan a mano —un ⓘ
        // en la esquina de una tarjeta queda a la derecha del centro, asi que
        // abre hacia la izquierda; uno pegado a un titulo queda a la izquierda
        // y abre hacia la derecha— y de paso arregla la campanita, que abria
        // hacia el lado sin espacio. Nadie tiene que acertarlo desde la vista.
        const naceDerecha = base.left; // borde izq. del panel = borde izq. del disparador
        const naceIzquierda = base.right - ancho; // borde der. = borde der. del disparador
        const libreDerecha = vpAncho - MARGEN - base.left;
        const libreIzquierda = base.right - MARGEN;

        let dx = (libreDerecha >= libreIzquierda ? naceDerecha : naceIzquierda) - izquierda;

        // Y si aun asi se sale (panel mas ancho que el hueco), correrlo hacia
        // adentro. El segundo if gana sobre el primero a proposito: con un
        // panel mas ancho que la pantalla se prefiere ver su inicio.
        if (izquierda + dx + ancho > vpAncho - MARGEN) dx = vpAncho - MARGEN - ancho - izquierda;
        if (izquierda + dx < MARGEN) dx = MARGEN - izquierda;

        // Vertical: si no cabe donde esta, probar el otro lado del disparador
        // (solo si alla cabe entero; si no, mejor quedarse y scrollear).
        let dy = 0;
        if (arriba + alto > vpAlto - MARGEN && base.top - MARGEN >= alto) {
            dy = base.top - MARGEN - alto - arriba; // voltea ARRIBA
        } else if (arriba < MARGEN && vpAlto - MARGEN - base.bottom >= alto) {
            dy = base.bottom + MARGEN - arriba; // voltea ABAJO
        }

        // Se descuentan los margenes (mt-2 / mb-2 del componente): con `top`
        // implicito el margen YA esta dentro de offsetTop, pero al fijar `top`
        // el navegador lo vuelve a sumar y el panel baja 8px de mas — lo justo
        // para que el borde inferior quede pegado al filo de la pantalla.
        const margenSup = parseFloat(estilo.marginTop) || 0;
        const margenIzq = parseFloat(estilo.marginLeft) || 0;

        // Se anulan right/bottom para no quedar sobre-restringidos con la clase
        // (end-0, bottom-full), que si no seguiria mandando.
        el.style.left = `${Math.round(offsetX + dx - margenIzq)}px`;
        el.style.right = 'auto';
        el.style.top = `${Math.round(offsetY + dy - margenSup)}px`;
        el.style.bottom = 'auto';

        // Ultimo recurso: no cabe entero por ningun lado -> scroll adentro, que
        // es preferible a que la mitad quede fuera de la pantalla.
        const disponible = vpAlto - MARGEN - (arriba + dy);
        if (alto > disponible) {
            el.style.maxHeight = `${Math.max(Math.round(disponible), ALTO_MINIMO)}px`;
            el.style.overflowY = 'auto';
        }
    };

    // x-show togglea `display` en el atributo style: observarlo es lo que
    // permite reposicionar al abrir sin tocar el codigo de apertura de cada
    // componente. Las escrituras propias (left/top/maxHeight) no cambian
    // `display`, asi que no se realimenta.
    let visible = false;
    const sincronizar = () => {
        const ahora = window.getComputedStyle(el).display !== 'none';
        if (ahora && !visible) colocar();
        visible = ahora;
    };

    const observador = new MutationObserver(sincronizar);
    observador.observe(el, { attributeFilter: ['style', 'class'] });
    sincronizar();

    // Con el panel abierto, cualquier cosa que mueva al disparador o cambie el
    // viewport invalida la medida (incluye cruzar el breakpoint del ⓘ, donde
    // el panel pasa de `fixed` a `absolute`).
    const alMoverse = () => { if (visible) colocar(); };
    window.addEventListener('resize', alMoverse);
    window.addEventListener('scroll', alMoverse, { passive: true, capture: true });

    cleanup(() => {
        observador.disconnect();
        window.removeEventListener('resize', alMoverse);
        window.removeEventListener('scroll', alMoverse, { capture: true });
    });
});

/**
 * Buscador remoto reutilizable (Servicio Tecnico): autocompletado contra un
 * endpoint JSON (limit 15). Se usa para cliente (por RUT/nombre) y para producto
 * (por SKU/nombre); el id elegido se guarda en un <input hidden> que define la
 * vista (name="cliente_id" o "producto_id"). Se registra aqui y no con
 * @push('scripts') porque el layout no tiene @stack. Enfoca via $refs.input
 * para que convivan varias instancias en la misma pagina.
 */
Alpine.data('buscadorRemoto', ({ endpoint, inicialId, inicialLabel }) => ({
    endpoint,
    term: inicialLabel || '',
    seleccionId: inicialId || null,
    elegidoLabel: inicialLabel || '',
    resultados: [],
    abierto: false,
    cargando: false,

    async buscar() {
        const q = this.term.trim();

        if (q.length < 2) {
            this.resultados = [];
            this.abierto = false;
            return;
        }

        this.cargando = true;
        this.abierto = true;

        try {
            const { data } = await window.axios.get(this.endpoint, { params: { q } });
            this.resultados = data;
        } catch (e) {
            this.resultados = [];
        } finally {
            this.cargando = false;
        }
    },

    elegir(r) {
        this.seleccionId = r.id;
        this.elegidoLabel = r.label;
        this.term = r.label;
        this.abierto = false;
        this.resultados = [];
    },

    limpiar() {
        this.seleccionId = null;
        this.elegidoLabel = '';
        this.term = '';
        this.resultados = [];
        this.$nextTick(() => this.$refs.input?.focus());
    },
}));

/**
 * Cliente del ingreso (Servicio Tecnico). Nombre y RUT se guardan SIEMPRE en la
 * orden (campos obligatorios), exista o no en el catalogo. El RUT funciona como
 * buscador: si la persona ya existe, elegirla autocompleta nombre + rut + enlaza
 * cliente_id; si no, se escriben a mano (cliente_id queda nulo). Editar el RUT a
 * mano rompe el enlace (cliente_id nulo) porque ya no corresponde a esa ficha.
 */
Alpine.data('clienteIngreso', ({ endpoint, rut, nombre, telefono, clienteId }) => ({
    endpoint,
    rut: rut || '',
    nombre: nombre || '',
    telefono: telefono || '',
    clienteId: clienteId || null,
    resultados: [],
    abierto: false,
    cargando: false,

    // Máquina propia de la empresa (IMP. DALI / IMPORTADORA DALI): ignora puntos,
    // espacios y mayús/minús. Cuando es propia, RUT/teléfono/correo son opcionales.
    get esPropia() {
        const n = (this.nombre || '').toUpperCase().replace(/[.,]/g, ' ').replace(/\s+/g, ' ').trim();
        return ['IMP DALI', 'IMPORTADORA DALI', 'DALI'].includes(n);
    },

    async buscar() {
        this.clienteId = null; // tipear a mano rompe el enlace al catalogo
        const q = this.rut.trim();

        if (q.length < 2) {
            this.resultados = [];
            this.abierto = false;
            return;
        }

        this.cargando = true;
        this.abierto = true;

        try {
            const { data } = await window.axios.get(this.endpoint, { params: { q } });
            this.resultados = data;
        } catch (e) {
            this.resultados = [];
        } finally {
            this.cargando = false;
        }
    },

    elegir(r) {
        this.clienteId = r.id;
        this.rut = r.rut || '';
        this.nombre = r.razon_social || '';
        // Si la ficha no trae telefono, conservar el que se haya tipeado.
        this.telefono = r.telefono || this.telefono;
        this.abierto = false;
        this.resultados = [];
    },
}));

/**
 * Formulario de Servicio Tecnico. Maneja dos cosas:
 *  1. `cond`: muestra el bloque de documento de garantia solo si la condicion
 *     es "garantia".
 *  2. Fecha de entrega estimada: fecha de ingreso + N dias habiles segun la
 *     sucursal (data-dias en cada <option>), saltando sabados, domingos y
 *     feriados (lista pasada desde config/feriados.php). Al REGISTRAR
 *     (soloLectura) es solo informativa: siempre se recalcula y el servidor
 *     fija la definitiva. Al EDITAR es editable: si el usuario la cambia a
 *     mano, deja de recalcularse.
 */
Alpine.data('ordenServicioForm', ({ cond, fechaEntrega, feriados, soloLectura, sucursalSel }) => ({
    cond: cond || '',
    fechaEntrega: fechaEntrega || '',
    soloLectura: !!soloLectura,
    entregaManual: !soloLectura && !!fechaEntrega, // si ya traia fecha (editar), no la pisamos
    feriados: new Set(feriados || []),
    // Recepción elegida: id de sucursal o el centinela 'ruta' (muestra el campo
    // de ciudad y evita exigir una sucursal física).
    sucursalSel: sucursalSel || '',

    init() {
        // Registrar: mostrar el estimado apenas haya sucursal (p. ej. al volver
        // con errores de validacion, donde la sucursal ya viene elegida).
        if (this.soloLectura) this.$nextTick(() => this.recalcularEntrega());
    },

    iso(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    },

    // Suma `n` dias habiles a partir del dia SIGUIENTE a `desde` (Y-m-d),
    // saltando sabados, domingos y feriados. Devuelve Y-m-d o ''.
    sumarDiasHabiles(desde, n) {
        if (!desde || !n) return '';
        const d = new Date(desde + 'T00:00:00');
        if (isNaN(d.getTime())) return '';

        let sumados = 0;
        while (sumados < n) {
            d.setDate(d.getDate() + 1);
            const dow = d.getDay(); // 0=domingo, 6=sabado
            if (dow === 0 || dow === 6) continue;
            if (this.feriados.has(this.iso(d))) continue;
            sumados++;
        }

        return this.iso(d);
    },

    recalcularEntrega() {
        if (this.entregaManual) return;

        const ingreso = this.$refs.fechaIngreso?.value;
        const opt = this.$refs.sucursal?.selectedOptions?.[0];
        const dias = opt ? parseInt(opt.dataset.dias || '0', 10) : 0;

        this.fechaEntrega = this.sumarDiasHabiles(ingreso, dias);
    },
}));

/**
 * Etapa de taller (Servicio Tecnico). Maneja la lista variable de repuestos
 * (agregar/quitar filas) y calcula en vivo el costo total: suma de cada
 * repuesto (cantidad x precio) + mano de obra. Montos en pesos chilenos.
 */
Alpine.data('reparacionForm', ({ repuestos, manoObra, endpointRepuestos, precioHora, descuentoPct }) => ({
    repuestos: Array.isArray(repuestos) ? repuestos : [],
    manoObra: manoObra || 0,
    // Descuento (%) sobre el total; 0 = sin descuento.
    descuentoPct: Number(descuentoPct) || 0,

    // Mano de obra por horas: valor hora del catalogo (SKU config, con IVA) x
    // las horas trabajadas. Si hay valor hora, `horas` calcula `manoObra`; el
    // campo de mano de obra sigue editable (override manual). `horas` arranca
    // en 0 aunque la orden ya tenga mano de obra guardada (esta se conserva
    // hasta que el tecnico toque las horas).
    precioHora: Number(precioHora) || 0,
    horas: 0,

    calcularManoObra() {
        if (this.precioHora > 0) {
            this.manoObra = Math.round((Number(this.horas) || 0) * this.precioHora);
        }
    },

    // Autocompletado de repuestos (historial + comunes). `filaActiva` marca
    // que fila tiene el dropdown abierto; `sugerencias` son los nombres del
    // endpoint. El campo sigue siendo de texto libre: elegir solo rellena.
    endpointRepuestos: endpointRepuestos || '',
    sugerencias: [],
    filaActiva: null,
    buscandoRepuesto: false,

    agregar() {
        this.repuestos.push({ nombre: '', cantidad: 1, precio_unitario: 0 });
    },

    quitar(i) {
        this.repuestos.splice(i, 1);
    },

    async buscarRepuesto(i) {
        this.filaActiva = i;
        const q = (this.repuestos[i]?.nombre || '').trim();

        if (q.length < 2 || !this.endpointRepuestos) {
            this.sugerencias = [];
            return;
        }

        this.buscandoRepuesto = true;

        try {
            const { data } = await window.axios.get(this.endpointRepuestos, { params: { q } });
            this.sugerencias = data;
        } catch (e) {
            this.sugerencias = [];
        } finally {
            this.buscandoRepuesto = false;
        }
    },

    elegirRepuesto(i, s) {
        // `s` puede ser {nombre, sku, precio} del catalogo, o {nombre} del historial.
        this.repuestos[i].nombre = s.nombre;
        // Si el catalogo trae precio (con IVA), se pre-rellena como sugerencia.
        // OJO: el precio se edita en la pestaña Cotización y aquí (parte del
        // técnico) viaja oculto; NO lo pisamos si ya tiene un precio cotizado
        // (> 0), para no borrar en silencio lo que puso la cotización.
        const yaTienePrecio = Number(this.repuestos[i].precio_unitario) > 0;
        if (! yaTienePrecio && s.precio !== null && s.precio !== undefined && s.precio !== '') {
            this.repuestos[i].precio_unitario = Number(s.precio);
        }
        this.cerrarSugerencias();
    },

    cerrarSugerencias() {
        this.sugerencias = [];
        this.filaActiva = null;
    },

    subtotal(r) {
        return (Number(r.cantidad) || 0) * (Number(r.precio_unitario) || 0);
    },

    get totalRepuestos() {
        return this.repuestos.reduce((s, r) => s + this.subtotal(r), 0);
    },

    // Costo bruto (antes de descuento): repuestos + mano de obra.
    get costoBruto() {
        return this.totalRepuestos + (Number(this.manoObra) || 0);
    },

    get descuentoMonto() {
        return Math.round((this.costoBruto * (Number(this.descuentoPct) || 0)) / 100);
    },

    // Total a pagar: bruto menos el descuento.
    get total() {
        return this.costoBruto - this.descuentoMonto;
    },

    clp(n) {
        return '$' + new Intl.NumberFormat('es-CL').format(Number(n) || 0);
    },
}));

/**
 * Formulario de la Agenda de terreno (tecnico industrial). Dos piezas:
 * (1) buscador del cliente por RUT/razon social que al elegir rellena
 * nombre/telefono/correo/direccion/ciudad y enlaza cliente_id (editables);
 * (2) detalle del servicio elegido del catalogo (UF, duracion, que incluye)
 * mostrado bajo el select para que quien agenda vea que esta vendiendo.
 */
Alpine.data('agendaTerrenoForm', ({ endpointCliente, servicios, clienteId, servicioId }) => ({
    endpointCliente: endpointCliente || '',
    servicios: servicios || {},          // {id: {valor_uf, duracion, incluye, observaciones}}
    // null (no 0): el hidden postea vacio -> nullable, y exists no rechaza a
    // un cliente NUEVO que no esta en el catalogo (patron de clienteIngreso).
    clienteId: clienteId || null,
    servicioId: servicioId || '',

    rutBusqueda: '',
    resultados: [],
    abierto: false,
    buscando: false,

    async buscarCliente() {
        const q = (this.rutBusqueda || '').trim();
        if (q.length < 2 || !this.endpointCliente) {
            this.resultados = [];
            return;
        }
        this.buscando = true;
        try {
            const { data } = await window.axios.get(this.endpointCliente, { params: { q } });
            this.resultados = data;
            this.abierto = true;
        } catch (e) {
            this.resultados = [];
        } finally {
            this.buscando = false;
        }
    },

    // Rellena los inputs por id (siguen editables); el que no exista se salta.
    elegirCliente(r) {
        this.clienteId = r.id || null;
        const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v || ''; };
        set('cliente_rut', r.rut);
        set('cliente_nombre', r.razon_social);
        set('cliente_telefono', r.telefono);
        set('cliente_email', r.email);
        set('direccion', r.direccion);
        set('ciudad', r.ciudad);
        this.rutBusqueda = r.rut || '';
        this.abierto = false;
        this.resultados = [];
    },

    get servicioDetalle() {
        return this.servicios[this.servicioId] || null;
    },
}));

/**
 * Registro de instalaciones del tecnico industrial. Buscador de cliente por
 * RUT/razon social que, al elegir, rellena nombre/RUT/comuna (siguen editables).
 * cliente_id null (no 0) para que el hidden postee vacio en clientes nuevos.
 */
Alpine.data('instalacionForm', ({ endpointCliente, clienteId }) => ({
    endpointCliente: endpointCliente || '',
    clienteId: clienteId || null,
    rutBusqueda: '',
    resultados: [],
    abierto: false,
    buscando: false,

    async buscarCliente() {
        const q = (this.rutBusqueda || '').trim();
        if (q.length < 2 || !this.endpointCliente) {
            this.resultados = [];
            return;
        }
        this.buscando = true;
        try {
            const { data } = await window.axios.get(this.endpointCliente, { params: { q } });
            this.resultados = data;
            this.abierto = true;
        } catch (e) {
            this.resultados = [];
        } finally {
            this.buscando = false;
        }
    },

    elegirCliente(r) {
        this.clienteId = r.id || null;
        const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v || ''; };
        set('cliente_rut', r.rut);
        set('cliente_nombre', r.razon_social);
        set('comuna_region', r.ciudad);
        this.rutBusqueda = r.rut || '';
        this.abierto = false;
        this.resultados = [];
    },
}));

/**
 * Cierre de un trabajo de la agenda de terreno: el tecnico industrial marca
 * "Realizado" y registra los repuestos usados (nombre + cantidad, filas
 * variables) y notas. El panel se despliega al pulsar "Realizado".
 */
Alpine.data('cierreTerrenoForm', () => ({
    abierto: false,
    repuestos: [],

    agregar() {
        this.repuestos.push({ nombre: '', cantidad: 1 });
    },
    quitar(i) {
        this.repuestos.splice(i, 1);
    },
}));

/**
 * Ingreso por LOTE de Servicio Tecnico (conductor en ruta). Tabla de maquinas
 * como filas livianas: cada fila lleva el codigo Dali (autocompletado por
 * fila, mismo patron que reparacionForm), serie/modelo y una foto de respaldo
 * (comprimida en el navegador con optimizarFotoInput). La empresa y los
 * defaults del lote se eligen una vez fuera de este componente.
 */
Alpine.data('loteServicioForm', ({ endpointProducto, endpointCliente, tipoDefault, tiposSerie }) => ({
    endpointProducto: endpointProducto || '',
    endpointCliente: endpointCliente || '',

    // Tipo por defecto del lote (reactivo: el select de arriba lo actualiza) y
    // tipos cuyo N° de serie es obligatorio — para marcar la serie por fila.
    tipoDefault: tipoDefault || '',
    tiposSerie: Array.isArray(tiposSerie) ? tiposSerie : [],

    serieObligatoria(m) {
        return this.tiposSerie.includes((m.tipo || this.tipoDefault) || '');
    },

    // Empresa del lote (se elige una vez). El RUT es el buscador; al elegir de
    // la lista autocompleta nombre/correo/teléfono y enlaza cliente_id.
    clienteId: 0,
    rut: '',
    nombre: '',
    email: '',
    telefono: '',
    empresaResultados: [],
    empresaAbierto: false,
    empresaBuscando: false,

    maquinas: [],
    sugerencias: [],
    filaActiva: null,
    buscando: false,

    init() {
        if (this.maquinas.length === 0) this.agregar();
    },

    async buscarEmpresa() {
        const q = (this.rut || '').trim();
        if (q.length < 2 || !this.endpointCliente) {
            this.empresaResultados = [];
            return;
        }
        this.empresaBuscando = true;
        try {
            const { data } = await window.axios.get(this.endpointCliente, { params: { q } });
            this.empresaResultados = data;
            this.empresaAbierto = true;
        } catch (e) {
            this.empresaResultados = [];
        } finally {
            this.empresaBuscando = false;
        }
    },

    elegirEmpresa(r) {
        this.clienteId = r.id || 0;
        this.rut = r.rut || '';
        this.nombre = r.razon_social || '';
        this.telefono = r.telefono || '';
        this.email = r.email || '';
        this.empresaAbierto = false;
        this.empresaResultados = [];
    },

    filaVacia() {
        return { producto_id: '', producto_label: '', numero_serie: '', modelo: '', foto_nombre: '' };
    },

    agregar() {
        this.maquinas.push(this.filaVacia());
    },

    quitar(i) {
        this.maquinas.splice(i, 1);
        if (this.maquinas.length === 0) this.agregar();
    },

    async buscar(i) {
        this.filaActiva = i;
        const q = (this.maquinas[i]?.producto_label || '').trim();
        if (q.length < 2 || !this.endpointProducto) {
            this.sugerencias = [];
            return;
        }
        this.buscando = true;
        try {
            const { data } = await window.axios.get(this.endpointProducto, { params: { q } });
            this.sugerencias = data;
        } catch (e) {
            this.sugerencias = [];
        } finally {
            this.buscando = false;
        }
    },

    elegir(i, s) {
        this.maquinas[i].producto_id = s.id;
        this.maquinas[i].producto_label = s.label;
        this.cerrar();
    },

    cerrar() {
        this.sugerencias = [];
        this.filaActiva = null;
    },

    // Comprime la foto en el navegador (optimizarFotoInput reemplaza el archivo
    // del input por la version liviana) y marca la fila como "con foto".
    async fotoInput(i, input) {
        await window.optimizarFotoInput(input);
        this.maquinas[i].foto_nombre = input.files?.[0]?.name || 'Foto lista';
    },

    // Antes de enviar: cada fila necesita un codigo elegido del catalogo.
    filaIncompleta(m) {
        return !m.producto_id;
    },
}));

/**
 * Cards de accesos del Inicio (M16, D-013): modo "Personalizar" — el usuario
 * elige el color del squircle de cada card y se guarda al instante (pintado
 * optimista con rollback si el PATCH falla). El token CSRF se lee FRESCO del
 * <meta> en cada request (patrón de la cola offline: jamás serializar _token).
 * `paleta` llega del Blade (key → clases del squircle, literales allá para
 * que Tailwind no las purgue).
 */
Alpine.data('dgTiles', ({ url, colores, paleta }) => ({
    editando: false,
    abierto: null, // key de la card con el panel de swatches abierto
    colores,
    mensaje: '',
    timerMensaje: null,

    abrir(key) {
        this.abierto = this.abierto === key ? null : key;
    },

    salir() {
        this.editando = false;
        this.abierto = null;
        this.mensaje = '';
    },

    aviso(texto) {
        this.mensaje = texto;
        clearTimeout(this.timerMensaje);
        this.timerMensaje = setTimeout(() => (this.mensaje = ''), 2500);
    },

    // Swap de clases del squircle (server-side rendered): quitar toda la
    // paleta y poner la elegida — nunca dos bg-* conviviendo.
    pintarSquircle(key, color) {
        const squircle = this.$root.querySelector(`[data-tile="${key}"] [data-squircle]`);
        if (!squircle) return;
        Object.values(paleta).forEach((clases) => squircle.classList.remove(...clases.split(' ')));
        squircle.classList.add(...paleta[color].split(' '));
    },

    async pintar(key, color) {
        const anterior = this.colores[key];
        this.abierto = null;
        if (anterior === color) return;

        this.colores[key] = color;
        this.pintarSquircle(key, color); // optimista; rollback si falla
        try {
            const resp = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ colores: this.colores }),
            });
            if (!resp.ok) throw new Error(String(resp.status));
            this.aviso('Guardado ✓');
        } catch (e) {
            this.colores[key] = anterior;
            this.pintarSquircle(key, anterior);
            this.aviso(e.message === '419' ? 'Sesión expirada — recarga la página' : 'No se pudo guardar');
        }
    },
}));

/**
 * Estado de red global (spike PWA, P-SPK-01). Indicador informativo para el
 * operario: navigator.onLine tiene falsos positivos (WiFi sin internet), asi
 * que al volver "online" se confirma con un HEAD al health check /up (ya
 * existe, sin auth). Declarado ANTES de Alpine.start(): si va despues,
 * $store.red es undefined al evaluar los x-show de las vistas.
 */
Alpine.store('red', {
    online: navigator.onLine,

    async confirmar() {
        try {
            const resp = await fetch('/up', { method: 'HEAD', cache: 'no-store' });
            this.online = resp.ok;
        } catch (e) {
            this.online = false;
        }
    },
});
window.addEventListener('online', () => Alpine.store('red').confirmar());
window.addEventListener('offline', () => (Alpine.store('red').online = false));

window.Alpine = Alpine;

Alpine.start();

/**
 * Registro del service worker (spike PWA). Guard de hostname: en localhost NO
 * se registra (un SW persiste POR ORIGEN y contaminaria cualquier otro
 * proyecto servido luego en el mismo puerto de dev); para probarlo en local:
 * localStorage.daligoSW = '1'. updateViaCache:'none' blinda la revalidacion
 * de sw.js contra headers de cache del hosting (LiteSpeed).
 */
if ('serviceWorker' in navigator) {
    const esLocal = ['localhost', '127.0.0.1'].includes(window.location.hostname);
    if (!esLocal || window.localStorage.getItem('daligoSW') === '1') {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register('/sw.js', { updateViaCache: 'none' })
                .catch(() => {}); // sin SW la app funciona igual (mejora progresiva)
        });
    }
}

// Drenado de la cola offline al volver la señal / al cargar (spike P-SPK-02).
iniciarColaOffline();

/**
 * Global: si la página cargó con errores de validación del servidor, llevar al
 * usuario al PRIMER error visible y sacudirlo (sin anillo, sin focus() para no
 * abrir el teclado en móvil). Marcamos cada mensaje con [data-error-message] en
 * el componente <x-input-error>. Se corre en un requestAnimationFrame tras
 * Alpine.start() para que los colapsables que se auto-abren ante error (p. ej.
 * paneles.maquina) ya estén expandidos; por eso preferimos el primer error con
 * offsetParent (visible).
 */
const irAlPrimerError = () => {
    const errores = [...document.querySelectorAll('[data-error-message]')];
    const primero = errores.find((el) => el.offsetParent !== null) || errores[0];
    if (primero) window.requestAnimationFrame(() => dgDestacar(primero, { ring: false }));
};
if (document.readyState !== 'loading') irAlPrimerError();
else document.addEventListener('DOMContentLoaded', irAlPrimerError);

/**
 * Códigos QR del mostrador (P-M12-01): en la página de QR de Servicio Técnico
 * dibujamos en el cliente el QR del link firmado de cada sucursal. Import
 * dinámico: 'qrcode' solo se descarga en esa página (chunk aparte), no en el
 * bundle global de todas las vistas.
 */
const dibujarQrsMostrador = () => {
    const nodos = document.querySelectorAll('canvas[data-qr]');
    if (!nodos.length) return;
    import('qrcode').then((mod) => {
        // 'qrcode' es CommonJS: segun el interop de Vite puede llegar como
        // mod.default o como el modulo mismo. Aceptamos ambos.
        const QRCode = mod.default ?? mod;
        nodos.forEach((canvas) => {
            QRCode.toCanvas(canvas, canvas.dataset.qr, { width: 224, margin: 1 }, (err) => {
                if (err) console.error('No se pudo dibujar el QR:', err);
            });
        });
    });
};
if (document.readyState !== 'loading') dibujarQrsMostrador();
else document.addEventListener('DOMContentLoaded', dibujarQrsMostrador);

/**
 * Optimización de fotos EN EL NAVEGADOR antes de subir (ingreso por QR).
 * Las fotos de celular (12MP+) pesan varios MB y decodificarlas en el servidor
 * con GD agota la memoria del hosting (error 500 y no se envía). Aquí se
 * redimensionan a MAX_LADO_FOTO px y se re-encodan a JPEG, dejando el archivo en
 * ~200-400 KB: subida liviana y rápida, y el servidor la procesa sin problema.
 * Convierte HEIC de iPhone a JPEG de paso (Safari decodifica HEIC en el <img>).
 * Si algo falla, se sube el original (el servidor igual comprime como respaldo).
 */
const MAX_LADO_FOTO = 1600;

async function comprimirImagenCliente(file) {
    const url = URL.createObjectURL(file);
    try {
        const img = await new Promise((resolve, reject) => {
            const im = new Image();
            im.onload = () => resolve(im);
            im.onerror = reject;
            im.src = url;
        });

        const lado = Math.max(img.naturalWidth, img.naturalHeight);
        const escala = Math.min(1, MAX_LADO_FOTO / lado);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * escala);
        canvas.height = Math.round(img.naturalHeight * escala);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.8));
        if (!blob) return null;

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    } finally {
        URL.revokeObjectURL(url);
    }
}

// Reemplaza el archivo del input por su versión liviana. Se llama desde el
// onchange de los inputs de foto del formulario del QR. No deshabilita el input
// (para no perder el archivo si el usuario envía justo durante el proceso).
window.optimizarFotoInput = async function (input) {
    const file = input.files && input.files[0];
    if (!file || !file.type.startsWith('image/')) return; // no-imagen (o vacío): dejar al servidor

    try {
        const liviana = await comprimirImagenCliente(file);
        if (liviana && liviana.size < file.size) {
            const dt = new DataTransfer();
            dt.items.add(liviana);
            input.files = dt.files;
        }
    } catch (e) {
        // Si falla, se sube el original; el servidor comprime igual (con más memoria).
    }
};
