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

/**
 * La cabina va BLANCA, como la flota real (se ve en las fotos de carga que pasó el
 * dueño el 05-08). Antes era azul inventado.
 *
 * Y va en NEUTROS a propósito, sin franja de color: dentro del lienzo el color es
 * DATO —distingue un producto de otro y la leyenda lo traduce— así que pintar la
 * cabina de naranjo o azul la haría confundible con la carga. El contraste lo dan
 * los grises de paragolpes, parrilla, espejos y ruedas.
 */
const CABINA = [214, 218, 224], VIDRIO = [138, 158, 176], GRIS = [78, 82, 90], CLARO = [188, 191, 198];
const CHAPA = [236, 236, 228];

/**
 * Rótulo de la chapa de atrás. El catálogo del simulador son cajas de carga TIPO, así
 * que esto es un rótulo de MODELO y no una patente real.
 *
 * Se le quita el paréntesis («HINO 500 (FC 1118)» → «HINO 500») y, si sigue largo, se
 * le sacan las palabras de adelante hasta que entre en una chapa: en 9 caracteres no
 * cabe «HYUNDAI HD35» legible, y lo que identifica al camión es «HD35» — que además
 * es como lo pidió el dueño. «CONTENEDOR 40'» queda «40'», que es como se nombran los
 * contenedores.
 */
const rotulo = (nombre) => {
    const palabras = (nombre || '').replace(/\s*\([^)]*\)/g, '').trim().toUpperCase().split(/\s+/);
    while (palabras.length > 1 && palabras.join(' ').length > 9) palabras.shift();

    return palabras.join(' ');
};

export default function iniciarCarga3d(canvas, datos) {
    const ctx = canvas.getContext('2d');
    const veh = datos.vehiculo;
    // La carga viaja SIEMPRE como lista de bloques (cupo máximo = un bloque;
    // carga mixta = un bloque por tipo colocado, con su color y su posición).
    const bloques = datos.bloques || [];

    // Arranca VACÍO (decisión del dueño 05-08: «no quiero que el camión esté
    // contabilizado a cuánto tiene que llegar»). El visor es una herramienta que se
    // maneja, no una foto del máximo: se carga con los pasos (+1 / +5 / +10 / Todo)
    // o con «▶ Cargar», que reproduce la estiba de a poco.
    //
    // Se probó abrir LLENO para que el dibujo coincidiera con el «entran 420» del
    // título, y el dueño lo descartó: perseguir el tope no es lo que hace cuando
    // arma una carga. No volver a cambiarlo sin preguntarle.
    const TOPE = Math.max(0, datos.tope || 0);
    let yaw = -0.85, pitch = -0.3, cant = 0, anim = null, arrastre = null;
    let CX = 0, CY = 0, ESC = 100, OFF = [0, 0, 0], cola = [];
    // Encuadre base (escala y centro medidos a escala 1) + zoom encima. Separarlos
    // es lo que permite que girar no cambie el zoom.
    let escBase = 100, centro = [0, 0], zoom = 1, nombres = true;
    const ZOOM_MIN = 0.7, ZOOM_MAX = 4;
    // Hasta cuántas bolsas se dibujan como BIDONES antes de caer al bulto rectangular
    // (ver `bultos`). Medido sobre los polígonos por frame, no elegido a ojo.
    const TOPE_BIDONES = 150;
    // Cuántos bultos se dibujaron de cada bloque: lo llena `bultos()` y lo usan las
    // etiquetas para no rotular un bloque que la animación todavía no cargó.
    let dibujadosPorBloque = [];
    // Rótulo de atrás: se calcula una vez y `textoChapa` lo pinta arriba de todo.
    const ROTULO = rotulo(veh.nombre);
    let chapaCaja = null;

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
        const largoCab = Math.min(semi ? 2.6 : (liviano ? 1.45 : 2.15), veh.largo * (semi ? 0.25 : 0.42));
        const altoCab = semi
            ? Math.min(veh.alto * 1.05, 2.35)
            : (liviano ? Math.min(veh.alto * 0.60, 1.35) : Math.min(veh.alto * 0.78, 2.05));
        return {
            semi, liviano, chas, r, rw, sep, largoCab, altoCab,
            suelo: -chas - r * 2,
            delante: largoCab + sep,
            // El techo del tracto (cabina + deflector) puede pasar al contenedor. Ya no
            // se suma un cajón de dormitorio: el deflector son 14 cm, no 30% del alto.
            techo: veh.alto + (semi ? Math.max(0, altoCab + 0.14 - veh.alto) : 0),
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

    /**
     * Bolsa de bidones: los N botellones parados en fila dentro de la bolsa, en vez
     * de un ladrillo naranja. Es la carga diaria de Dali, así que es la que más se
     * mira (foto del dueño 05-08: se ven los 5 picos gathered arriba).
     *
     * Los N salen de la GEOMETRÍA, no de un número fijo: `largo / ancho` da 5 tanto en
     * la bolsa de 20 L (130/26) como en la de 10 L (110/21), y si mañana entra una
     * bolsa de 4 se dibuja con 4 sin tocar nada.
     *
     * Cada bidón son dos prismas hexagonales (cuerpo + pico). Seis lados alcanzan para
     * que se lea redondo a este tamaño y cuestan la mitad que ocho — y de estos van
     * cinco por bolsa.
     */
    function bolsaDeBidones(x, y, z, l, w, h, col) {
        const n = Math.max(1, Math.min(8, Math.round(l / Math.max(0.01, w))));
        const paso = l / n, r = Math.min(paso, w) * 0.46;

        for (let i = 0; i < n; i++) {
            const cx = x + paso * (i + 0.5), cz = z + w / 2;
            cilindro(cx, cz, y, r, h * 0.80, col);                       // cuerpo
            cilindro(cx, cz, y + h * 0.80, r * 0.42, h * 0.20, col);     // pico
        }
        // La bolsa: SOLO la película de arriba, que es donde se ve en la foto (el
        // plástico gathered sobre los picos). Dibujarla como caja entera metía tres
        // caras translúcidas por bolsa y con 74 bolsas apiladas la carga se veía de
        // vidrio, además de pelearse el orden de dibujo con los bidones de atrás.
        panel([[x, y + h, z], [x + l, y + h, z], [x + l, y + h, z + w], [x, y + h, z + w]],
            [216, 230, 245], { alpha: 0.34 });
    }

    /**
     * Prisma de 8 lados con eje VERTICAL, descartando las caras del otro lado.
     *
     * El sombreado va por el ÁNGULO de cada cara alrededor del eje contra una luz
     * fija del mundo, no por su profundidad en pantalla: así el redondeo se lee igual
     * desde cualquier ángulo de cámara. La primera versión usaba la profundidad y los
     * bidones salían angulosos y con el brillo saltando al girar.
     */
    function cilindro(cx, cz, y0, r, alto, col) {
        const N = 8, per = [], LUZ = -0.9;   // la luz entra por el frente-izquierda
        for (let i = 0; i < N; i++) {
            const a = (i * 2 * Math.PI) / N;
            per.push([cx + Math.cos(a) * r, cz + Math.sin(a) * r, a]);
        }
        const A = per.map((p) => proyectar([p[0], y0, p[1]]));
        const B = per.map((p) => proyectar([p[0], y0 + alto, p[1]]));
        const zc = (q) => q.reduce((s, p) => s + p[2], 0) / q.length;
        const eje = (zc(A) + zc(B)) / 2;

        for (let i = 0; i < N; i++) {
            const j = (i + 1) % N, q = [A[i], A[j], B[j], B[i]];
            const z = zc(q);
            if (z > eje) continue;   // cara del otro lado del cilindro
            const normal = per[i][2] + Math.PI / N;      // hacia dónde mira la cara
            const s = 0.64 + 0.36 * (0.5 + 0.5 * Math.cos(normal - LUZ));
            cola.push({ z, pts: q, fill: rgb(col, s), borde: null });
        }
        cola.push({ z: zc(B) - 0.01, pts: B, fill: rgb(col, 1), borde: 'rgba(0,0,0,.18)' });
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

    /**
     * Cabina del TRACTO, moldeada sobre las fotos del Actros 2545 del dueño (05-08).
     *
     * Lo que estaba mal antes, según esas fotos:
     * · era una cuña con un CAJÓN encima haciendo de dormitorio; en el tracto real el
     *   dormitorio es parte del cuerpo y arriba solo va el deflector, fino;
     * · el parabrisas estaba reclinado un 30% y en el Actros es casi vertical;
     * · faltaban la banda oscura bajo el vidrio, la parrilla en tres franjas, los faros
     *   de las esquinas bajas, el guardabarro y el estribo;
     * · los espejos eran dos palitos y son grandes, sobre brazos.
     *
     * Solo la usa el semirremolque: las cabinas del HINO y del HD35 esperan sus propias
     * fotos, y moldearlas a ojo mientras tanto sería inventar otra vez.
     */
    function cabinaTracto() {
        const x0 = -M.delante, largo = M.largoCab, w = veh.ancho - 0.06, z0 = 0.03;
        const alto = M.altoCab, capo = alto * 0.44;

        // Cuerpo de una pieza. El parabrisas apenas se inclina (7% del largo).
        prisma(x0, 0, z0, largo, w, capo, CABINA, G);
        cuerpo(cuna(x0, capo, z0, largo, w, alto - capo, largo * 0.07, 0), CABINA, G);

        // Deflector del techo: sobresale al frente y a los costados, y es FINO.
        prisma(x0 - 0.03, alto, z0 - 0.03, largo * 0.92, w + 0.06, 0.14, CABINA, G);

        // Parabrisas: grande y casi vertical, ocupando casi todo el frente de arriba.
        cuerpo(cuna(x0 - 0.02, capo + 0.14, z0 + 0.05, largo * 0.16, w - 0.10,
            (alto - capo) * 0.62, largo * 0.05, 0), VIDRIO, { grad: true, borde: 'rgba(0,0,0,.35)' });
        // Banda oscura bajo el parabrisas (la zona de los limpiaparabrisas).
        prisma(x0 - 0.03, capo + 0.02, z0 + 0.04, 0.05, w - 0.08, 0.13, [44, 46, 52]);

        // Parrilla: tres franjas hundidas, como en las fotos.
        for (let i = 0; i < 3; i++) {
            prisma(x0 - 0.025, capo * (0.30 + i * 0.21), z0 + 0.16, 0.03, w - 0.32, capo * 0.14, [62, 64, 70]);
        }

        // Paragolpes con el escalón central + faros en las esquinas bajas.
        prisma(x0 - 0.09, 0.06, z0 + 0.01, 0.11, w - 0.02, capo * 0.30, CABINA, G);
        prisma(x0 - 0.10, 0.06, z0 + w * 0.34, 0.12, w * 0.32, capo * 0.20, [54, 56, 62]);
        for (const z of [z0 + 0.04, z0 + w - 0.30]) {
            prisma(x0 - 0.08, capo * 0.30, z, 0.05, 0.26, 0.13, [242, 243, 232], { borde: 'rgba(0,0,0,.3)' });
        }

        // Espejos grandes sobre brazos, a la altura del parabrisas.
        for (const z of [z0 - 0.22, z0 + w + 0.04]) {
            prisma(x0 + largo * 0.13, alto * 0.58, z, 0.05, 0.18, 0.36, [56, 58, 64], G);
            prisma(x0 + largo * 0.13, alto * 0.72, z > z0 ? z - 0.06 : z + 0.18, 0.04, 0.08, 0.04, [44, 46, 52]);
        }

        // Guardabarro blanco sobre la rueda delantera + estribo bajo la puerta.
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) {
            guardabarroClaro(-M.largoCab * 0.60, -M.chas, z, M.r, M.rw);
        }
        for (const z of [z0 - 0.05, z0 + w - 0.20]) {
            prisma(x0 + largo * 0.42, -M.chas * 0.4, z, largo * 0.34, 0.25, 0.06, [96, 99, 106], G);
        }

        // Tanque de combustible y pasarela detrás de la cabina.
        prisma(x0 + largo + 0.10, -M.chas + 0.02, z0 - 0.06, 0.70, 0.34, 0.44, [176, 180, 188], G);
    }

    /** Guardabarro CLARO (carrocería) en vez del gris del chasis: en el tracto real
     *  el pasarruedas delantero es del color de la cabina. */
    function guardabarroClaro(cx, cyBase, z, r, ancho) {
        const N = 7, R = r + 0.09, cen = cyBase - r, arco = [];
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
            cola.push({ z: q.reduce((s, p) => s + p[2], 0) / 4, pts: q, fill: rgb(CABINA, 0.94), borde: null });
        }
    }

    /**
     * Cabina del HD35, moldeada sobre las fotos del dueño (05-08).
     *
     * Lo que más lo delataba: en el HD35 la CAJA es más ANCHA y bastante más ALTA que
     * la cabina —el furgón sobresale por los costados y queda muy por encima del
     * techo— y acá se dibujaba todo del mismo ancho y casi del mismo alto. Por eso la
     * cabina lleva su propio `anchoCab` en vez de heredar el de la caja.
     *
     * El resto sale de las fotos: cabina corta de techo plano (no hay dormitorio),
     * parabrisas grande, panel blanco del morro con la parrilla negra debajo, faros
     * verticales con el ámbar hacia afuera, paragolpes de parte baja negra, espejos
     * negros grandes sobre brazos que pasan el ancho de la caja, calco gris diagonal en
     * la puerta y estribo.
     */
    function cabinaLiviana() {
        const largo = M.largoCab, alto = M.altoCab;
        // La cabina es más angosta que la caja: es LA marca del HD35 con furgón.
        const anchoCab = Math.min(veh.ancho - 0.22, 1.78);
        const z0 = (veh.ancho - anchoCab) / 2;
        const x0 = -largo, y0 = -M.chas;                 // arranca al ras del chasis
        const h = alto - y0;

        // Cuerpo. El frente es casi vertical con el morro apenas saliente abajo.
        cuerpo(cuna(x0, y0, z0, largo, anchoCab, h, largo * 0.05, 0), CABINA, G);
        // Techo plano con una ceja mínima (no hay deflector ni cajón).
        prisma(x0 + 0.02, alto, z0 + 0.02, largo * 0.94, anchoCab - 0.04, 0.05, CABINA, G);

        // Reparto del frente sacado de las fotos, de arriba abajo: parabrisas ~35%,
        // panel blanco del logo, parrilla NEGRA y paragolpes. La primera versión le daba
        // 40% al parabrisas y dejaba el panel tan finito que la parrilla no se veía.
        //
        // Parabrisas, más angosto que la cara para que queden los parantes blancos.
        cuerpo(cuna(x0 - 0.02, y0 + h * 0.56, z0 + 0.10, largo * 0.13, anchoCab - 0.20,
            h * 0.34, largo * 0.04, 0), VIDRIO, { grad: true, borde: 'rgba(0,0,0,.35)' });

        // Panel blanco del morro (donde va el logo).
        prisma(x0 - 0.035, y0 + h * 0.34, z0 + 0.08, 0.04, anchoCab - 0.16, h * 0.20, CABINA, G);
        // Parrilla negra, bien salida para que no la tape el panel.
        prisma(x0 - 0.05, y0 + h * 0.16, z0 + 0.14, 0.05, anchoCab - 0.28, h * 0.16, [38, 40, 44]);

        // Paragolpes: parte alta clara y parte baja NEGRA.
        prisma(x0 - 0.08, y0 + h * 0.03, z0 + 0.01, 0.10, anchoCab - 0.02, h * 0.11, CABINA, G);
        prisma(x0 - 0.09, y0 - 0.03, z0 + 0.01, 0.11, anchoCab - 0.02, h * 0.07, [48, 50, 55]);

        // Faros verticales en las esquinas, con el ámbar hacia afuera.
        for (const z of [z0 + 0.02, z0 + anchoCab - 0.24]) {
            prisma(x0 - 0.07, y0 + h * 0.17, z, 0.05, 0.22, h * 0.14, [242, 243, 234], { borde: 'rgba(0,0,0,.3)' });
        }
        for (const z of [z0 - 0.015, z0 + anchoCab - 0.045]) {
            prisma(x0 - 0.06, y0 + h * 0.18, z, 0.04, 0.06, h * 0.11, [228, 150, 38]);
        }

        // Espejos negros grandes: pasan el ancho de la CAJA, no solo el de la cabina.
        for (const z of [z0 - 0.26, z0 + anchoCab + 0.04]) {
            prisma(x0 + largo * 0.20, alto - h * 0.30, z, 0.05, 0.22, 0.30, [46, 48, 54], G);
        }

        // Calco gris diagonal de la puerta + estribo.
        for (const z of [z0 - 0.005, z0 + anchoCab - 0.02]) {
            cuerpo(cuna(x0 + largo * 0.34, y0 + h * 0.34, z, largo * 0.62, 0.025, h * 0.16,
                largo * 0.30, 0), [126, 130, 138], { borde: null });
        }
        for (const z of [z0 - 0.04, z0 + anchoCab - 0.18]) {
            prisma(x0 + largo * 0.40, y0 - 0.04, z, largo * 0.44, 0.22, 0.05, [88, 91, 98], G);
        }
    }

    /** Cabina de los camiones de reparto medianos (HINO, Chevy). Sigue siendo genérica:
     *  esperan sus propias fotos, y moldearlas a ojo mientras tanto sería inventar. */
    function cabina() {
        const x0 = -M.delante, w = veh.ancho - 0.06, z0 = 0.03;
        const alto = M.altoCab, cuerpoBajo = alto * 0.52, largo = M.largoCab;

        prisma(x0, 0, z0, largo, w, cuerpoBajo, CABINA, G);
        // Parabrisas reclinado: el techo se mete hacia atrás.
        cuerpo(cuna(x0, cuerpoBajo, z0, largo, w, alto - cuerpoBajo, 0, largo * 0.30), CABINA, G);
        // Vidrio, apenas por delante del parabrisas.
        cuerpo(cuna(x0 - 0.015, cuerpoBajo + 0.06, z0 + 0.05, largo * 0.32, w - 0.1,
            (alto - cuerpoBajo) * 0.72, 0, largo * 0.22), VIDRIO, { grad: true, borde: 'rgba(0,0,0,.3)' });
        // Paragolpes y parrilla.
        prisma(x0 - 0.10, 0.05, z0 + 0.02, 0.12, w - 0.04, cuerpoBajo * 0.42, GRIS, G);
        prisma(x0 - 0.03, cuerpoBajo * 0.56, z0 + 0.10, 0.05, w - 0.2, cuerpoBajo * 0.28, [40, 42, 48], G);
        // Espejos.
        for (const z of [z0 - 0.15, z0 + w + 0.03]) {
            prisma(x0 + largo * 0.24, cuerpoBajo + 0.10, z, 0.05, 0.12, 0.25, [45, 47, 52]);
        }
    }

    /**
     * Chapa con el rótulo del camión, atrás y abajo (pedido del dueño 05-08). Va
     * sobre la cara trasera de la caja, así que se lee justo desde donde se mira la
     * puerta. El texto NO se dibuja acá: se pinta en la pasada de arriba
     * (`textoChapa`), porque encolarlo entre las caras lo taparía la carga.
     */
    function chapa() {
        if (!ROTULO) return;
        const alto = 0.17;
        const ancho = Math.min(0.70, Math.max(0.34, veh.ancho * 0.30));
        chapaCaja = { x: veh.largo, y: 0.06, z: (veh.ancho - ancho) / 2, ancho, alto };
        prisma(chapaCaja.x, chapaCaja.y, chapaCaja.z, 0.03, ancho, alto, CHAPA, { borde: 'rgba(0,0,0,.4)' });
    }

    /**
     * El texto de la chapa, en la pasada de arriba. Se saltea cuando la cara trasera
     * no nos está mirando (girado el camión, el rótulo quedaría espejado) o cuando en
     * pantalla mide tan poco que no se leería.
     */
    function textoChapa() {
        if (!chapaCaja || !ROTULO) return;
        const c = chapaCaja;
        const medio = c.y + c.alto / 2;
        const centro = proyectar([c.x + 0.03, medio, c.z + c.ancho / 2]);
        const adentro = proyectar([c.x - 0.4, medio, c.z + c.ancho / 2]);
        if (centro[2] > adentro[2]) return;    // la chapa quedó del lado de atrás

        const orilla = proyectar([c.x + 0.03, medio, c.z]);
        const anchoPx = Math.hypot(centro[0] - orilla[0], centro[1] - orilla[1]) * 2;
        if (anchoPx < 34) return;              // de canto o muy chica

        const tam = Math.max(7, Math.min(20, (anchoPx * 1.5) / Math.max(5, ROTULO.length)));
        ctx.font = `700 ${tam.toFixed(1)}px -apple-system,system-ui,sans-serif`;
        ctx.textAlign = 'center';
        ctx.fillStyle = '#3f3f46';
        ctx.fillText(ROTULO, centro[0], centro[1] + tam * 0.36);
        ctx.textAlign = 'left';
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
        chapa();
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
        if (M.liviano) cabinaLiviana(); else cabina();
        cajaDeCarga();

        // Rueda delantera SIMPLE y trasera DOBLE en los dos: el HD35 también lleva
        // ruedas gemelas atrás (se ve en la foto de la trasera del chasis), y antes se
        // le dibujaba una sola por eje.
        const anchoTras = M.rw * 2 + 0.03;
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) rueda(-M.largoCab * 0.58, -M.chas, z, M.r, M.rw);
        for (const z of [-0.03, veh.ancho - anchoTras + 0.03]) {
            rueda(veh.largo * 0.74, -M.chas, z, M.r, M.rw, true);
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
        cabinaTracto();
        cajaDeCarga();
        // Patas de apoyo: sin ellas el acoplado parece flotar.
        for (const z of [veh.ancho * 0.16, veh.ancho * 0.78]) {
            prisma(veh.largo * 0.30, M.suelo + 0.06, z, 0.09, 0.09, -M.chas - M.suelo - 0.06, [96, 99, 106], G);
        }

        const anchoDoble = M.rw * 2 + 0.03;
        // Eje delantero simple del tracto + TÁNDEM trasero doble: en las fotos del
        // Actros se ven las dos ruedas juntas detrás de la cabina (es un 6×4), y antes
        // el tracto tenía un solo eje y parecía apoyado en el aire.
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) rueda(-M.largoCab * 0.60, -M.chas, z, M.r, M.rw);
        for (const ex of [-M.sep - 0.10, -M.sep + 1.25]) {
            for (const z of [-0.03, veh.ancho - anchoDoble + 0.03]) rueda(ex, -M.chas, z, M.r, M.rw, true);
        }
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
        dibujadosPorBloque = bloques.map(() => 0);

        for (const [i, blq] of bloques.entries()) {
            if (puestos >= cant) break;
            const rej = blq.rejilla, ori = blq.orientacion, col = blq.color || [234, 88, 12];
            const capa = rej.ancho * rej.alto;
            const dibujables = Math.max(0, Math.min(blq.cantidad, rej.largo * capa, cant - puestos));

            // Los bidones cuestan ~6 veces más polígonos que un bulto rectangular, y
            // se dibujan uno por bolsa. Con cientos de bolsas (el contenedor lleva
            // 324) se cae al bulto rectangular: a ese tamaño en pantalla se ve
            // prácticamente igual y el arrastre se mantiene fluido. El límite se fijó
            // MIDIENDO los polígonos por frame, no a ojo (ver las reglas).
            const bidones = blq.forma === 'botellones' && dibujables <= TOPE_BIDONES;

            const indice = (ix, iz, iy) => ix * capa + iz * rej.alto + iy;
            const puesto = (ix, iz, iy) => ix >= 0 && ix < rej.largo && iz >= 0 && iz < rej.ancho
                && iy >= 0 && iy < rej.alto && indice(ix, iz, iy) < dibujables;

            for (let n = 0; n < dibujables; n++) {
                const ix = Math.floor(n / capa), resto = n % capa;
                const iz = Math.floor(resto / rej.alto), iy = resto % rej.alto;

                if (puesto(ix - 1, iz, iy) && puesto(ix + 1, iz, iy)
                    && puesto(ix, iz - 1, iy) && puesto(ix, iz + 1, iy)
                    && puesto(ix, iz, iy - 1) && puesto(ix, iz, iy + 1)) continue;

                const px = blq.x + ix * ori.largo, py = iy * ori.alto, pz = blq.y + iz * ori.ancho;
                if (bidones) {
                    bolsaDeBidones(px, py, pz, ori.largo * 0.985, ori.ancho * 0.985, ori.alto * 0.985, col);
                } else {
                    prisma(px, py, pz, ori.largo * 0.985, ori.ancho * 0.985, ori.alto * 0.985, col);
                }
            }
            dibujadosPorBloque[i] = dibujables;
            puestos += dibujables;
        }
    }

    /**
     * Nombre de cada producto sobre SU bloque, con una línea que lo ancla.
     *
     * UNA etiqueta por bloque, no por bulto: con 324 bultos serían 324 textos
     * ilegibles y lentos. Un bloque = un producto, así que son 2-4 etiquetas y se
     * leen. Van DESPUÉS de `pintar()`, encima de todo, porque una etiqueta tapada
     * por la carga no sirve de nada.
     *
     * Si dos quedan pegadas se separan hacia arriba (las de más adelante primero):
     * superpuestas no se lee ninguna de las dos.
     */
    function etiquetas() {
        if (!nombres || bloques.length === 0) return;

        // UNA etiqueta por PRODUCTO, no por bloque: el acomodo por zonas puede partir
        // un mismo producto en dos o tres bloques, y rotularlos todos repetía el
        // nombre («Caja de soportes» dos veces con números distintos). Se rotula el
        // bloque más grande de cada producto y el número es el TOTAL del producto.
        const porProducto = new Map();
        for (const [i, blq] of bloques.entries()) {
            if (!blq.nombre || !dibujadosPorBloque[i]) continue;
            const g = porProducto.get(blq.nombre) ?? { puestos: 0, total: 0, mayor: -1, bloque: null };
            g.puestos += dibujadosPorBloque[i];
            g.total += blq.cantidad;
            if (dibujadosPorBloque[i] > g.mayor) { g.mayor = dibujadosPorBloque[i]; g.bloque = blq; }
            porProducto.set(blq.nombre, g);
        }

        const puestas = [];
        for (const [nombre, g] of porProducto) {
            const blq = g.bloque;
            const rej = blq.rejilla, ori = blq.orientacion;
            // Ancla: el centro del techo del bloque.
            const ancla = proyectar([
                blq.x + (rej.largo * ori.largo) / 2,
                rej.alto * ori.alto,
                blq.y + (rej.ancho * ori.ancho) / 2,
            ]);
            // A medio cargar (los pasos, o la animación) dice CUÁNTOS VAN de cuántos:
            // poner solo el total mientras se ven 18 de 84 hace dudar de qué número
            // creer. Los números son del PRODUCTO, sumando todas sus zonas.
            puestas.push({
                ancla,
                texto: nombre,
                cuenta: g.puestos < g.total ? `${g.puestos} de ${g.total}` : String(g.total),
                col: blq.color || [234, 88, 12],
            });
        }

        // De adelante hacia atrás: la de adelante manda y las de atrás ceden.
        puestas.sort((a, b) => a.ancla[2] - b.ancla[2]);
        const ocupadas = [];
        ctx.font = '600 15px -apple-system,system-ui,sans-serif';
        ctx.textAlign = 'left';

        for (const e of puestas) {
            const texto = `${e.texto} · ${e.cuenta}`;
            const ancho = ctx.measureText(texto).width + 32;
            const alto = 26;
            let x = Math.min(Math.max(8, e.ancla[0] - ancho / 2), canvas.width - ancho - 8);
            let y = e.ancla[1] - 46;

            while (ocupadas.some((o) => Math.abs(o.y - y) < alto + 4 && Math.abs(o.x - x) < (o.ancho + ancho) / 2)) {
                y -= alto + 6;
            }
            y = Math.max(6, y);
            ocupadas.push({ x, y, ancho });

            // Línea de anclaje al techo del bloque.
            ctx.beginPath();
            ctx.moveTo(e.ancla[0], e.ancla[1]);
            ctx.lineTo(x + ancho / 2, y + alto);
            ctx.strokeStyle = 'rgba(70,70,75,.45)';
            ctx.lineWidth = 1;
            ctx.stroke();

            // Cartel.
            ctx.beginPath();
            ctx.roundRect(x, y, ancho, alto, 7);
            ctx.fillStyle = 'rgba(255,255,255,.94)';
            ctx.fill();
            ctx.strokeStyle = 'rgba(0,0,0,.14)';
            ctx.stroke();

            // Punto del color del bloque: es la misma leyenda que la lista de abajo.
            ctx.beginPath();
            ctx.arc(x + 13, y + alto / 2, 5, 0, Math.PI * 2);
            ctx.fillStyle = rgb(e.col);
            ctx.fill();

            ctx.fillStyle = '#3f3f46';
            ctx.fillText(texto, x + 24, y + alto / 2 + 5);
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

        escBase = Math.min(canvas.width * 0.93 / Math.max(0.01, x1 - x0),
            canvas.height * 0.88 / Math.max(0.01, y1 - y0));
        centro = [(x0 + x1) / 2, (y0 + y1) / 2];
        yaw = yaw0; pitch = pitch0;
        reiniciarVista();
    }

    /** Vuelve al encuadre y al ángulo con que abre la pantalla. */
    function reiniciarVista() {
        zoom = 1;
        yaw = -0.85; pitch = -0.3;
        ESC = escBase;
        CX = canvas.width / 2 - centro[0] * ESC;
        CY = canvas.height / 2 - centro[1] * ESC;
    }

    /**
     * Acerca o aleja ANCLANDO un punto de la pantalla: el punto que estaba bajo el
     * cursor sigue estando ahí después de acercar. Por eso apuntar a la carga y
     * girar la rueda acerca LA CARGA, que es lo que se pidió — un zoom al centro
     * geométrico del camión deja la carga fuera de cuadro.
     *
     * Sin `px`/`py` ancla el centro del lienzo (los botones + / −).
     */
    function acercar(factor, px = canvas.width / 2, py = canvas.height / 2) {
        const antes = zoom;
        zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoom * factor));
        if (zoom === antes) return;

        // Punto del mundo proyectado que está bajo (px, py), en unidades de escala 1.
        const ux = (px - CX) / ESC, uy = (py - CY) / ESC;
        ESC = escBase * zoom;
        CX = px - ux * ESC;
        CY = py - uy * ESC;
    }

    /** Pasa las coordenadas del puntero a píxeles del lienzo (se dibuja a 1240×720
     *  y el CSS lo escala, así que sin esto el ancla del zoom queda corrida). */
    function aLienzo(e) {
        const r = canvas.getBoundingClientRect();
        return [
            (e.clientX - r.left) * (canvas.width / Math.max(1, r.width)),
            (e.clientY - r.top) * (canvas.height / Math.max(1, r.height)),
        ];
    }

    function dibujar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (M.semi) siluetaSemirremolque(); else siluetaCamion();
        bultos();
        pintar();
        etiquetas();
        textoChapa();

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

    /**
     * ZOOM SOLO EN ESCRITORIO (pedido del dueño 05-08-2026: «no lo quiero para
     * celular, no quiero que se quede pegada o se ponga lento»).
     *
     * Se cumple por CONSTRUCCIÓN y no por un `if` de ancho de pantalla: el zoom
     * entra por la rueda del mouse —que un táctil no emite— y por botones que la
     * vista esconde con `hidden lg:flex`. NO se registra ningún handler de touch ni
     * de pinza: si mañana se quiere en celular, hay que agregarlo a propósito y
     * medir antes que no se ponga lento.
     */
    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const [px, py] = aLienzo(e);
        acercar(e.deltaY < 0 ? 1.12 : 1 / 1.12, px, py);
        dibujar();
    }, { passive: false });

    const boton = (id, fn) => {
        const b = document.getElementById(id);
        if (b) b.addEventListener('click', fn);
        return b;
    };

    boton('carga3dMas', () => { acercar(1.25); dibujar(); });
    boton('carga3dMenos', () => { acercar(1 / 1.25); dibujar(); });
    boton('carga3dReset', () => { reiniciarVista(); dibujar(); });

    const btnNombres = boton('carga3dNombres', () => {
        nombres = !nombres;
        btnNombres.setAttribute('aria-pressed', String(nombres));
        btnNombres.classList.toggle('bg-neutral-100', !nombres);
        btnNombres.classList.toggle('text-neutral-400', !nombres);
        dibujar();
    });

    /**
     * Fija cuántos bultos se ven cargados. Corta la animación si estaba corriendo:
     * si no, seguiría sumando por su cuenta y pelearía con el botón que se acaba de
     * tocar.
     */
    const fijar = (n) => {
        clearInterval(anim);
        cant = Math.max(0, Math.min(TOPE, Math.round(n)));
        dibujar();
    };

    boton('carga3dVaciar', () => fijar(0));
    boton('carga3dTodo', () => fijar(TOPE));
    boton('carga3dQuita1', () => fijar(cant - 1));
    boton('carga3dSuma1', () => fijar(cant + 1));
    boton('carga3dSuma5', () => fijar(cant + 5));
    boton('carga3dSuma10', () => fijar(cant + 10));

    boton('carga3dPlay', () => {
        clearInterval(anim);
        cant = 0;
        const paso = Math.max(1, Math.round(TOPE / 70));
        anim = setInterval(() => {
            cant = Math.min(TOPE, cant + paso);
            dibujar();
            if (cant >= TOPE) clearInterval(anim);
        }, 45);
    });

    encuadrar();
    dibujar();
}
