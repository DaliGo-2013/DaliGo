/**
 * Visor 3D del simulador de carga (LOGÍSTICA).
 *
 * SIN LIBRERÍAS a propósito. Todo lo que hay que dibujar son prismas —la silueta
 * del camión y los bultos— y proyectarlos ordenándolos por profundidad son unas
 * pocas decenas de líneas, contra los ~150 KB comprimidos que costaría una
 * librería 3D en una PWA. Se evaluó traer Three.js para «que se vea más real»
 * (pedido del dueño 05-08-2026) y se descartó: lo que faltaba no eran luces ni
 * texturas, era GEOMETRÍA —los cuatro camiones se dibujaban idénticos— y eso no
 * lo arregla ninguna librería. Si algún día hacen falta sombras proyectadas o
 * curvas de verdad, ahí sí conviene, y entra por import dinámico igual que este
 * archivo.
 *
 * NO existe ningún modelo 3D: la silueta se deriva de las medidas del camión y
 * cada bulto de las suyas. Cambiar un número en la base cambia el dibujo.
 *
 * TRES SILUETAS (`veh.silueta`, que manda el controlador): un contenedor de 40'
 * no tiene cabina propia —viaja sobre el semirremolque— y un HD35 de 4,3 m no es
 * un camión de reparto mediano. Dibujados todos iguales, el visor no ayudaba a
 * reconocer contra qué se está cotizando, que es la mitad de para qué existe.
 *
 * RENDIMIENTO: se descartan las caras que miran para el lado contrario a la
 * cámara y los bultos tapados por sus seis vecinos (ver `bultos`). Sin eso, un
 * cupo de 900 bultos son 5.400 polígonos ordenados por frame y el arrastre se
 * arrastra en celular. Por lo mismo los DEGRADADOS se usan solo en la silueta
 * (decenas de caras) y nunca en los bultos (miles): crear un gradiente por cara
 * y por frame es caro.
 *
 * Se carga solo en esta pantalla (ver el import dinámico en app.js).
 */

// Caras del prisma, en el orden en que `v8()` entrega los vértices.
const CARAS = [[0, 1, 2, 3], [4, 5, 6, 7], [0, 1, 5, 4], [3, 2, 6, 7], [0, 3, 7, 4], [1, 2, 6, 5]];
// Sombreado por cara: da volumen sin necesitar luces ni normales. La luz viene
// de ARRIBA, así que el techo es la cara más clara y el piso la más oscura —
// antes estaba al revés y los bultos se veían apagados justo por arriba, que es
// por donde más se los mira.
const SOMBRA = [0.80, 0.88, 0.50, 1.0, 0.70, 0.84];

/** Los 8 vértices de un prisma recto: `a` estira x (largo), `b` z (ancho), `c` y (alto). */
const v8 = (x, y, z, a, b, c) => [
    [x, y, z], [x + a, y, z], [x + a, y + c, z], [x, y + c, z],
    [x, y, z + b], [x + a, y, z + b], [x + a, y + c, z + b], [x, y + c, z + b],
];

/**
 * Prisma con el TECHO corrido en x: `dxFondo` mete el techo hacia la puerta,
 * `dxFrente` lo mete hacia el fondo. Con eso salen el capó y el parabrisas
 * reclinados sin salir del mismo pipeline de caras.
 */
const cuna = (x, y, z, a, b, c, dxFondo = 0, dxFrente = 0) => [
    [x, y, z], [x + a, y, z], [x + a - dxFrente, y + c, z], [x + dxFondo, y + c, z],
    [x, y, z + b], [x + a, y, z + b], [x + a - dxFrente, y + c, z + b], [x + dxFondo, y + c, z + b],
];

const rgb = (c, s = 1) => `rgb(${Math.round(c[0] * s)},${Math.round(c[1] * s)},${Math.round(c[2] * s)})`;

const AZUL = [58, 110, 170], VIDRIO = [128, 176, 220], GRIS = [78, 82, 90], CLARO = [188, 191, 198];

export default function iniciarCarga3d(canvas, datos) {
    const ctx = canvas.getContext('2d');
    const veh = datos.vehiculo;
    // La carga viaja SIEMPRE como lista de bloques (cupo máximo = un bloque;
    // carga mixta = un bloque por tipo colocado, con su color y su posición).
    const bloques = datos.bloques || [];

    let yaw = -0.85, pitch = -0.3, cant = Math.round(datos.tope * 0.6), anim = null, arrastre = null;
    let CX = 0, CY = 0, ESC = 100, OFF = [0, 0, 0], cola = [];

    /**
     * Proporciones del vehículo, derivadas de sus medidas útiles. Se calculan UNA
     * vez y las comparten la silueta y el encuadre: si el encuadre usara otras,
     * el camión se saldría del lienzo.
     */
    const M = (() => {
        const semi = veh.silueta === 'semirremolque';
        const liviano = veh.silueta === 'camion_liviano';
        const chas = semi ? 0.24 : (liviano ? 0.14 : 0.20);
        const r = semi ? 0.46 : (liviano ? 0.32 : 0.46);
        const rw = semi ? 0.24 : (liviano ? 0.17 : 0.22);
        const sep = semi ? 0.35 : 0;   // hueco entre el tracto y el frente del acoplado
        const largoCab = Math.min(semi ? 2.6 : (liviano ? 1.55 : 2.15), veh.largo * (semi ? 0.25 : 0.42));
        const altoCab = semi
            ? Math.min(veh.alto * 1.05, 2.35)
            : Math.min(veh.alto * (liviano ? 0.86 : 0.78), liviano ? 1.75 : 2.05);
        return {
            semi, liviano, chas, r, rw, sep, largoCab, altoCab,
            suelo: -chas - r * 2,
            delante: largoCab + sep,
            // El dormitorio del tracto es lo más alto de la escena.
            techo: veh.alto + (semi ? Math.max(0, altoCab + altoCab * 0.30 - veh.alto) : 0),
        };
    })();

    function proyectar(p) {
        const x0 = p[0] - OFF[0], y0 = p[1] - OFF[1], z0 = p[2] - OFF[2];
        const cy = Math.cos(yaw), sy = Math.sin(yaw), cp = Math.cos(pitch), sp = Math.sin(pitch);
        const x = x0 * cy - z0 * sy, z = x0 * sy + z0 * cy;
        const y2 = y0 * cp - z * sp, z2 = y0 * sp + z * cp;
        const f = 1 / (1 + z2 * 0.048);
        return [CX + x * ESC * f, CY - y2 * ESC * f, z2];
    }

    // ---------------------------------------------------------------- primitivas

    /**
     * Encola las caras de un cuerpo de 8 vértices.
     *
     * `cull`: descarta las caras que miran para el otro lado comparando la
     * profundidad de la cara con la del centro del cuerpo — un prisma es convexo,
     * así que una cara más lejana que su propio centro no puede verse. Se apaga
     * en las paredes translúcidas de la caja, que sí deben dibujarse enteras.
     * `grad`: degradado vertical en vez de color plano (solo para la silueta).
     */
    function cuerpo(vertices, col, { alpha = 1, borde = 'rgba(0,0,0,.22)', cull = true, grad = false } = {}) {
        const pv = vertices.map(proyectar);
        const zCentro = pv.reduce((s, p) => s + p[2], 0) / 8;

        CARAS.forEach((f, k) => {
            const zc = (pv[f[0]][2] + pv[f[1]][2] + pv[f[2]][2] + pv[f[3]][2]) / 4;
            if (cull && zc > zCentro + 1e-9) return;
            const s = SOMBRA[k];
            cola.push({
                z: zc,
                pts: f.map((i) => pv[i]),
                fill: `rgba(${col.map((v) => Math.round(v * s)).join(',')},${alpha})`,
                grad: grad ? [rgb(col, Math.min(1, s * 1.14)), rgb(col, s * 0.82)] : null,
                borde,
            });
        });
    }

    const prisma = (x, y, z, a, b, c, col, opts) => cuerpo(v8(x, y, z, a, b, c), col, opts);
    const G = { grad: true };

    /**
     * Prisma LARGO partido en tramos a lo largo de x.
     *
     * Mismo motivo que `paredes()`: el orden de dibujo usa la profundidad del
     * centro de cada cara, así que el piso o un riel de 12 m se ordenaban como si
     * estuvieran enteros a la altura de su punto medio y se pintaban ENCIMA de la
     * carga del fondo (se veía un parche gris en medio del bulterío). Los tramos
     * van sin borde: uno por tramo dibujaría juntas que no existen.
     */
    function tira(x, y, z, a, b, c, col, opts = {}) {
        const N = Math.max(1, Math.round(a / 0.8));
        for (let i = 0; i < N; i++) {
            prisma(x + (i * a) / N, y, z, a / N, b, c, col, { ...opts, borde: null });
        }
    }

    /**
     * Largueros bajo la caja: DOS VIGAS angostas, no una placa del ancho completo.
     *
     * Una placa tan ancha como el piso compite con él por el orden de dibujo —son
     * dos superficies grandes casi a la misma altura, y cada una partida en tramos
     * distintos— y salía como un parche oscuro sobre el piso claro. Dos vigas
     * angostas se leen igual de bien (es lo que se ve bajo un acoplado de verdad) y
     * no tapan nada.
     */
    function largueros(x, largo) {
        // Terminan 2 cm POR DEBAJO del piso: al ras quedaban coplanares con él y
        // asomaban como rectángulos oscuros sobre el piso claro.
        for (const z of [veh.ancho * 0.20, veh.ancho * 0.80 - 0.18]) {
            tira(x, -M.chas, z, largo, 0.18, Math.max(0.04, M.chas - 0.07), GRIS, G);
        }
    }

    /** Una sola cara suelta (sin espesor), para las paredes de la caja. */
    function panel(pts, col, { alpha = 1, tono = 1, borde = null } = {}) {
        const pv = pts.map(proyectar);
        cola.push({
            z: pv.reduce((s, p) => s + p[2], 0) / pv.length,
            pts: pv,
            fill: `rgba(${col.map((v) => Math.round(v * tono)).join(',')},${alpha})`,
            grad: null,
            borde,
        });
    }

    /**
     * Rueda: polígono de 18 lados extruido, con llanta más clara al centro.
     * `doble` dibuja el par de neumáticos del eje trasero — ningún camión lleva
     * rueda sola atrás, y con una sola el dibujo se leía como de juguete.
     */
    function rueda(cx, cyBase, z, r, ancho, doble = false) {
        const N = 18, paso = Math.PI / N, per = [];
        for (let i = 0; i < N; i++) {
            const a = paso + (i * 2 * Math.PI) / N;
            per.push([cx + Math.cos(a) * r, cyBase - r + Math.sin(a) * r]);
        }
        const cen = [cx, cyBase - r];
        const zc = (q) => q.reduce((s, p) => s + p[2], 0) / q.length;

        for (const z0 of (doble ? [z, z + ancho + 0.03] : [z])) {
            const A = per.map((p) => proyectar([p[0], p[1], z0]));
            const B = per.map((p) => proyectar([p[0], p[1], z0 + ancho]));
            // Banda de rodadura.
            for (let i = 0; i < N; i++) {
                const j = (i + 1) % N, q = [A[i], A[j], B[j], B[i]];
                cola.push({ z: zc(q), pts: q, fill: 'rgb(38,38,43)', borde: null });
            }
            // Flancos + llanta concéntrica: es la llanta la que la hace leer redonda.
            for (const [cara, zLado, tono] of [[A, z0 - 0.002, 'rgb(56,56,62)'], [B, z0 + ancho + 0.002, 'rgb(30,30,34)']]) {
                cola.push({ z: zc(cara), pts: cara, fill: tono, borde: 'rgba(0,0,0,.3)' });
                const llanta = per.map((q) => proyectar([
                    cen[0] + (q[0] - cen[0]) * 0.52, cen[1] + (q[1] - cen[1]) * 0.52, zLado,
                ]));
                cola.push({ z: zc(llanta) - 0.02, pts: llanta, fill: 'rgb(122,124,130)', borde: null });
            }
        }
    }

    /** Guardabarros: media caña sobre la rueda. Quita lo de «chasis pelado». */
    function guardabarro(cx, cyBase, z, r, ancho) {
        const N = 7, R = r + 0.07, cen = cyBase - r, arco = [];
        for (let i = 0; i <= N; i++) {
            const a = Math.PI - (i * Math.PI) / N;
            arco.push([cx + Math.cos(a) * R, cen + Math.sin(a) * R]);
        }
        for (let i = 0; i < N; i++) {
            const q = [
                proyectar([arco[i][0], arco[i][1], z]),
                proyectar([arco[i + 1][0], arco[i + 1][1], z]),
                proyectar([arco[i + 1][0], arco[i + 1][1], z + ancho]),
                proyectar([arco[i][0], arco[i][1], z + ancho]),
            ];
            cola.push({ z: q.reduce((s, p) => s + p[2], 0) / 4, pts: q, fill: rgb(GRIS, 0.92), borde: null });
        }
    }

    /**
     * Sombra de contacto: elipse con degradado radial, en vez del rectángulo
     * plano de borde duro que había antes. Es lo que más «apoya» el vehículo en
     * el piso por lo poco que cuesta.
     */
    function sombraSuave(x0, x1) {
        const p = [[x0, M.suelo, 0], [x1, M.suelo, 0], [x1, M.suelo, veh.ancho], [x0, M.suelo, veh.ancho]].map(proyectar);
        const xs = p.map((q) => q[0]), ys = p.map((q) => q[1]);
        const cx = (Math.min(...xs) + Math.max(...xs)) / 2, cy = (Math.min(...ys) + Math.max(...ys)) / 2;
        const rx = (Math.max(...xs) - Math.min(...xs)) / 2 * 1.06;
        const ry = (Math.max(...ys) - Math.min(...ys)) / 2 * 1.3;
        if (!(rx > 0 && ry > 0)) return;

        const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, Math.max(rx, ry));
        g.addColorStop(0, 'rgba(0,0,0,.20)');
        g.addColorStop(0.6, 'rgba(0,0,0,.09)');
        g.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.beginPath();
        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
        ctx.fillStyle = g;
        ctx.fill();
    }

    function pintar() {
        cola.sort((a, b) => b.z - a.z);
        cola.forEach((o) => {
            ctx.beginPath();
            o.pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
            ctx.closePath();
            if (o.grad) {
                const ys = o.pts.map((p) => p[1]);
                const g = ctx.createLinearGradient(0, Math.min(...ys), 0, Math.max(...ys));
                g.addColorStop(0, o.grad[0]);
                g.addColorStop(1, o.grad[1]);
                ctx.fillStyle = g;
            } else {
                ctx.fillStyle = o.fill;
            }
            ctx.fill();
            if (o.borde) { ctx.strokeStyle = o.borde; ctx.lineWidth = 1; ctx.stroke(); }
        });
        cola = [];
    }

    // ----------------------------------------------------------------- siluetas

    /** Cabina con capó y parabrisas reclinados, paragolpes, parrilla y espejos. */
    function cabina(conDormitorio) {
        const x0 = -M.delante, w = veh.ancho - 0.06, z0 = 0.03;
        const alto = M.altoCab, cuerpoBajo = alto * 0.52, largo = M.largoCab;

        prisma(x0, 0, z0, largo, w, cuerpoBajo, AZUL, G);
        // Parabrisas reclinado: el techo se mete hacia atrás.
        cuerpo(cuna(x0, cuerpoBajo, z0, largo, w, alto - cuerpoBajo, 0, largo * 0.30), AZUL, G);
        // Vidrio, apenas por delante del parabrisas.
        cuerpo(cuna(x0 - 0.015, cuerpoBajo + 0.06, z0 + 0.05, largo * 0.32, w - 0.1,
            (alto - cuerpoBajo) * 0.72, 0, largo * 0.22), VIDRIO, { grad: true, borde: 'rgba(0,0,0,.3)' });
        // Dormitorio: el cajón que hace inconfundible al tracto.
        if (conDormitorio) prisma(x0 + largo * 0.34, alto, z0 + 0.04, largo * 0.62, w - 0.08, alto * 0.30, AZUL, G);
        // Paragolpes y parrilla.
        prisma(x0 - 0.10, 0.05, z0 + 0.02, 0.12, w - 0.04, cuerpoBajo * 0.42, GRIS, G);
        prisma(x0 - 0.03, cuerpoBajo * 0.56, z0 + 0.10, 0.05, w - 0.2, cuerpoBajo * 0.28, [40, 42, 48], G);
        // Espejos.
        for (const z of [z0 - 0.15, z0 + w + 0.03]) {
            prisma(x0 + largo * 0.24, cuerpoBajo + 0.10, z, 0.05, 0.12, 0.25, [45, 47, 52]);
        }
    }

    /** La caja: piso opaco, marco y rieles, y paredes translúcidas para ver adentro. */
    function cajaDeCarga() {
        const L = veh.largo, W = veh.ancho, H = veh.alto;
        tira(0, -0.05, 0, L, W, 0.05, CLARO, G);
        // Rieles arriba y abajo + marco de la puerta: le quitan lo de «cubo de vidrio».
        for (const z of [-0.02, W - 0.02]) {
            tira(0, H - 0.09, z, L, 0.04, 0.09, CLARO, G);
            tira(0, 0, z, L, 0.04, 0.10, CLARO, G);
        }
        for (const y of [0, H - 0.10]) prisma(L - 0.04, y, 0, 0.04, W, 0.10, CLARO, G);
        for (const z of [0, W - 0.05]) prisma(L - 0.04, 0, z, 0.04, 0.05, H, CLARO, G);
        paredes(L, W, H);
    }

    /**
     * Paredes translúcidas, en PANELES a lo largo y no como un solo prisma.
     *
     * El orden de dibujo compara la profundidad del CENTRO de cada cara, así que
     * una pared de 8 m entera se ordenaba como si estuviera toda a la distancia de
     * su punto medio: se pintaba encima de la cabina y de la carga y todo se veía
     * lavado. Partida en tramos, cada uno se ordena donde de verdad está.
     *
     * Sin borde: el marco y los rieles ya dan las aristas, y un borde por tramo
     * dibujaría una reja que no existe.
     */
    function paredes(L, W, H) {
        const VID = [250, 251, 253];
        // Tramos de ~0,6 m: en una caja corta, 4 tramos seguían siendo polígonos
        // grandes y la carga se veía lavada igual. Son caras planas, salen baratas.
        const N = Math.max(6, Math.round(L / 0.6));

        for (let i = 0; i < N; i++) {
            const xa = (i * L) / N, xb = ((i + 1) * L) / N;
            panel([[xa, 0, 0], [xb, 0, 0], [xb, H, 0], [xa, H, 0]], VID, { alpha: 0.10, tono: 0.86 });
            panel([[xa, 0, W], [xb, 0, W], [xb, H, W], [xa, H, W]], VID, { alpha: 0.10, tono: 0.95 });
            panel([[xa, H, 0], [xb, H, 0], [xb, H, W], [xa, H, W]], VID, { alpha: 0.09 });
        }
        panel([[0, 0, 0], [0, H, 0], [0, H, W], [0, 0, W]], VID, { alpha: 0.11, tono: 0.8 });
        panel([[L, 0, 0], [L, H, 0], [L, H, W], [L, 0, W]], VID, { alpha: 0.09, tono: 0.9 });
    }

    /** Camión de reparto (HINO, Chevy) y su variante liviana (HD35). */
    function siluetaCamion() {
        sombraSuave(-M.delante - 0.1, veh.largo);
        // Placa entera solo BAJO LA CABINA (ahí no hay piso que tapar); bajo la
        // caja van largueros angostos.
        tira(-M.delante, -M.chas, 0.02, M.delante, veh.ancho - 0.04, M.chas - 0.05, GRIS, G);
        largueros(0, veh.largo);
        cabina(false);
        cajaDeCarga();

        const anchoTras = M.liviano ? M.rw : M.rw * 2 + 0.03;
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) rueda(-M.largoCab * 0.58, -M.chas, z, M.r, M.rw);
        for (const z of [-0.03, veh.ancho - anchoTras + 0.03]) {
            rueda(veh.largo * 0.74, -M.chas, z, M.r, M.rw, !M.liviano);
            guardabarro(veh.largo * 0.74, -M.chas, z, M.r, anchoTras);
        }
    }

    /** Tracto + acoplado: el Contenedor 40' no tiene cabina, viaja arriba. */
    function siluetaSemirremolque() {
        sombraSuave(-M.delante - 0.1, veh.largo);
        // Chasis en dos tramos (se ve el quiebre del acoplado sobre el tracto).
        // Bajo el acoplado, largueros; bajo el tracto, la placa del chasis.
        largueros(0, veh.largo);
        tira(-M.delante, -M.chas, 0.06, M.delante + 0.35, veh.ancho - 0.12, M.chas * 0.8, GRIS, G);
        // Quinta rueda: el disco donde se apoya el acoplado.
        prisma(-M.sep * 0.6, -0.02, veh.ancho * 0.28, 0.55, veh.ancho * 0.44, 0.06, [58, 60, 66]);
        cabina(true);
        cajaDeCarga();
        // Patas de apoyo: sin ellas el acoplado parece flotar.
        for (const z of [veh.ancho * 0.16, veh.ancho * 0.78]) {
            prisma(veh.largo * 0.30, M.suelo + 0.06, z, 0.09, 0.09, -M.chas - M.suelo - 0.06, [96, 99, 106], G);
        }

        const anchoDoble = M.rw * 2 + 0.03;
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) rueda(-M.largoCab * 0.60, -M.chas, z, M.r, M.rw);
        // Tridem: separación 1,4 m contra ruedas de 0,92 m para que se lean
        // sueltas. Con 1,3 m y radio 0,50 se tocaban y quedaba un amasijo.
        for (const ex of [veh.largo * 0.66, veh.largo * 0.66 + 1.4, veh.largo * 0.66 + 2.8]) {
            if (ex > veh.largo - 0.35) continue;
            for (const z of [-0.03, veh.ancho - anchoDoble + 0.03]) rueda(ex, -M.chas, z, M.r, M.rw, true);
        }
        // Sin guardabarros: un acoplado porta-contenedor los lleva mínimos, y un
        // arco sobre los tres ejes salía como una mancha oscura enorme.
    }

    // ------------------------------------------------------------------- bultos

    /**
     * Los bultos, en orden de estiba (fondo → puerta, abajo → arriba: el
     * controlador manda los bloques ya ordenados).
     *
     * Se saltan los que quedan TAPADOS por sus seis vecinos: dentro de un bloque
     * macizo no se ven nunca. El test va sobre el índice de llenado, así que
     * también vale con el bloque a medio cargar (la animación) — y no puede
     * esconder un bulto que sí se vería, porque un vecino que todavía no se
     * dibujó no cuenta como tapa.
     */
    function bultos() {
        let puestos = 0;

        for (const blq of bloques) {
            if (puestos >= cant) break;
            const rej = blq.rejilla, ori = blq.orientacion, col = blq.color || [234, 88, 12];
            const capa = rej.ancho * rej.alto;
            const dibujables = Math.max(0, Math.min(blq.cantidad, rej.largo * capa, cant - puestos));

            const indice = (ix, iz, iy) => ix * capa + iz * rej.alto + iy;
            const puesto = (ix, iz, iy) => ix >= 0 && ix < rej.largo && iz >= 0 && iz < rej.ancho
                && iy >= 0 && iy < rej.alto && indice(ix, iz, iy) < dibujables;

            for (let n = 0; n < dibujables; n++) {
                const ix = Math.floor(n / capa), resto = n % capa;
                const iz = Math.floor(resto / rej.alto), iy = resto % rej.alto;

                if (puesto(ix - 1, iz, iy) && puesto(ix + 1, iz, iy)
                    && puesto(ix, iz - 1, iy) && puesto(ix, iz + 1, iy)
                    && puesto(ix, iz, iy - 1) && puesto(ix, iz, iy + 1)) continue;

                prisma(blq.x + ix * ori.largo, iy * ori.alto, blq.y + iz * ori.ancho,
                    ori.largo * 0.985, ori.ancho * 0.985, ori.alto * 0.985, col);
            }
            puestos += dibujables;
        }
    }

    // ------------------------------------------------------------------- escena

    /**
     * Encuadre: escala y centro para que el vehículo LLENE el lienzo.
     *
     * Se miden los extremos REALES del cuerpo ya proyectado en vez de dividir el
     * largo por un número a ojo. La versión anterior reservaba ancho para una
     * rotación que no estaba usando y el camión quedaba chico en el medio, con el
     * primer quinto del lienzo siempre vacío.
     *
     * Se calcula con los ÁNGULOS POR DEFECTO y queda fijo: si se recalculara en
     * cada frame, arrastrar para girar haría zoom, que no es lo que uno espera.
     */
    function encuadrar() {
        const yaw0 = yaw, pitch0 = pitch;
        yaw = -0.85; pitch = -0.3;
        OFF = [(veh.largo - M.delante) / 2, (M.techo + M.suelo) / 2, veh.ancho / 2];
        CX = 0; CY = 0; ESC = 1;

        let x0 = Infinity, x1 = -Infinity, y0 = Infinity, y1 = -Infinity;
        for (const x of [-M.delante, veh.largo]) {
            for (const y of [M.suelo, M.techo]) {
                for (const z of [0, veh.ancho]) {
                    const p = proyectar([x, y, z]);
                    x0 = Math.min(x0, p[0]); x1 = Math.max(x1, p[0]);
                    y0 = Math.min(y0, p[1]); y1 = Math.max(y1, p[1]);
                }
            }
        }

        ESC = Math.min(canvas.width * 0.93 / Math.max(0.01, x1 - x0),
            canvas.height * 0.88 / Math.max(0.01, y1 - y0));
        CX = canvas.width / 2 - ((x0 + x1) / 2) * ESC;
        CY = canvas.height / 2 - ((y0 + y1) / 2) * ESC;
        yaw = yaw0; pitch = pitch0;
    }

    function dibujar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (M.semi) siluetaSemirremolque(); else siluetaCamion();
        bultos();
        pintar();

        ctx.fillStyle = '#8a8a8a';
        ctx.font = '600 15px -apple-system,system-ui,sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText('PUERTA →', canvas.width - 22, 30);
        ctx.textAlign = 'left';

        const n = document.getElementById('carga3dN');
        if (n) n.textContent = cant;
    }

    // ---------------------------------------------------------------- controles

    canvas.addEventListener('pointerdown', (e) => {
        arrastre = { x: e.clientX, y: e.clientY, yaw, pitch };
        canvas.setPointerCapture(e.pointerId);
    });
    canvas.addEventListener('pointermove', (e) => {
        if (!arrastre) return;
        yaw = arrastre.yaw + (e.clientX - arrastre.x) * 0.008;
        pitch = Math.max(-1.15, Math.min(0.45, arrastre.pitch + (e.clientY - arrastre.y) * 0.006));
        dibujar();
    });
    canvas.addEventListener('pointerup', () => { arrastre = null; });

    const play = document.getElementById('carga3dPlay');
    if (play) {
        play.addEventListener('click', () => {
            clearInterval(anim);
            cant = 0;
            const paso = Math.max(1, Math.round(datos.tope / 70));
            anim = setInterval(() => {
                cant = Math.min(datos.tope, cant + paso);
                dibujar();
                if (cant >= datos.tope) clearInterval(anim);
            }, 45);
        });
    }

    encuadrar();
    dibujar();
}
