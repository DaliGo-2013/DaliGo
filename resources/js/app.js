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

window.Alpine = Alpine;

Alpine.start();
