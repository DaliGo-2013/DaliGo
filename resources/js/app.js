import './bootstrap';

import Alpine from 'alpinejs';

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
Alpine.data('clienteIngreso', ({ endpoint, rut, nombre, clienteId }) => ({
    endpoint,
    rut: rut || '',
    nombre: nombre || '',
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
 *     feriados (lista pasada desde config/feriados.php). Se autocompleta pero
 *     es editable: si el usuario la cambia a mano, deja de recalcularse.
 */
Alpine.data('ordenServicioForm', ({ cond, fechaEntrega, feriados }) => ({
    cond: cond || '',
    fechaEntrega: fechaEntrega || '',
    entregaManual: !!fechaEntrega, // si ya traia fecha, no la pisamos
    feriados: new Set(feriados || []),

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
Alpine.data('reparacionForm', ({ repuestos, manoObra, endpointRepuestos }) => ({
    repuestos: Array.isArray(repuestos) ? repuestos : [],
    manoObra: manoObra || 0,

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

    elegirRepuesto(i, nombre) {
        this.repuestos[i].nombre = nombre;
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

    get total() {
        return this.totalRepuestos + (Number(this.manoObra) || 0);
    },

    clp(n) {
        return '$' + new Intl.NumberFormat('es-CL').format(Number(n) || 0);
    },
}));

window.Alpine = Alpine;

Alpine.start();
