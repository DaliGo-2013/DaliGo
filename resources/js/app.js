import './bootstrap';

import Alpine from 'alpinejs';

/**
 * Buscador de cliente por RUT/nombre (Servicio Tecnico). Se registra aqui y no
 * con @push('scripts') porque el layout no tiene @stack. Pega a un endpoint JSON
 * (limit 15) y guarda el id elegido en un <input hidden name="cliente_id">.
 */
Alpine.data('buscadorCliente', ({ endpoint, inicialId, inicialLabel }) => ({
    endpoint,
    term: inicialLabel || '',
    clienteId: inicialId || null,
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
        this.clienteId = r.id;
        this.elegidoLabel = r.label;
        this.term = r.label;
        this.abierto = false;
        this.resultados = [];
    },

    limpiar() {
        this.clienteId = null;
        this.elegidoLabel = '';
        this.term = '';
        this.resultados = [];
        this.$nextTick(() => document.getElementById('cliente_buscar')?.focus());
    },
}));

window.Alpine = Alpine;

Alpine.start();
