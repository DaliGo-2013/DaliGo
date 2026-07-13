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
Alpine.data('ordenServicioForm', ({ cond, fechaEntrega, feriados, soloLectura }) => ({
    cond: cond || '',
    fechaEntrega: fechaEntrega || '',
    soloLectura: !!soloLectura,
    entregaManual: !soloLectura && !!fechaEntrega, // si ya traia fecha (editar), no la pisamos
    feriados: new Set(feriados || []),

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
        // Si el catalogo trae precio (con IVA), se pre-rellena como sugerencia
        // editable; el tecnico lo puede ajustar.
        if (s.precio !== null && s.precio !== undefined && s.precio !== '') {
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
