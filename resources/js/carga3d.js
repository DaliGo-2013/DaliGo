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
 * UNA SILUETA POR CAMIÓN (`veh.silueta`, que manda el controlador). Empezó con tres
 * genéricas —un contenedor de 40' no tiene cabina propia porque viaja sobre el
 * semirremolque, y un HD35 de 4,3 m no es un camión de reparto mediano— y el dueño
 * pidió después «un modelo por cada camión», así que las va moldeando sobre fotos de
 * su flota, de a una: `semirremolque` (Actros + Tremac), `camion_liviano` (HD35),
 * `camion_hino` (HINO 500 FC 1118) y `camion`, la genérica de respaldo. Al venderse el
 * Chevy 3 (05-08) los tres camiones del catálogo tienen silueta propia y la genérica
 * quedó solo para un camión sin silueta declarada. Cada cabina tiene su propia función:
 * NO meterlas todas en una con banderas.
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

/**
 * Prisma con el techo EN PENDIENTE: mide `cAdelante` de alto en x y `cAtras` en x+a.
 * `cuna` no sirve para esto —corre el techo en x pero lo deja horizontal— y la cuña
 * de aire del techo de la cabina (el rompeviento del HINO) es justamente una rampa:
 * baja adelante, alta atrás, contra el frente del furgón.
 *
 * Sale plana cara por cara, así que entra por el mismo pipeline y el sombreado por
 * cara le da el volumen sin necesitar normales.
 */
const rampa = (x, y, z, a, b, cAdelante, cAtras) => [
    [x, y, z], [x + a, y, z], [x + a, y + cAtras, z], [x, y + cAdelante, z],
    [x, y, z + b], [x + a, y, z + b], [x + a, y + cAtras, z + b], [x, y + cAdelante, z + b],
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

/**
 * Nombre para el cartel del lienzo. Con tres productos, tres carteles de 300 px
 * tapan más carga de la que explican — el nombre completo está en la lista de
 * abajo y la LETRA alcanza para saber cuál es cuál.
 *
 * Primero suelta el paréntesis del final, que es lo menos distintivo del catálogo
 * de Dali («(vacío)» lo llevan todas las bolsas, y lo que las separa es el «20 L»
 * contra el «10 L»). Recortar por largo lo hacía terminar en «20 L (v…», que es
 * peor que no decirlo. Si aun así no entra, ahí sí recorta por el final: el
 * principio del nombre es lo que distingue.
 */
const nombreCorto = (n) => {
    const limpio = n.length > 26 ? n.replace(/\s*\([^)]*\)\s*$/, '') : n;

    return limpio.length > 26 ? `${limpio.slice(0, 25).trimEnd()}…` : limpio;
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
    let escBase = 100, centro = [0, 0], zoom = 1, nombres = true, codigos = true, vistaActual = '3d';
    /**
     * DESPLAZAMIENTO con el botón DERECHO (pedido del dueño 12-08-2026, con la ayuda
     * de EasyCargo en la mano: «izquierdo gira, derecho recorre el espacio de carga,
     * rueda acerca»). Faltaba el del medio: girar y acercar ya estaban.
     *
     * Es un corrimiento EN PÍXELES DE PANTALLA y no un movimiento de cámara en el
     * mundo: la proyección de este visor es paralela (ver `proyectar`), así que
     * mover la cámara de costado y correr el dibujo dan exactamente la misma imagen
     * — y el corrimiento en pantalla no puede desalinear el encuadre ni el zoom.
     *
     * Se guarda APARTE de CX/CY, que se recalculan solos cada vez que el encuadre se
     * vuelve a medir (girar con zoom 1 lo hace en cada frame). Si el desplazamiento
     * viviera dentro de CX/CY, se borraría en cuanto el usuario girara un grado.
     */
    let pan = [0, 0];
    // Acumulador de la medición del encuadre. Distinto de null = se está midiendo, y
    // en ese rato nada debe pintar en el lienzo (ver `medirEncuadre`).
    let midiendo = null;
    // Medidas LÓGICAS del lienzo, en píxeles de CSS. TODO el dibujo se hace en estas
    // coordenadas, no en las del mapa de bits (ver `ajustarLienzo`): así una pantalla
    // de alta densidad se ve nítida sin que las letras salgan a la mitad de tamaño.
    let AW = canvas.width, AH = canvas.height;
    /**
     * Cuánto se puede alejar y acercar, respecto del encuadre automático (zoom 1).
     *
     * ALEJARSE llegaba solo a 0,7 — o sea, un 30% más chico que el encuadre, que es
     * casi nada. El dueño lo pidió explícito (10-08): «el zoom está bien, pero el
     * lejos es lo que más quiero, para ver más posibilidades de cargar». Tiene
     * sentido: acercarse sirve para mirar UN bulto, alejarse sirve para pensar la
     * carga entera y ver dónde queda hueco.
     *
     * A 0,25 el camión entra cuatro veces en el ancho del recuadro. No se baja más
     * porque a esa escala la silueta ya mide ~90 px y deja de distinguirse de un
     * rectángulo; y los códigos sobre los bultos se apagan solos mucho antes, por
     * el LOD de CODIGO_MIN, así que alejarse tampoco cuesta rendimiento.
     */
    const ZOOM_MIN = 0.25, ZOOM_MAX = 4;
    // Hasta cuántas bolsas se dibujan como BIDONES antes de caer al bulto rectangular
    // (ver `bultos`). Medido sobre los polígonos por frame, no elegido a ojo.
    const TOPE_BIDONES = 150;
    // Los códigos sobre las cajas solo tienen sentido si hay MÁS DE UN producto: con
    // uno solo, escribir «A» en las 420 bolsas no distingue nada y solo ensucia. El
    // botón sigue estando (igual que «Nombres») para no cambiar los controles según
    // la carga, pero no dibuja.
    const VARIOS = new Set(bloques.map((b) => b.nombre)).size > 1;
    // Modo SOBRE PALLET: el pallet arranca EN EL PISO al lado del camión y se sube con un
    // botón. Sin pallet en la escena `subido` vale true desde el principio, así que la
    // carga suelta se dibuja como siempre.
    const PALLET = !!datos.pallet;
    let subido = !PALLET;
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
        const hino = veh.silueta === 'camion_hino';
        const nqr = veh.silueta === 'camion_nqr';
        const chas = semi ? 0.24 : (liviano ? 0.14 : 0.20);
        const r = semi ? 0.46 : (liviano ? 0.32 : 0.46);
        const rw = semi ? 0.24 : (liviano ? 0.17 : 0.22);
        const sep = semi ? 0.35 : 0;   // hueco entre el tracto y el frente del acoplado
        // El NQR es un cab-over corto: la cabina mide menos que la del HINO y, en las
        // fotos, el furgón le saca bastante en alto (el techo de la cabina queda a ~7/10).
        const largoCab = Math.min(semi ? 2.6 : (liviano ? 1.45 : (hino ? 1.95 : (nqr ? 1.85 : 2.15))), veh.largo * (semi ? 0.25 : 0.42));
        const altoCab = semi
            ? Math.min(veh.alto * 1.05, 2.35)
            : (liviano ? Math.min(veh.alto * 0.60, 1.35)
                : (hino ? Math.min(veh.alto * 0.68, 1.95)
                    : (nqr ? Math.min(veh.alto * 0.70, 1.90) : Math.min(veh.alto * 0.78, 2.05))));
        return {
            semi, liviano, hino, nqr, chas, r, rw, sep, largoCab, altoCab,
            // Dónde va el EJE DELANTERO, medido desde el frente de la caja hacia atrás.
            //
            // En el tracto estaba al 60% de la cabina y quedaba casi pegado al tándem
            // (1,11 m entre centros contra ruedas de 0,92: quedaban 19 cm de aire y se
            // veían las tres juntas, no como un eje delantero — reporte del dueño 06-08).
            // Al 82% queda bajo el frente de la cabina, que es donde va en un cab-over
            // real, y se separa 1,68 m del tándem.
            //
            // Un solo valor para el silueteado Y el guardabarro: estaban escritos dos
            // veces y mover uno dejaba el otro flotando sobre la nada.
            ejeDel: -largoCab * (semi ? 0.82 : 0.58),
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
     * LA LÍNEA QUE SEPARA UNA CAJA DE LA DE AL LADO (pedido del dueño 11-08-2026).
     *
     * El borde por defecto de `cuerpo()` es negro al 22% y sirve para la chapa del
     * camión, donde hay una arista cada tanto. En la carga hay CIENTOS de caras pegadas
     * del mismo color, y al 22% una pared de 40 cajas se lee como un bloque macizo.
     *
     * Lo que se pierde no es estética: es el HUECO. Sin ver dónde termina cada caja no
     * se ve que falta una, y ver el espacio que sobra es para lo que se mira el dibujo.
     */
    const BORDE_BULTO = 'rgba(17,17,20,.55)';

    /**
     * Diagonal mínima EN PANTALLA (px) para dibujarle la línea a un bulto.
     *
     * Es el mismo LOD que ya usan los códigos (`CODIGO_MIN`) y por el mismo motivo, pero
     * al revés de lo que uno esperaría: el problema no es que la línea no se vea, es que
     * se vea DEMASIADO. Alejado, cada caja mide pocos píxeles y su contorno ocupa casi
     * toda su superficie: 400 cajas del contenedor se vuelven una mancha negra y
     * desaparece justo el color que dice de qué producto es cada bloque.
     *
     * MEDIDO, no elegido, contando píxeles dentro de la zona de la carga en el contenedor
     * lleno (400 cajas de tapas): sin umbral la línea se comía el **17,9%** del área, que
     * a esa distancia es una reja negra. El valor se subió hasta que el dibujo alejado
     * vuelve a leerse por color y la línea aparece al acercarse.
     *
     * Se compara contra el LADO MÁS LARGO proyectado, no contra la diagonal del cuerpo:
     * la diagonal suma la profundidad y sobreestima el tamaño aparente — con ella, cajas
     * que en pantalla medían 13 px pasaban el filtro.
     */
    const BORDE_MIN = 30;

    /**
     * Cuánto se encoge cada bulto para que quede una hendija entre vecinos: 1,5%.
     *
     * NO se agranda para «separar más». El dibujo tiene que ser fiel al cálculo —las
     * cajas van pegadas de verdad— y un hueco inventado dibujaría un acomodo que el
     * motor no calculó, que es el pecado del §2 en versión visual. La separación se
     * consigue con la LÍNEA, no con aire.
     */
    const SEPARACION = 0.985;

    /**
     * Tamaño mínimo de letra, en píxeles, para escribir el código de un bulto. Debajo
     * de esto es una manchita que ensucia en vez de aclarar — y hace de LOD gratis:
     * alejado no se escribe nada, al acercarte aparecen los códigos.
     *
     * El 8 está MEDIDO, no elegido: con el umbral en 11 no se escribía ni un código en
     * la carga real del dueño (HINO con bolsas de bidones, cajas de soportes y
     * dispensadores). La cara que se ve de una bolsa de 26 × 51 cm, girada y en fuga,
     * mide unos 17 px de lado corto, así que pedía una letra de 12 px y el umbral la
     * descartaba. Al dibujarse en alta densidad, esos 12 px salen nítidos.
     */
    const CODIGO_MIN = 8;

    /**
     * Escribe el código del producto sobre la cara VISIBLE más cercana del bulto.
     *
     * Es «las cajas escritas con códigos» de EasyCargo, que el dueño señaló como
     * una de las tres cosas que más le sirven de esa app (05-08-2026): con el color
     * solo, dos productos de tono parecido se confunden y no se pueden nombrar en
     * voz alta.
     *
     * UNA cara por bulto, la más cercana a la cámara: escribir en las tres caras
     * visibles triplicaría el texto para no aclarar nada, y elegir «la de la
     * puerta» dejaría los códigos invisibles en la vista de costado. Al elegir por
     * cercanía, la vista no importa: siempre cae en una cara que se ve.
     */
    function codigoEnBulto(x, y, z, a, b, c, letra, adentro = false) {
        const pv = v8(x, y, z, a, b, c).map(proyectar);
        const zCentro = pv.reduce((s, p) => s + p[2], 0) / 8;

        let mejor = null;
        for (const f of CARAS) {
            const zc = (pv[f[0]][2] + pv[f[1]][2] + pv[f[2]][2] + pv[f[3]][2]) / 4;
            if (zc > zCentro + 1e-9) continue;      // mira para el otro lado
            if (!mejor || zc < mejor.zc) mejor = { zc, pts: f.map((i) => pv[i]) };
        }
        if (!mejor) return;

        // `adentro`: el bulto no se dibuja como un prisma lleno sino con CONTENIDO
        // adentro (los bidones de la bolsa). Esos cilindros son tangentes a la cara,
        // así que quedan a la misma profundidad que el código y lo tapaban — la letra
        // salía sobre las cajas y sobre los bidones de pie, pero no sobre los
        // acostados. Se adelanta hasta el vértice MÁS CERCANO del bulto, que por
        // definición está delante de todo lo que el bulto tenga adentro.
        const zFrente = adentro ? Math.min(...pv.map((p) => p[2])) : mejor.zc;

        const [p0, p1, , p3] = mejor.pts;
        const lado = (u, v) => Math.hypot(u[0] - v[0], u[1] - v[1]);
        // La letra se dimensiona con el lado CORTO de la cara: con el largo, una cara
        // muy en fuga (casi de perfil) pediría una letra que no entra.
        const px = Math.min(lado(p0, p1), lado(p0, p3)) * 0.72;
        if (px < CODIGO_MIN) return;

        cola.push({
            // Apenas por delante de su cara: si empatan, el orden de `sort` no está
            // definido y el código parpadearía al girar.
            z: zFrente - 1e-6,
            pts: null,
            letra,
            // Tope: la cara de una bolsa vista de costado mide 1,30 × 0,51 m y pedía una
            // letra de 30 px que se comía el bulto. A 22 se lee igual y no compite con
            // el dibujo.
            px: Math.min(px, 22),
            x: mejor.pts.reduce((s, p) => s + p[0], 0) / 4,
            y: mejor.pts.reduce((s, p) => s + p[1], 0) / 4,
        });
    }

    /** Pinta una entrada de código: letra clara con filo oscuro, para que se lea
     *  igual sobre un bloque naranja que sobre uno azul. */
    function pintarCodigo(o) {
        ctx.font = `700 ${o.px.toFixed(1)}px -apple-system,system-ui,sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.lineWidth = Math.max(2, o.px * 0.18);
        ctx.strokeStyle = 'rgba(0,0,0,.55)';
        ctx.strokeText(o.letra, o.x, o.y);
        ctx.fillStyle = 'rgba(255,255,255,.96)';
        ctx.fillText(o.letra, o.x, o.y);
        ctx.textAlign = 'left';
        ctx.textBaseline = 'alphabetic';
    }

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
    function bolsaDeBidones(x, y, z, l, w, h, col, estiba = 'pie') {
        // LAS TRES ESTIBAS que describió el dueño. Cada una cambia el EJE del botellón y
        // la dirección en que se cuenta la fila de cinco, así que el dibujo tiene que
        // seguir al cálculo o el lienzo deja de ser la prueba de lo que el motor hizo:
        //
        //   pie      130 × 26 × 51  eje vertical, fila a lo largo del camión
        //   costado  130 × 51 × 26  eje cruzando el camión, fila a lo largo
        //   pico      51 × 130 × 26 eje mirando a la puerta, fila CRUZANDO el camión
        //
        // El grosor es el diámetro del botellón, que en cada caso cae en otra medida del
        // pack: es lo que dice cuántos entran en la fila.
        if (estiba === 'pico') {
            const n = Math.max(1, Math.min(8, Math.round(w / Math.max(0.01, h))));
            const paso = w / n, r = Math.min(paso, h) * 0.46;
            for (let i = 0; i < n; i++) {
                const cz = z + paso * (i + 0.5), cy = y + h / 2;
                cilindroTumbado(cz, cy, x, r, l * 0.80, col, true);                    // cuerpo
                cilindroTumbado(cz, cy, x + l * 0.80, r * 0.42, l * 0.20, col, true);  // pico
            }
        } else {
            const acostado = estiba === 'costado';
            const grosor = Math.max(0.01, acostado ? h : w);
            const n = Math.max(1, Math.min(8, Math.round(l / grosor)));
            const paso = l / n, r = Math.min(paso, grosor) * 0.46;

            for (let i = 0; i < n; i++) {
                const cx = x + paso * (i + 0.5);
                if (acostado) {
                    const cy = y + h / 2;
                    cilindroTumbado(cx, cy, z, r, w * 0.80, col, false);                    // cuerpo
                    cilindroTumbado(cx, cy, z + w * 0.80, r * 0.42, w * 0.20, col, false);  // pico
                } else {
                    const cz = z + w / 2;
                    cilindro(cx, cz, y, r, h * 0.80, col);                       // cuerpo
                    cilindro(cx, cz, y + h * 0.80, r * 0.42, h * 0.20, col);     // pico
                }
            }
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
    /**
     * Cilindro TUMBADO: el eje va horizontal, no a lo alto.
     *
     * Es una función aparte de `cilindro` porque el sombreado y la tapa se calculan
     * sobre planos distintos: la sección circular vive en un plano VERTICAL, así que la
     * luz se mide ahí y no en el del piso — el brillo tiene que quedar arriba del
     * botellón tumbado.
     *
     * `ejeX` sí es una bandera, y acá está bien: es literalmente el EJE del cilindro (a
     * lo largo del camión para «pico a la puerta», a lo ancho para «de costado»), no dos
     * cuerpos distintos disfrazados de uno. Lo único que cambia es a qué coordenada del
     * mundo va cada componente, y eso lo resuelve `punto()` en una línea.
     *
     * Se dibuja la tapa del extremo MÁS CERCANO a la cámara, no siempre la misma: al
     * girar, la punta que se ve cambia, y fijar una dejaba ver el cilindro «abierto»
     * desde la mitad de los ángulos.
     *
     * @param  number  a1  centro sobre el eje horizontal de la sección (x si el eje es z; z si es x)
     * @param  number  cy  centro en altura
     * @param  number  t0  dónde arranca el cilindro sobre su propio eje
     */
    function cilindroTumbado(a1, cy, t0, r, largo, col, ejeX = false) {
        const N = 8, per = [], LUZ = 1.9;   // hacia arriba y un poco al frente
        // Con el eje en x, la sección circular vive en el plano z/y; con el eje en z,
        // en el plano x/y. `punto` es lo único que cambia entre los dos casos.
        const punto = (u, v, t) => (ejeX ? [t, v, u] : [u, v, t]);
        for (let i = 0; i < N; i++) {
            const a = (i * 2 * Math.PI) / N;
            per.push([a1 + Math.cos(a) * r, cy + Math.sin(a) * r, a]);
        }
        const A = per.map((p) => proyectar(punto(p[0], p[1], t0)));
        const B = per.map((p) => proyectar(punto(p[0], p[1], t0 + largo)));
        const zc = (q) => q.reduce((s, p) => s + p[2], 0) / q.length;
        const eje = (zc(A) + zc(B)) / 2;

        for (let i = 0; i < N; i++) {
            const j = (i + 1) % N, q = [A[i], A[j], B[j], B[i]];
            const z = zc(q);
            if (z > eje) continue;   // cara del otro lado del cilindro
            const normal = per[i][2] + Math.PI / N;
            const s = 0.64 + 0.36 * (0.5 + 0.5 * Math.cos(normal - LUZ));
            cola.push({ z, pts: q, fill: rgb(col, s), borde: null });
        }

        const tapa = zc(A) < zc(B) ? A : B;
        cola.push({ z: zc(tapa) - 0.01, pts: tapa, fill: rgb(col, 0.92), borde: 'rgba(0,0,0,.18)' });
    }

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
    /**
     * Los detalles del COSTADO de una cabina: vidrio de la puerta, junta, manija y
     * zócalo. Y arriba, la visera sobre el parabrisas.
     *
     * Nace de un pedido del dueño (05-08-2026: «la cabina del camión, ¿no hay chance de
     * dejarla un poco más real o con más detalle?») mirando la vista de COSTADO, que es
     * donde más se notaba: la cabina era una lámina blanca sin una sola línea. De frente
     * ya tenía parrilla, faros, paragolpes y espejos; de costado, nada.
     *
     * Es un helper con parámetros y NO una cabina más: cada camión lo llama con SUS
     * medidas, así que se sigue cumpliendo «una función de cabina por camión».
     *
     * El vidrio y las líneas se separan 6 mm HACIA AFUERA de la cara del cuerpo. Eso
     * hace dos cosas de una: los pone delante de la chapa cuando ese costado mira a la
     * cámara, y los deja detrás —o sea invisibles— cuando mira para el otro lado, sin
     * necesidad de decidir nada.
     */
    function costadoDeCabina(x0, y0, z0, largo, anchoCab, h, opts = {}) {
        const { puerta = 0.30, vidrioDe = 0.36, vidrioA = 0.94, arriba = 0.86 } = opts;
        const s = 0.006;

        for (const [z, fuera] of [[z0, -s], [z0 + anchoCab, s]]) {
            const zc = z + fuera;
            const cara = (xa, xb, ya, yb, col, o) => panel([
                [xa, ya, zc], [xb, ya, zc], [xb, yb, zc], [xa, yb, zc],
            ], col, o);

            // Vidrio de la puerta. Arranca pasada la junta y muere antes del respaldo:
            // una luneta de punta a punta haría parecer la cabina un microbús.
            cara(x0 + largo * vidrioDe, x0 + largo * vidrioA, y0 + h * 0.55, y0 + h * arriba,
                VIDRIO, { alpha: 0.94, borde: 'rgba(0,0,0,.38)' });

            // Junta de la puerta, de arriba abajo.
            cara(x0 + largo * puerta, x0 + largo * puerta + 0.014, y0 + h * 0.06, y0 + h * 0.95,
                GRIS, { alpha: 0.5 });

            // Manija, apenas debajo del vidrio.
            cara(x0 + largo * (vidrioDe + 0.08), x0 + largo * (vidrioDe + 0.26),
                y0 + h * 0.46, y0 + h * 0.50, [64, 66, 72], {});

            // Zócalo: la franja oscura de abajo. Es lo que le saca el aire de caja
            // flotando, porque apoya la cabina contra el chasis.
            cara(x0 + largo * 0.06, x0 + largo * 0.98, y0, y0 + h * 0.10, [92, 95, 102], { alpha: 0.85 });
        }
    }

    /** Visera sobre el parabrisas: una lengüeta oscura que sobresale del frente. Es
     *  chica y cambia mucho, porque le da alero a la cara y deja de ser un plano. */
    function visera(x0, z0, anchoCab, yTecho, saliente) {
        prisma(x0 - saliente, yTecho - 0.075, z0 + 0.02, saliente + 0.05, anchoCab - 0.04, 0.055, [58, 60, 66], G);
    }

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

        // La sombra es la única pieza que se pinta al margen de la cola, así que es
        // la única que tiene que declararse sola al medir el encuadre. Antes no
        // entraba en la cuenta y quedaba CORTADA contra el borde de abajo.
        //
        // Declara el 90% de su radio. El degradado se va a transparente, así que el
        // anillo de afuera casi no se ve y reservarlo entero descentraba el camión hacia
        // arriba. Con 0,75 se pasaba para el otro lado y el borde de la sombra tocaba el
        // filo de abajo; 0,90 centra mejor sin que se note ningún recorte.
        if (midiendo) {
            registrar(cx - rx * 0.90, cy - ry * 0.90);
            registrar(cx + rx * 0.90, cy + ry * 0.90);

            return;
        }

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
            // Entrada de solo TEXTO (el código de un bulto): viaja en la misma cola
            // que las caras justamente para que una caja de adelante lo tape, igual
            // que taparía a la caja de atrás. Fuera de la cola habría que resolver la
            // oclusión a mano.
            if (!o.pts) { pintarCodigo(o); return; }

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
            guardabarroClaro(M.ejeDel, -M.chas, z, M.r, M.rw);
        }
        for (const z of [z0 - 0.05, z0 + w - 0.20]) {
            prisma(x0 + largo * 0.42, -M.chas * 0.4, z, largo * 0.34, 0.25, 0.06, [96, 99, 106], G);
        }

        // Tanque de combustible y pasarela detrás de la cabina.
        prisma(x0 + largo + 0.10, -M.chas + 0.02, z0 - 0.06, 0.70, 0.34, 0.44, [176, 180, 188], G);

        // El costado. Sin visera: el tracto ya tiene el deflector del techo rompiendo el
        // plano, y sumarle una lengüeta encima sería inventar algo que las fotos no
        // tienen. La cabina arranca en 0 (no en el chasis) así que el alto útil es `alto`.
        costadoDeCabina(x0, 0, z0, largo, w, alto, { puerta: 0.26, vidrioDe: 0.32, arriba: 0.84 });
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

        // El costado y la visera. La puerta arranca más adelante que en el HINO porque
        // la cabina es corta: con la junta al 30% quedaba pegada al parabrisas.
        costadoDeCabina(x0, y0, z0, largo, anchoCab, h, { puerta: 0.36, vidrioDe: 0.44, arriba: 0.84 });
        visera(x0, z0, anchoCab, alto, 0.05);
    }

    /**
     * Cabina del HINO 500 FC 1118, moldeada sobre las fotos del dueño (05-08).
     *
     * Lo que la distingue en las fotos, en orden de cuánto se nota:
     * · ESPEJOS enormes sobre brazos largos, montados alto y bien salidos — pasan el
     *   ancho del furgón y son lo primero que se reconoce del frente;
     * · el ROMPEVIENTO del techo: una cuña blanca sobre la cabina, separada del techo
     *   por un bastidor, que sube hasta casi el techo del furgón (ver abajo);
     * · el furgón le gana en alto (el techo de la cabina queda a ~2/3 de la caja) y un
     *   poco en ancho;
     * · parrilla con marco plateado y el logo al centro, con listones negros;
     * · paragolpes claro con la placa al medio, faldón negro abajo y antiniebla;
     * · faros angulares grandes en las esquinas; techo plano con una ceja al frente.
     *
     * NUNCA la patente. En las fotos del PF BS-22 está pintada en las dos puertas y en
     * el paragolpes, y el repositorio es PÚBLICO (D-012): la placa se dibuja como un
     * rectángulo claro y vacío, igual que hasta ahora.
     */
    function cabinaHino() {
        const largo = M.largoCab, alto = M.altoCab;
        const anchoCab = Math.max(1.9, veh.ancho - 0.16);   // apenas menos que la caja
        const z0 = (veh.ancho - anchoCab) / 2;
        const x0 = -largo, y0 = -M.chas, h = alto - y0;

        // Cuerpo con el frente casi vertical + techo plano con ceja al frente.
        cuerpo(cuna(x0, y0, z0, largo, anchoCab, h, largo * 0.05, 0), CABINA, G);
        prisma(x0 - 0.02, alto, z0 + 0.02, largo * 0.5, anchoCab - 0.04, 0.06, CABINA, G);

        // Parabrisas grande, dejando los parantes.
        cuerpo(cuna(x0 - 0.02, y0 + h * 0.55, z0 + 0.10, largo * 0.12, anchoCab - 0.20,
            h * 0.36, largo * 0.035, 0), VIDRIO, { grad: true, borde: 'rgba(0,0,0,.35)' });

        // Los dos limpiaparabrisas, apoyados en la base del parabrisas. Se ven grandes y
        // cruzados en la foto de frente, y sin ellos el vidrio quedaba como un espejo
        // liso pegado a la chapa.
        for (const z of [z0 + anchoCab * 0.14, z0 + anchoCab * 0.50]) {
            prisma(x0 - 0.048, y0 + h * 0.553, z, 0.02, anchoCab * 0.32, 0.026, [30, 32, 36]);
        }

        // Marco plateado de la parrilla + el óvalo del logo al centro.
        prisma(x0 - 0.035, y0 + h * 0.30, z0 + 0.08, 0.04, anchoCab - 0.16, h * 0.22, [196, 200, 208], G);
        prisma(x0 - 0.055, y0 + h * 0.34, z0 + anchoCab * 0.40, 0.04, anchoCab * 0.20, h * 0.13, [168, 172, 180], G);
        // Listones negros de la parrilla.
        for (let i = 0; i < 2; i++) {
            prisma(x0 - 0.05, y0 + h * (0.20 + i * 0.07), z0 + 0.16, 0.04, anchoCab - 0.32, h * 0.045, [36, 38, 42]);
        }

        // Paragolpes: parte clara con la placa, faldón negro y antiniebla.
        prisma(x0 - 0.09, y0 + h * 0.05, z0 + 0.01, 0.11, anchoCab - 0.02, h * 0.11, CABINA, G);
        prisma(x0 - 0.10, y0 + h * 0.07, z0 + anchoCab * 0.36, 0.03, anchoCab * 0.28, h * 0.06, [238, 239, 232]);
        prisma(x0 - 0.10, y0 - 0.03, z0 + 0.01, 0.12, anchoCab - 0.02, h * 0.07, [46, 48, 53]);
        for (const z of [z0 + 0.10, z0 + anchoCab - 0.24]) {
            prisma(x0 - 0.11, y0 - 0.01, z, 0.04, 0.14, h * 0.045, [236, 237, 228]);
        }
        // Intermitentes ÁMBAR en las puntas del paragolpes (en la foto de frente están
        // encendidos, y son el único color de una cara toda blanca y gris).
        for (const z of [z0 + 0.015, z0 + anchoCab - 0.135]) {
            prisma(x0 - 0.105, y0 + h * 0.055, z, 0.035, 0.12, h * 0.05, [228, 150, 38]);
        }

        // Faros angulares en las esquinas.
        for (const z of [z0 + 0.02, z0 + anchoCab - 0.28]) {
            prisma(x0 - 0.07, y0 + h * 0.17, z, 0.05, 0.26, h * 0.12, [242, 243, 234], { borde: 'rgba(0,0,0,.3)' });
        }

        /*
         * ESPEJOS. Es lo que más identifica al 500 de frente, y con espejos chicos la
         * cabina se veía de cualquier camión.
         *
         * El soporte es de DOS TUBOS (fotos del PF BS-22, 11-08): uno arriba, casi al
         * ras del techo, y otro abajo que sale del parante de la puerta. Con un solo
         * brazo la paleta parecía flotar al costado de la cabina, sobre todo en la vista
         * de costado, donde el brazo de arriba se ve de canto. Y cuelga un CONVEXO chico
         * debajo de la paleta grande: son dos espejos por lado, no uno.
         */
        for (const z of [z0 - 0.30, z0 + anchoCab + 0.06]) {
            const zChapa = z > z0 ? z0 + anchoCab - 0.02 : z0 - 0.03;   // de dónde sale el tubo
            // Brazo de arriba (el que ya estaba) + paleta grande.
            prisma(x0 + largo * 0.06, alto - h * 0.22, z > z0 ? z - 0.24 : z + 0.06, largo * 0.10, 0.26, 0.05, [42, 44, 50]);
            prisma(x0 + largo * 0.10, alto - h * 0.42, z, 0.05, 0.20, 0.40, [46, 48, 54], G);
            // Tubo de abajo: cruza desde la chapa hasta el espejo, a la altura del
            // antebrazo del chofer. `Math.min` + la distancia real porque el lado
            // izquierdo tiene z menor que la chapa y el derecho mayor.
            prisma(x0 + largo * 0.15, alto - h * 0.52, Math.min(z, zChapa), 0.042,
                Math.abs(z - zChapa) + 0.05, 0.042, [42, 44, 50]);
            // Convexo chico, colgando del borde de abajo de la paleta.
            prisma(x0 + largo * 0.11, alto - h * 0.58, z + 0.02, 0.045, 0.14, 0.13, [40, 42, 48], G);
        }

        // Repetidor ámbar en el costado, adelante de la junta de la puerta: en la vista
        // de costado es el único punto de color de toda la chapa.
        for (const z of [z0 - 0.02, z0 + anchoCab]) {
            prisma(x0 + largo * 0.22, y0 + h * 0.30, z, 0.10, 0.02, h * 0.05, [228, 150, 38]);
        }

        // Estribo bajo la puerta, de DOS peldaños: el de abajo colgando del chasis y el
        // de arriba metido en la chapa. En las fotos se sube en dos pasos, y con una sola
        // tabla la puerta quedaba a la altura de la nada.
        for (const z of [z0 - 0.03, z0 + anchoCab - 0.20]) {
            prisma(x0 + largo * 0.44, y0 - 0.05, z, largo * 0.42, 0.23, 0.06, [88, 91, 98], G);
        }
        for (const z of [z0 - 0.02, z0 + anchoCab - 0.16]) {
            prisma(x0 + largo * 0.50, y0 + 0.15, z, largo * 0.30, 0.18, 0.05, [74, 77, 84], G);
        }

        // El costado (vidrio de la puerta, junta, manija, zócalo) y la visera.
        costadoDeCabina(x0, y0, z0, largo, anchoCab, h);
        visera(x0, z0, anchoCab, alto, 0.07);

        /*
         * EL ROMPEVIENTO DEL TECHO (pedido del dueño 11-08 con tres fotos del PF BS-22:
         * «creale ese techo arriba de la cabina, me imagino que es como un rompeviento»).
         *
         * Es lo que faltaba para que el HINO se pareciera al de la flota: sin él, entre
         * el techo de la cabina y el frente del furgón —que le gana casi 90 cm— quedaba
         * un escalón vacío, y ese hueco es justo lo que el deflector tapa en el camión
         * real.
         *
         * Tres cosas lo hacen leer como deflector y no como un cajón:
         *  1. es una RAMPA: baja adelante, alta atrás. Un prisma parejo se ve como un
         *     dormitorio de tracto, que este camión no tiene;
         *  2. NO llega al techo del furgón. Se mide contra `veh.alto` en vez de llevar un
         *     alto fijo, así que en una caja más baja se achica en vez de asomar por
         *     encima — que sería dibujar un camión que no existe;
         *  3. va SEPARADO del techo, con el bastidor a la vista en el hueco. En las fotos
         *     se ve el aire por debajo, y es lo que delata que es una pieza agregada.
         */
        const hueco = Math.min(0.12, Math.max(0.06, h * 0.07));
        const yDef = alto + hueco;
        const altoDef = Math.max(0.14, Math.min(0.62, veh.alto - 0.06 - yDef));
        const largoDef = largo * 0.86, anchoDef = anchoCab - 0.06, zDef = z0 + 0.03;

        cuerpo(rampa(x0 - 0.06, yDef, zDef, largoDef, anchoDef, altoDef * 0.34, altoDef), CABINA, G);

        // El bastidor: dos travesaños en el hueco y dos parantes atrás, donde la pieza
        // está más alta y necesita de dónde agarrarse.
        for (const x of [x0 + 0.02, x0 + largoDef * 0.66]) {
            prisma(x, alto + hueco * 0.30, zDef + 0.03, 0.05, anchoDef - 0.06, 0.035, [72, 75, 82]);
        }
        for (const z of [zDef + 0.05, zDef + anchoDef - 0.11]) {
            prisma(x0 + largoDef * 0.74, alto, z, 0.05, 0.06, hueco + altoDef * 0.45, [72, 75, 82]);
        }

        // Luces de gálibo del techo: van en el HINO «full» de las fotos y son lo que
        // remata la cabina por arriba.
        for (const z of [z0 + anchoCab * 0.18, z0 + anchoCab * 0.44, z0 + anchoCab * 0.70]) {
            prisma(x0 + 0.03, alto + 0.06, z, 0.10, anchoCab * 0.10, 0.035, [232, 168, 62]);
        }
    }

    /**
     * Cabina del Chevrolet NQR (Isuzu N-Series), moldeada sobre las fotos del dueño
     * (11-08-2026). Es un CAB-OVER puro y eso es lo que hay que capturar: no tiene morro
     * ni cuña, la cara es un plano vertical.
     *
     * Lo que la distingue de las otras tres, en orden de cuánto se nota en las fotos:
     *
     * 1. **Parabrisas de una pieza, enorme**: se come casi la mitad de la cara y baja
     *    hasta muy cerca del paragolpes. En el HINO y el HD35 el vidrio es una franja
     *    con panel y parrilla debajo; acá el panel es finito.
     * 2. **La cara es LISA**: nada de marco plateado ni parrilla de listones. Solo el
     *    moño dorado al centro y una ranura negra angosta debajo.
     * 3. **Dos espejos por lado**: la paleta rectangular grande sobre brazo tubular Y un
     *    CONVEXO REDONDO adelantado, a la altura del capó. Ese redondo asomando por
     *    delante de la cara es la firma del camión.
     * 4. **Techo liso, sin visera** — a diferencia del HINO y del HD35, que sí la tienen.
     * 5. Faros grandes y claros integrados abajo, en las esquinas del paragolpes.
     */
    function cabinaNqr() {
        const largo = M.largoCab, alto = M.altoCab;
        const anchoCab = Math.max(1.86, veh.ancho - 0.14);
        const z0 = (veh.ancho - anchoCab) / 2;
        const x0 = -largo, y0 = -M.chas, h = alto - y0;

        // Cuerpo: frente PLANO. El recorte de 2% es casi nada, apenas para que la arista
        // de arriba no quede viva — un cab-over no tiene cuña.
        cuerpo(cuna(x0, y0, z0, largo, anchoCab, h, largo * 0.02, 0), CABINA, G);
        prisma(x0 + 0.01, alto, z0 + 0.02, largo * 0.96, anchoCab - 0.04, 0.05, CABINA, G);

        // Parabrisas de una pieza: 44% de la cara y arranca al 50%. Es el rasgo #1.
        cuerpo(cuna(x0 - 0.02, y0 + h * 0.50, z0 + 0.07, largo * 0.06, anchoCab - 0.14,
            h * 0.44, largo * 0.02, 0), VIDRIO, { grad: true, borde: 'rgba(0,0,0,.35)' });

        // Los dos limpiaparabrisas, apoyados abajo del vidrio.
        for (const z of [z0 + anchoCab * 0.16, z0 + anchoCab * 0.52]) {
            prisma(x0 - 0.045, y0 + h * 0.505, z, 0.02, anchoCab * 0.30, 0.028, [30, 32, 36]);
        }

        // Moño dorado al centro del panel liso. Sin parrilla de listones: solo la ranura.
        prisma(x0 - 0.032, y0 + h * 0.36, z0 + anchoCab * 0.43, 0.03, anchoCab * 0.14, h * 0.05, [204, 166, 58], G);
        prisma(x0 - 0.035, y0 + h * 0.26, z0 + 0.18, 0.04, anchoCab - 0.36, h * 0.04, [36, 38, 42]);

        // Paragolpes claro alto, con dos tomas negras y el faldón oscuro abajo.
        prisma(x0 - 0.09, y0 + h * 0.04, z0 + 0.01, 0.11, anchoCab - 0.02, h * 0.17, CABINA, G);
        for (const z of [z0 + anchoCab * 0.28, z0 + anchoCab * 0.54]) {
            prisma(x0 - 0.10, y0 + h * 0.07, z, 0.03, anchoCab * 0.16, h * 0.06, [44, 46, 51]);
        }
        prisma(x0 - 0.10, y0 - 0.03, z0 + 0.01, 0.12, anchoCab - 0.02, h * 0.07, [46, 48, 53]);

        // Faros grandes y claros en las esquinas BAJAS (no a media altura como el HD35).
        for (const z of [z0 + 0.02, z0 + anchoCab - 0.30]) {
            prisma(x0 - 0.085, y0 + h * 0.09, z, 0.05, 0.28, h * 0.13, [242, 243, 236], { borde: 'rgba(0,0,0,.3)' });
        }

        // ESPEJOS, rasgo #3: paleta grande sobre brazo + el convexo REDONDO adelantado.
        for (const z of [z0 - 0.24, z0 + anchoCab + 0.04]) {
            prisma(x0 + largo * 0.05, alto - h * 0.18, z > z0 ? z - 0.20 : z + 0.04, largo * 0.08, 0.22, 0.045, [38, 40, 46]);
            prisma(x0 + largo * 0.08, alto - h * 0.46, z, 0.05, 0.20, 0.42, [44, 46, 52], G);
            prisma(x0 - 0.07, alto - h * 0.64, z, 0.05, 0.13, 0.13, [40, 42, 48], G);
        }

        for (const z of [z0 - 0.03, z0 + anchoCab - 0.20]) {
            prisma(x0 + largo * 0.42, y0 - 0.05, z, largo * 0.44, 0.23, 0.06, [88, 91, 98], G);
        }

        // Costado. La puerta arranca temprano porque el cab-over pone al chofer adelante
        // del eje. SIN visera (rasgo #4): el techo de este camión es liso.
        costadoDeCabina(x0, y0, z0, largo, anchoCab, h, { puerta: 0.28, vidrioDe: 0.38, arriba: 0.88 });
    }

    /** Cabina del camión de reparto GENÉRICO. Queda de respaldo para un camión sin
     *  silueta declarada —o sin fotos todavía—, para que el lienzo nunca se quede sin
     *  dibujo. Hoy la usa el Chevy 3, a la espera de las suyas. */
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
        // Luces de gálibo ámbar en las esquinas de adelante del furgón: están en las
        // fotos del HD35 y del HINO y son baratas. El contenedor no las lleva.
        if (!M.semi) {
            for (const z of [0.01, W - 0.09]) prisma(-0.02, H - 0.13, z, 0.05, 0.08, 0.07, [226, 148, 38]);
        }
        paredes(L, W, H);
        // SIN puerta lateral. La pidió el dueño el 06-08 y la mandó sacar el 07-08
        // («sacame la puerta de la caja que no queda bien»). El motivo se ve en el
        // lienzo: dibujada translúcida sobre una pared que ya deja ver la carga, no
        // se leía como una puerta sino como una mancha sobre los bultos. El detalle
        // del costado lo siguen dando los nervios de `paredes()`.
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

        // Nervios / juntas de panel: los tramos van con el tono ALTERNADO. Un contenedor
        // real es corrugado y un furgón está hecho de paneles con junta, y de costado —una
        // de las vistas fijas— la pared era una sábana lisa de punta a punta. Sale gratis
        // porque los tramos ya existían por el orden de dibujo. En el contenedor la
        // alternancia es más marcada: la corrugación se ve mucho más que una junta.
        const nervio = M.semi ? 0.10 : 0.045;

        for (let i = 0; i < N; i++) {
            const xa = (i * L) / N, xb = ((i + 1) * L) / N;
            const t = i % 2 ? 1 - nervio : 1;
            panel([[xa, 0, 0], [xb, 0, 0], [xb, H, 0], [xa, H, 0]], VID, { alpha: 0.10, tono: 0.86 * t });
            panel([[xa, 0, W], [xb, 0, W], [xb, H, W], [xa, H, W]], VID, { alpha: 0.10, tono: 0.95 * t });
            panel([[xa, H, 0], [xb, H, 0], [xb, H, W], [xa, H, W]], VID, { alpha: 0.09 });
        }
        panel([[0, 0, 0], [0, H, 0], [0, H, W], [0, 0, W]], VID, { alpha: 0.11, tono: 0.8 });
        panel([[L, 0, 0], [L, H, 0], [L, H, W], [L, 0, W]], VID, { alpha: 0.09, tono: 0.9 });
    }

    /** Camión de reparto (HINO) y su variante liviana (HD35). */
    function siluetaCamion() {
        sombraSuave(-M.delante - 0.1, veh.largo);
        // Placa entera solo BAJO LA CABINA (ahí no hay piso que tapar); bajo la
        // caja van largueros angostos.
        tira(-M.delante, -M.chas, 0.02, M.delante, veh.ancho - 0.04, M.chas - 0.05, GRIS, G);
        largueros(0, veh.largo);
        if (M.liviano) cabinaLiviana(); else if (M.hino) cabinaHino(); else if (M.nqr) cabinaNqr(); else cabina();
        cajaDeCarga();

        // Rueda delantera SIMPLE y trasera DOBLE en los dos: el HD35 también lleva
        // ruedas gemelas atrás (se ve en la foto de la trasera del chasis), y antes se
        // le dibujaba una sola por eje.
        const anchoTras = M.rw * 2 + 0.03;
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) {
            rueda(M.ejeDel, -M.chas, z, M.r, M.rw);
            // Arco de la rueda delantera. Faltaba en los dos camiones de reparto y era lo
            // que más delataba la cabina como un cajón: la rueda salía de un costado
            // liso, sin nada que explicara de dónde. Atrás ya estaba.
            guardabarro(M.ejeDel, -M.chas, z, M.r, M.rw);
        }
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
        for (const z of [-0.03, veh.ancho - M.rw + 0.03]) rueda(M.ejeDel, -M.chas, z, M.r, M.rw);
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
            const rej = blq.rejilla, ori = blq.orientacion;
            const dibujables = Math.max(0, Math.min(blq.cantidad, rej.largo * rej.ancho * rej.alto, cant - puestos));

            if (blq.forma === 'pallet') {
                rejillaDePallets(blq, dibujables);
            } else {
                // `blq.apoyo` es la altura a la que APOYA el bloque: 0 en el piso y el techo
                // del bloque de abajo cuando va en segundo piso (bolsas livianas arriba del
                // muro, 11-08). Antes iba un 0 fijo, así que el motor podía contar carga
                // apoyada arriba y el lienzo la dibujaba atravesando lo que tenía debajo.
                rejillaDeBultos(blq.x, blq.apoyo || 0, blq.y, rej, ori, dibujables, blq);
            }

            dibujadosPorBloque[i] = dibujables;
            puestos += dibujables;
        }
    }

    /** Los pallets ARMADOS dentro del camión: cada uno es su base de madera más su carga
     *  encima, dibujada con la misma rejilla que la carga suelta. */
    function rejillaDePallets(blq, n) {
        const rej = blq.rejilla, ori = blq.orientacion, it = blq.interior;
        const capa = rej.ancho * rej.alto;
        // Con muchos pallets la madera detallada son 17 prismas cada uno: a partir de una
        // docena se cae a la tarima simple, que a ese tamaño en pantalla se ve igual.
        const detalle = n <= 12;

        for (let k = 0; k < n; k++) {
            const ix = Math.floor(k / capa), resto = k % capa;
            const iz = Math.floor(resto / rej.alto), iy = resto % rej.alto;
            const px = blq.x + ix * ori.largo, py = iy * ori.alto, pz = blq.y + iz * ori.ancho;

            palletDeMadera(px, py, pz, ori.largo * 0.99, ori.ancho * 0.99, blq.base, detalle);
            if (!it) continue;

            // La carga va CENTRADA sobre la tarima: la rejilla casi nunca llena el pallet
            // justo, y arrimada a una esquina parecía mal estibada.
            const usaL = it.rejilla.largo * it.orientacion.largo;
            const usaW = it.rejilla.ancho * it.orientacion.ancho;
            rejillaDeBultos(
                px + (ori.largo - usaL) / 2, py + blq.base, pz + (ori.ancho - usaW) / 2,
                it.rejilla, it.orientacion, it.cantidad, it,
            );
        }
    }

    /**
     * Tarima de madera. `detalle` dibuja las tablas y los tacos; sin él, un cajón liso.
     *
     * Las proporciones son las de un pallet real: tablas de arriba cruzadas al largo,
     * nueve tacos y tres patines abajo. Es lo que lo hace reconocible como pallet y no
     * como una caja marrón.
     */
    function palletDeMadera(x, y, z, l, w, base, detalle = true) {
        const MADERA = [186, 146, 98], TACO = [150, 114, 74];

        if (!detalle) {
            prisma(x, y, z, l, w, base, MADERA, G);

            return;
        }

        const tabla = base * 0.16;
        // Tablas de arriba: cruzan a lo ancho, con luz entre ellas.
        for (let i = 0; i < 5; i++) {
            prisma(x + (l - l * 0.13) * (i / 4), y + base - tabla, z, l * 0.13, w, tabla, MADERA, G);
        }
        // Tacos.
        for (const dx of [0, (l - w * 0.13) / 2, l - w * 0.13]) {
            for (const dz of [0, (w - w * 0.13) / 2, w - w * 0.13]) {
                prisma(x + dx, y + tabla, z + dz, w * 0.13, w * 0.13, base - tabla * 2, TACO, G);
            }
        }
        // Patines de abajo, a lo largo.
        for (const dz of [0, (w - w * 0.13) / 2, w - w * 0.13]) {
            prisma(x, y, z + dz, l, w * 0.13, tabla, MADERA, G);
        }
    }

    /**
     * El pallet EN EL PISO, al lado del camión, mientras se arma (pedido del dueño
     * 06-08-2026: «que el pallet aparezca al lado del camión en el piso con la opción de
     * armarlo y luego subirlo al camión»).
     *
     * Va del lado CERCANO a la cámara (z negativo) y apoyado en el mismo piso que las
     * ruedas, para que se lea que está en el suelo y no flotando al lado de la caja.
     */
    function palletAlLado() {
        const p = datos.pallet;
        if (!p) return;

        const x = veh.largo * 0.16;
        const z = -(p.ancho + 0.95);
        const y = M.suelo;

        palletDeMadera(x, y, z, p.largo, p.ancho, p.base, true);

        const it = p.interior;
        if (it && it.cantidad > 0) {
            const usaL = it.rejilla.largo * it.orientacion.largo;
            const usaW = it.rejilla.ancho * it.orientacion.ancho;
            rejillaDeBultos(
                x + (p.largo - usaL) / 2, y + p.base, z + (p.ancho - usaW) / 2,
                it.rejilla, it.orientacion, it.cantidad, it,
            );
        }
    }

    /**
     * Dibuja los `n` primeros bultos de una rejilla apoyada en (x0, y0, z0).
     *
     * Sale de `bultos()` para que el PALLET pueda armar su carga con exactamente el mismo
     * dibujo: un pallet armado es una rejilla de bultos sobre una base de madera, así que
     * duplicar este bucle habría dejado dos versiones que driftean (el descarte de
     * interiores, el LOD de los bidones y los códigos se habrían quedado en una sola).
     */
    function rejillaDeBultos(x0, y0, z0, rej, ori, n, blq) {
        if (n <= 0) return;

        const col = blq.color || [234, 88, 12];
        const capa = rej.ancho * rej.alto;

        // Los bidones cuestan ~6 veces más polígonos que un bulto rectangular, y se
        // dibujan uno por bolsa. Con cientos de bolsas (el contenedor lleva 324) se cae al
        // bulto rectangular: a ese tamaño en pantalla se ve prácticamente igual y el
        // arrastre se mantiene fluido. El límite se fijó MIDIENDO los polígonos por frame.
        const bidones = blq.forma === 'botellones' && n <= TOPE_BIDONES;

        const indice = (ix, iz, iy) => ix * capa + iz * rej.alto + iy;
        const puesto = (ix, iz, iy) => ix >= 0 && ix < rej.largo && iz >= 0 && iz < rej.ancho
            && iy >= 0 && iy < rej.alto && indice(ix, iz, iy) < n;

        for (let k = 0; k < n; k++) {
            const ix = Math.floor(k / capa), resto = k % capa;
            const iz = Math.floor(resto / rej.alto), iy = resto % rej.alto;

            if (puesto(ix - 1, iz, iy) && puesto(ix + 1, iz, iy)
                && puesto(ix, iz - 1, iy) && puesto(ix, iz + 1, iy)
                && puesto(ix, iz, iy - 1) && puesto(ix, iz, iy + 1)) continue;

            const px = x0 + ix * ori.largo, py = y0 + iy * ori.alto, pz = z0 + iz * ori.ancho;
            const [ba, bb, bc] = [ori.largo * SEPARACION, ori.ancho * SEPARACION, ori.alto * SEPARACION];
            if (bidones) {
                bolsaDeBidones(px, py, pz, ba, bb, bc, col, blq.estiba);
            } else {
                // CADA CAJA CON SU LÍNEA (pedido del dueño 11-08: «las cajas bien marcadas
                // con líneas negras, que se entienda la separación; en la imagen se ven los
                // espacios faltantes»).
                //
                // Antes iban con el borde por defecto de `cuerpo()` —negro al 22%— y a
                // pocos metros de distancia una pared de 40 cajas del mismo color se leía
                // como UN bloque naranja. Y lo que se pierde con eso no es estética: es
                // justamente el hueco. Sin ver dónde termina una caja no se ve que falta
                // una, que es para lo que se mira el dibujo.
                //
                // Va como borde y no como separación mayor entre cajas porque el dibujo
                // tiene que seguir siendo fiel al cálculo: las cajas están pegadas de
                // verdad, y agrandar el hueco dibujaría un acomodo que no es el que el
                // motor calculó.
                // La línea solo cuando la caja es lo bastante grande en PANTALLA (ver
                // `BORDE_MIN`). Se mide el LADO MÁS LARGO proyectado y no la diagonal del
                // cuerpo: la diagonal suma la profundidad, así que sobreestima el tamaño
                // aparente y dejaba pasar cajas que en pantalla eran de 13 px.
                const o = proyectar([px, py, pz]);
                const largoEnPantalla = (p) => Math.hypot(p[0] - o[0], p[1] - o[1]);
                const lado = Math.max(
                    largoEnPantalla(proyectar([px + ba, py, pz])),
                    largoEnPantalla(proyectar([px, py, pz + bb])),
                    largoEnPantalla(proyectar([px, py + bc, pz])),
                );

                prisma(px, py, pz, ba, bb, bc, col, {
                    borde: lado >= BORDE_MIN ? BORDE_BULTO : null,
                });
            }
            // El código va sobre la caja de siempre, sea prisma o bolsa de bidones: las
            // dos ocupan el mismo volumen, así que la cara se calcula igual.
            if (codigos && VARIOS && blq.letra) codigoEnBulto(px, py, pz, ba, bb, bc, blq.letra, bidones);
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
            // Ancla: el centro del techo del bloque, contando desde donde APOYA (un bloque
            // en segundo piso tiene su techo más arriba, y el rótulo tiene que seguirlo o
            // queda clavado dentro de la carga de abajo).
            const ancla = proyectar([
                blq.x + (rej.largo * ori.largo) / 2,
                (blq.apoyo || 0) + rej.alto * ori.alto,
                blq.y + (rej.ancho * ori.ancho) / 2,
            ]);
            // A medio cargar (los pasos, o la animación) dice CUÁNTOS VAN de cuántos:
            // poner solo el total mientras se ven 18 de 84 hace dudar de qué número
            // creer. Los números son del PRODUCTO, sumando todas sus zonas.
            puestas.push({
                ancla,
                texto: nombreCorto(nombre),
                cuenta: g.puestos < g.total ? `${g.puestos} de ${g.total}` : String(g.total),
                col: blq.color || [234, 88, 12],
                letra: VARIOS ? blq.letra : null,
            });
        }

        // De adelante hacia atrás: la de adelante manda y las de atrás ceden.
        puestas.sort((a, b) => a.ancla[2] - b.ancla[2]);
        const ocupadas = [];
        ctx.font = '600 15px -apple-system,system-ui,sans-serif';
        ctx.textAlign = 'left';

        for (const e of puestas) {
            const texto = `${e.texto} · ${e.cuenta}`;
            const ancho = ctx.measureText(texto).width + 38;
            const alto = 26;
            const x = Math.min(Math.max(8, e.ancla[0] - ancho / 2), AW - ancho - 8);

            // Busca un hueco libre: primero SUBIENDO desde el ancla y, si arriba no
            // queda lugar, BAJANDO.
            //
            // Antes se subía sin tope y después se recortaba con `Math.max(6, y)`, que
            // deshacía la separación recién calculada: con tres productos apilados en
            // el mismo frente, sus anclas caen casi en la misma x, las tres etiquetas
            // terminaban en y=6 y se tapaban entre ellas (se leía «…tes · 40» detrás de
            // la de las bolsas). El recorte tiene que estar DENTRO de la búsqueda.
            const paso = alto + 6;
            const choca = (yy) => ocupadas.some((o) => Math.abs(o.y - yy) < paso && Math.abs(o.x - x) < (o.ancho + ancho) / 2);
            const base = Math.min(Math.max(6, e.ancla[1] - 46), AH - alto - 6);

            let y = base;
            if (choca(y)) {
                y = null;
                for (let c = base - paso; c >= 6 && y === null; c -= paso) if (!choca(c)) y = c;
                for (let c = base + paso; c <= AH - alto - 6 && y === null; c += paso) if (!choca(c)) y = c;
                if (y === null) y = base;   // lienzo lleno de etiquetas: no queda mejor lugar
            }

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

            // Chapita del color del bloque con la LETRA del producto adentro: es la
            // misma leyenda que la lista de abajo y la misma letra que llevan escritas
            // las cajas, así que el cartel, el renglón y el bulto se leen como uno.
            ctx.beginPath();
            ctx.roundRect(x + 7, y + alto / 2 - 8, 16, 16, 4);
            ctx.fillStyle = rgb(e.col);
            ctx.fill();

            if (e.letra) {
                ctx.font = '700 12px -apple-system,system-ui,sans-serif';
                ctx.textAlign = 'center';
                ctx.fillStyle = '#fff';
                ctx.fillText(e.letra, x + 15, y + alto / 2 + 4);
                ctx.textAlign = 'left';
                ctx.font = '600 15px -apple-system,system-ui,sans-serif';
            }

            ctx.fillStyle = '#3f3f46';
            ctx.fillText(texto, x + 29, y + alto / 2 + 5);
        }
    }

    // ------------------------------------------------------------------- escena

    /**
     * Mide escala y centro para que el vehículo LLENE el lienzo VISTO DESDE
     * `yawV`/`pitchV`.
     *
     * Se miden los extremos REALES del cuerpo ya proyectado en vez de dividir el
     * largo por un número a ojo. La versión anterior reservaba ancho para una
     * rotación que no estaba usando y el camión quedaba chico en el medio, con el
     * primer quinto del lienzo siempre vacío.
     *
     * Recibe los ángulos en vez de leer los de la vista porque cada vista los
     * necesita distintos: de costado el camión es larguísimo y bajo, de planta es
     * largo y angosto, y desde la puerta es casi un cuadrado. Con una sola escala
     * medida en 3/4, la vista de costado se salía del lienzo y la de la puerta
     * quedaba diminuta en el medio.
     */
    function medirEncuadre(yawV, pitchV) {
        const yaw0 = yaw, pitch0 = pitch, cola0 = cola;
        yaw = yawV; pitch = pitchV;
        OFF = [(veh.largo - M.delante) / 2, (M.techo + M.suelo) / 2, veh.ancho / 2];
        CX = 0; CY = 0; ESC = 1;

        // Se dibuja la silueta a una cola DESCARTABLE y se miden sus vértices ya
        // proyectados. `midiendo` evita que la sombra —lo único que se pinta fuera de
        // la cola— manche el lienzo durante la medición.
        //
        // Se mide la silueta y NO los bultos: si entraran, el encuadre cambiaría
        // según cuánto haya cargado y el camión daría un salto de tamaño en cada
        // «+10». La caja de carga es parte de la silueta, así que la carga, que nunca
        // sale de la caja, ya está contenida.
        cola = [];
        midiendo = { x0: Infinity, x1: -Infinity, y0: Infinity, y1: -Infinity };
        if (M.semi) siluetaSemirremolque(); else siluetaCamion();
        // El pallet del piso entra al encuadre: si no, queda fuera del lienzo justo cuando
        // se lo está armando, que es cuando hay que verlo.
        if (PALLET && !subido) palletAlLado();
        for (const o of cola) if (o.pts) for (const p of o.pts) registrar(p[0], p[1]);

        const medida = midiendo;
        midiendo = null;
        cola = cola0;
        yaw = yaw0; pitch = pitch0;

        return medida;
    }

    /**
     * Da al recuadro la FORMA del camión dibujado, y ajusta el mapa de bits.
     *
     * Es la otra mitad de «el camión se ve chico» (dueño, 05-08-2026: «necesito que
     * sea más grande el espacio cuadrado, se sigue viendo apretado o pequeño»). El
     * recuadro era apaisado 2,21:1 y el camión en 3/4 proyecta una silueta de
     * ~1,45:1, así que el alto se llenaba al 95% y del ancho sobraba una cuarta
     * parte: el camión entraba por lo alto y quedaba chico. Midiendo la silueta se
     * sabe su proporción, y el recuadro se corta a esa medida.
     *
     * Se fija UNA vez con los ángulos de apertura y no cambia al cambiar de vista:
     * si cada vista redimensionara el recuadro, la página entera saltaría al tocar
     * «Planta». Las demás vistas entran adentro de ese mismo recuadro.
     *
     * El clamp evita los dos extremos: un contenedor de 12 m no puede dejar un
     * recuadro tan apaisado que la carga sea una línea, y un camión corto no puede
     * pedir un recuadro tan alto que empuje los datos abajo de la pantalla.
     */
    function proporcionar(ext) {
        const ancho = Math.max(0.01, ext.x1 - ext.x0), alto = Math.max(0.01, ext.y1 - ext.y0);
        const ratio = Math.min(2.5, Math.max(1.35, ancho / alto));
        canvas.style.aspectRatio = ratio.toFixed(3);
        ajustarLienzo();
    }

    /**
     * Deja la vista en esos extremos: escala para llenar el lienzo y centro para que
     * el dibujo quede EN EL MEDIO.
     *
     * Los márgenes son chicos porque la medida es EXACTA: mide lo que se pinta
     * (espejos, paragolpes, guardabarros y la sombra incluidos). La versión anterior
     * medía los 8 vértices de la caja de carga, que no es lo que se ve: reservaba
     * adelante un hueco que la cabina no llenaba y se le escapaba la sombra por
     * abajo, que quedaba CORTADA contra el borde (medido: 0 px de margen abajo).
     */
    function aplicar(ext) {
        escBase = Math.min(AW * 0.97 / Math.max(0.01, ext.x1 - ext.x0),
            AH * 0.95 / Math.max(0.01, ext.y1 - ext.y0));
        centro = [(ext.x0 + ext.x1) / 2, (ext.y0 + ext.y1) / 2];
        zoom = 1;
        ESC = escBase;
        // El desplazamiento del botón derecho se SUMA al encuadre en vez de estar
        // adentro: acá se vuelve a medir todo, así que un pan guardado en CX/CY se
        // perdería con solo girar un grado (ver `pan`).
        CX = AW / 2 - centro[0] * ESC + pan[0];
        CY = AH / 2 - centro[1] * ESC + pan[1];
    }

    /** Suma un punto a la medición del encuadre en curso. */
    function registrar(x, y) {
        if (!midiendo) return;
        if (x < midiendo.x0) midiendo.x0 = x;
        if (x > midiendo.x1) midiendo.x1 = x;
        if (y < midiendo.y0) midiendo.y0 = y;
        if (y > midiendo.y1) midiendo.y1 = y;
    }

    /**
     * Ajusta el mapa de bits al tamaño REAL del recuadro y a la densidad de la
     * pantalla. Devuelve si cambió.
     *
     * El lienzo tenía un tamaño fijo de 1240 px y el CSS lo escalaba: en un monitor
     * ancho eso es una imagen de 1240 px estirada a 2000, o sea blanda. Ahora se
     * dibuja a la medida del hueco que hay, que es lo que el dueño pidió al decir
     * que quería «más grande el espacio» (05-08-2026).
     *
     * El alto del recuadro lo fija el CSS con `aspect-ratio`, NO el atributo del
     * lienzo. Es lo que evita el bucle: si el alto saliera del mapa de bits, tocarlo
     * cambiaría el recuadro, que volvería a cambiar el mapa de bits.
     */
    function ajustarLienzo() {
        const r = canvas.getBoundingClientRect();
        if (r.width < 1 || r.height < 1) return false;

        const dpr = Math.min(2, window.devicePixelRatio || 1);
        // Tope de ancho: en un monitor 4K, dibujar a 3800 px cuadruplicaría el costo
        // de cada frame para una nitidez que ya nadie distingue.
        const k = Math.min(dpr, 2600 / r.width);
        const w = Math.max(320, Math.round(r.width * k)), h = Math.max(200, Math.round(r.height * k));
        if (w === canvas.width && h === canvas.height && AW === r.width) return false;

        canvas.width = w; canvas.height = h;

        // Se dibuja en píxeles LÓGICOS (los del CSS) y la escala la pone la matriz del
        // contexto. Es la única forma de subir la densidad sin encoger todo lo que se
        // mide en píxeles: si se dibujara en píxeles del mapa de bits, un lienzo al
        // doble de resolución mostraría las letras y los carteles a la mitad de
        // tamaño. Redimensionar el lienzo BORRA el estado del contexto, así que la
        // matriz se vuelve a poner acá, junto al cambio de tamaño.
        AW = r.width; AH = r.height;
        ctx.setTransform(w / AW, 0, 0, h / AH, 0, 0);

        return true;
    }

    /**
     * Encuadre inicial: le da al recuadro la forma del camión y se para en 3/4.
     *
     * La proporción se toma de la vista de apertura y queda fija para toda la
     * pantalla (ver `proporcionar`).
     */
    function encuadrar() {
        proporcionar(medirEncuadre(...VISTAS['3d']));
        vista('3d');
    }

    /**
     * Se para en una de las vistas fijas. Es lo que en EasyCargo es el panel
     * «Views», y el dueño lo señaló como lo que más le sirve de esa app
     * (05-08-2026): «la capacidad para mostrar los diferentes opciones para ver la
     * carga».
     *
     * Cada vista es un par de ángulos, no una cámara aparte, así que sigue siendo
     * el mismo dibujo de siempre y se puede seguir arrastrando desde donde quedó.
     * Los ángulos NO son a ojo, salen de la proyección (ver `proyectar`):
     * · costado → yaw 0: la coordenada z (el ancho) deja de entrar en la x de
     *   pantalla, así que se ve el perfil puro, con la puerta a la derecha.
     * · planta  → pitch muy negativo: mira desde arriba. No se usa −π/2 exacto
     *   porque a 90° las caras verticales degeneran en líneas y la carga pierde
     *   todo el volumen; a −1,35 rad se lee como planta y conserva el espesor.
     * · puerta  → yaw −π/2: la x (el largo) sale de la x de pantalla y el fondo del
     *   camión queda DETRÁS de la carga, que es mirar por la puerta abierta.
     *
     * Vuelve el zoom a 1: si no, cambiar de vista con la carga ampliada dejaba el
     * lienzo mirando el vacío al costado del camión.
     */
    const VISTAS = {
        '3d': [-0.85, -0.3],
        costado: [0, 0],
        planta: [0, -1.35],
        puerta: [-Math.PI / 2, -0.15],
    };

    function vista(clave) {
        const [y, p] = VISTAS[clave] || VISTAS['3d'];

        // Una vista fija es «ponete acá y mostrame todo»: si conservara el
        // desplazamiento del botón derecho, apretar «Planta» después de haber
        // recorrido la carga dejaría el camión a medio salir del cuadro, y el botón
        // no habría hecho lo que dice. Se limpia ANTES de encuadrar, que es quien lo
        // suma a CX/CY.
        pan = [0, 0];

        aplicar(medirEncuadre(y, p));
        yaw = y; pitch = p;
        vistaActual = clave;
        marcarVista();
    }

    /** Vuelve al encuadre y al ángulo con que abre la pantalla. */
    function reiniciarVista() {
        vista('3d');
    }

    /** Marca cuál de las vistas está puesta. Tolera que los botones no existan: el
     *  encuadre inicial corre antes de que se cablee nada. */
    function marcarVista() {
        for (const clave of Object.keys(VISTAS)) {
            const b = document.getElementById(`carga3dVista${clave}`);
            if (!b) continue;
            const puesta = clave === vistaActual;
            b.setAttribute('aria-pressed', String(puesta));
            b.classList.toggle('bg-brand-600', puesta);
            b.classList.toggle('text-white', puesta);
            b.classList.toggle('border-brand-600', puesta);
            b.classList.toggle('bg-white', !puesta);
            b.classList.toggle('text-neutral-700', !puesta);
        }
    }

    /**
     * Acerca o aleja ANCLANDO un punto de la pantalla: el punto que estaba bajo el
     * cursor sigue estando ahí después de acercar. Por eso apuntar a la carga y
     * girar la rueda acerca LA CARGA, que es lo que se pidió — un zoom al centro
     * geométrico del camión deja la carga fuera de cuadro.
     *
     * Sin `px`/`py` ancla el centro del lienzo (los botones + / −).
     */
    function acercar(factor, px = AW / 2, py = AH / 2) {
        const antes = zoom;
        zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoom * factor));
        if (zoom === antes) return;

        // Punto del mundo proyectado que está bajo (px, py), en unidades de escala 1.
        const ux = (px - CX) / ESC, uy = (py - CY) / ESC;
        ESC = escBase * zoom;
        CX = px - ux * ESC;
        CY = py - uy * ESC;

        // El ancla movió CX/CY, así que `pan` —que es la diferencia contra el encuadre
        // centrado— se recalcula desde ellos. Sin esto queda viejo, y el primer giro
        // con zoom 1 devolvería el dibujo a un lugar que el usuario no eligió.
        pan = [CX - (AW / 2 - centro[0] * ESC), CY - (AH / 2 - centro[1] * ESC)];
    }

    /**
     * Corre el dibujo por la pantalla (botón derecho).
     *
     * El tope crece con el zoom y por eso no estorba: a zoom 1 el camión llena el
     * recuadro y no se lo puede empujar hasta perderlo de vista; acercado 4 veces hay
     * cancha para recorrer la carga de punta a punta, que es para lo que se acerca.
     * Sin tope, un arrastre largo deja el lienzo en blanco y la única salida es
     * «Reiniciar» — un callejón que no tiene por qué existir.
     */
    function desplazar(dx, dy) {
        const tope = [AW * 0.6 * zoom, AH * 0.6 * zoom];
        pan = [
            Math.max(-tope[0], Math.min(tope[0], dx)),
            Math.max(-tope[1], Math.min(tope[1], dy)),
        ];
        CX = AW / 2 - centro[0] * ESC + pan[0];
        CY = AH / 2 - centro[1] * ESC + pan[1];
    }

    /** Pasa las coordenadas del puntero a píxeles del lienzo (se dibuja a 1240×720
     *  y el CSS lo escala, así que sin esto el ancla del zoom queda corrida). */
    function aLienzo(e) {
        const r = canvas.getBoundingClientRect();
        return [
            (e.clientX - r.left) * (AW / Math.max(1, r.width)),
            (e.clientY - r.top) * (AH / Math.max(1, r.height)),
        ];
    }

    function dibujar() {
        ctx.clearRect(0, 0, AW, AH);

        if (M.semi) siluetaSemirremolque(); else siluetaCamion();
        if (PALLET && !subido) palletAlLado(); else bultos();
        pintar();
        etiquetas();
        textoChapa();

        ctx.fillStyle = '#8a8a8a';
        ctx.font = '600 15px -apple-system,system-ui,sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText('PUERTA →', AW - 22, 30);
        ctx.textAlign = 'left';

        const n = document.getElementById('carga3dN');
        if (n) n.textContent = cant;

        // La caja de cantidad refleja el número REAL, venga de donde venga (los
        // pasos, Todo/Vaciar o la animación). Se actualiza acá, en el único lugar
        // por el que pasa todo dibujado, así que no puede quedar desfasada.
        //
        // Salvo mientras la están tipeando: pisarle el valor al usuario en medio de
        // un número lo vuelve inusable (escribe «1» de «150» y se lo reemplazan).
        const caja = document.getElementById('carga3dCantidad');
        if (caja && document.activeElement !== caja) caja.value = cant;

        // La barra también sigue al número real. Acá NO hace falta la guarda del
        // foco: una barra no se «escribe a medias», su valor ya ES el que el
        // usuario dejó, así que reescribirlo con el mismo número no molesta.
        const barra = document.getElementById('carga3dBarra');
        if (barra) barra.value = cant;
    }

    // ---------------------------------------------------------------- controles

    /**
     * BOTÓN IZQUIERDO GIRA, BOTÓN DERECHO DESPLAZA (pedido del dueño 12-08-2026,
     * copiando los controles de EasyCargo). La rueda ya acercaba.
     *
     * El botón del medio también desplaza: es lo que hace cualquier programa de 3D o
     * de mapas, no cuesta nada y evita que quien lo tenga por costumbre crea que el
     * visor se trabó.
     *
     * En el TELÉFONO nada de esto cambia: un dedo emite `button 0`, así que sigue
     * girando igual que siempre.
     */
    const DESPLAZA = new Set([1, 2]);

    canvas.addEventListener('pointerdown', (e) => {
        arrastre = {
            x: e.clientX, y: e.clientY, yaw, pitch,
            // Qué hace este arrastre queda decidido al APRETAR y no se recalcula
            // mientras se mueve: si se leyera `e.buttons` en cada `pointermove`,
            // soltar el derecho y apretar el izquierdo sin levantar la mano cambiaría
            // de modo a mitad de gesto.
            mueve: DESPLAZA.has(e.button),
            pan: [...pan],
        };
        canvas.setPointerCapture(e.pointerId);
        canvas.style.cursor = arrastre.mueve ? 'move' : 'grabbing';
    });
    canvas.addEventListener('pointermove', (e) => {
        if (!arrastre) return;

        if (arrastre.mueve) {
            // Píxeles del LIENZO y no del CSS: el lienzo se dibuja a su ancho lógico y
            // el navegador lo escala, así que sin convertir, el dibujo se movería más
            // (o menos) que el cursor y el arrastre se sentiría resbaladizo.
            const k = AW / Math.max(1, canvas.getBoundingClientRect().width);
            desplazar(arrastre.pan[0] + (e.clientX - arrastre.x) * k,
                arrastre.pan[1] + (e.clientY - arrastre.y) * k);
            dibujar();

            return;
        }

        // Girando con el dedo o el mouse ya no se está en ninguna vista fija, así que se
        // apaga el botón marcado. Si no, quedaba «Costado» encendido con el camión en
        // tres cuartos —el dueño lo mandó en una captura— y el botón mentía.
        if (vistaActual) { vistaActual = null; marcarVista(); }

        yaw = arrastre.yaw + (e.clientX - arrastre.x) * 0.008;
        // El tope de abajo llega hasta la vista de PLANTA (−1,35): con el −1,15 de
        // antes, entrar en planta y mover un pixel el dedo saltaba de golpe a 3/4.
        pitch = Math.max(-1.42, Math.min(0.45, arrastre.pitch + (e.clientY - arrastre.y) * 0.006));

        // REENCUADRA MIENTRAS GIRA (pedido del dueño 06-08-2026: «quiero que en el
        // cuadrado el camión esté en el centro, ahí lo estoy girando y se ve cortado la
        // última parte»).
        //
        // Esto REVIERTE una decisión anterior —el encuadre se medía una vez por vista y
        // no se tocaba, para que girar no cambiara el tamaño—. La razón por la que estaba
        // mal: al girar, el ancho proyectado de un acoplado de 12 m pasa de 12 m (de
        // costado) a 2,4 m (desde la puerta). Con una escala fija, cualquier ángulo que
        // no sea el medido queda o cortado contra el borde o diminuto en el medio. Que el
        // camión cambie de tamaño al girar molesta mucho menos que verlo cortado.
        //
        // Solo con zoom 1: si el usuario se acercó a mirar un bulto, reencuadrar le
        // sacaría de golpe el zoom que acaba de hacer.
        if (zoom === 1) aplicar(medirEncuadre(yaw, pitch));

        dibujar();
    });
    canvas.addEventListener('pointerup', () => {
        arrastre = null;
        canvas.style.cursor = '';
    });
    // Sin esto, el botón derecho abre el menú del navegador encima del camión y el
    // desplazamiento no se llega a ver. Va en el LIENZO y no en el documento: el clic
    // derecho tiene que seguir funcionando en el resto de la pantalla.
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());

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

    /** Los interruptores de «Nombres» y «Códigos», que se ven apagados al apagarse. */
    const interruptor = (id, leer, escribir) => {
        const b = boton(id, () => {
            escribir(!leer());
            b.setAttribute('aria-pressed', String(leer()));
            b.classList.toggle('bg-neutral-100', !leer());
            b.classList.toggle('text-neutral-400', !leer());
            dibujar();
        });

        return b;
    };

    interruptor('carga3dNombres', () => nombres, (v) => { nombres = v; });
    interruptor('carga3dCodigos', () => codigos, (v) => { codigos = v; });

    // Las VISTAS fijas. A diferencia del zoom, van también en celular: son la forma
    // de mirar la carga desde otro lado sin tener que arrastrar con el dedo.
    for (const clave of Object.keys(VISTAS)) {
        boton(`carga3dVista${clave}`, () => { vista(clave); dibujar(); });
    }

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

    /**
     * Un botón de paso que se puede MANTENER APRETADO y acelera.
     *
     * Es lo que permitió pasar de seis botones (−10/−5/−1/+1/+5/+10) a dos, sin
     * volver al problema que los había creado: con un paso fijo de a uno, llenar
     * un contenedor de 324 bultos era repetir el clic 324 veces. Manteniendo
     * apretado, el paso arranca en 1 y sube a 5 y después a 10, así que el
     * recorrido largo se hace igual de rápido y el corto sigue siendo exacto.
     *
     * Detalles que importan:
     * - `pointerdown` y no `mousedown`: el mismo handler sirve para dedo y mouse.
     * - El primer paso se aplica AL APRETAR, no al soltar: un toque suelto tiene
     *   que mover exactamente uno.
     * - Se corta con pointerup, pointercancel y pointerleave. Sin el leave, sacar
     *   el dedo del botón sin levantarlo dejaba el contador corriendo solo.
     * - `setPointerCapture` no se usa a propósito: capturaría el puntero y
     *   `pointerleave` no dispararía nunca.
     */
    const pasoRepetible = (id, signo) => {
        const el = document.getElementById(id);
        if (! el) return;

        let repetir = null;
        let acelerar = null;

        const frenar = () => {
            clearInterval(repetir);
            clearTimeout(acelerar);
            repetir = acelerar = null;
        };

        el.addEventListener('pointerdown', (e) => {
            e.preventDefault();          // que no arrastre ni seleccione el texto del botón
            frenar();
            fijar(cant + signo);         // el toque suelto mueve exactamente 1

            // 400 ms de gracia: recién ahí se entiende que lo está manteniendo.
            acelerar = setTimeout(() => {
                let paso = 1;
                let vueltas = 0;
                repetir = setInterval(() => {
                    vueltas++;
                    if (vueltas === 8) paso = 5;
                    if (vueltas === 20) paso = 10;
                    fijar(cant + signo * paso);
                }, 90);
            }, 400);
        });

        for (const evento of ['pointerup', 'pointercancel', 'pointerleave']) {
            el.addEventListener(evento, frenar);
        }
    };

    pasoRepetible('carga3dQuita1', -1);
    pasoRepetible('carga3dSuma1', +1);

    /**
     * Escribir la cantidad exacta (pedido del dueño 07-08: «dame la opción de agregar
     * números para hacer más exacta la carga»). Con solo + y −, llegar a 137 eran 137
     * toques; los pasos siguen sirviendo para ajustar de a poco.
     *
     * Se escucha `input` y no `change`: con `change` el dibujo recién se movería al
     * salir del campo, y lo que se quiere es ver la carga mientras se tipea.
     *
     * `fijar` ya capa contra 0 y TOPE, así que un 9999 se convierte en el máximo en
     * vez de romper nada. El campo vacío NO se fuerza a 0 mientras se edita —
     * borrarlo para escribir otro número es normal—; se acomoda al salir (`blur`).
     */
    const caja = document.getElementById('carga3dCantidad');
    if (caja) {
        caja.value = cant;
        caja.addEventListener('input', () => {
            if (caja.value.trim() === '') return;
            fijar(parseInt(caja.value, 10) || 0);
        });
        caja.addEventListener('blur', () => { caja.value = cant; });
    }

    /**
     * La BARRA: el tercer control del mismo número (pedido del dueño 07-08 mirando el
     * pallet cargado de EasyCargo). Arrastrar da el barrido rápido y la sensación de
     * «llenar»; el campo da el número exacto y los pasos ajustan de a uno.
     *
     * `input` y no `change`: la carga se dibuja MIENTRAS se arrastra, que es todo el
     * valor de tener una barra. Con `change` recién se movería al soltar.
     */
    const barra = document.getElementById('carga3dBarra');
    if (barra) {
        barra.value = cant;
        barra.addEventListener('input', () => fijar(parseInt(barra.value, 10) || 0));
    }

    /**
     * SUBIR / BAJAR el pallet del camión (pedido del dueño 06-08: «con la opción de
     * armarlo y luego subirlo al camión»).
     *
     * Al subir se llenan TODOS los pallets: es el momento de ver el resultado, y el paso
     * es explícito del usuario, así que no contradice la regla de que la pantalla abre
     * vacía. Al bajar vuelve el pallet al piso y el camión queda limpio.
     */
    const btnSubir = boton('carga3dSubir', () => {
        clearInterval(anim);
        subido = !subido;
        cant = subido ? TOPE : 0;
        btnSubir.textContent = subido ? '↓ Bajar del camión' : '↑ Subir al camión';
        // Reencuadra: con el pallet en el piso la escena es más ancha que el camión solo.
        encuadrar();
        dibujar();
    });

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

    /**
     * El recuadro cambia de tamaño cuando cambia la ventana (y cuando se abre el
     * menú lateral, que corre la columna). Se redibuja a la nueva medida en vez de
     * dejar el lienzo estirado.
     *
     * Se agenda con `requestAnimationFrame` porque al arrastrar el borde de la
     * ventana el evento llega decenas de veces por segundo, y remedir el encuadre en
     * cada uno sería trabajo tirado.
     */
    /**
     * El recuadro cambió de tamaño: se ajusta el mapa de bits y se vuelve a encuadrar.
     *
     * SÍNCRONO, sin `requestAnimationFrame`. La primera versión coalescía con rAF y tenía
     * un modo de falla feo: en una pestaña que no está pintando (en segundo plano) el rAF
     * no corre nunca, el flag de «ya hay uno agendado» quedaba puesto para siempre y el
     * visor no volvía a reencuadrar jamás. Coalescer no hacía falta: un ResizeObserver ya
     * avisa como máximo una vez por frame, y el trabajo es medir la silueta y redibujar,
     * que es lo mismo que hace un arrastre.
     *
     * Se reencuadra en los ÁNGULOS ACTUALES y no en los de la vista fija: si el usuario
     * venía girando a mano, volver a `vista(vistaActual)` le pegaría un salto de cámara
     * solo por abrir el menú.
     */
    const reacomodar = () => {
        if (!ajustarLienzo()) return;
        aplicar(medirEncuadre(yaw, pitch));
        dibujar();
    };

    window.addEventListener('resize', reacomodar);

    // El recuadro también cambia sin que cambie la ventana: al abrir o cerrar el MENÚ de
    // herramientas, que le come ancho al lienzo. Un `resize` de window no se enteraría, y
    // el camión quedaría dibujado a la medida vieja (cortado o chico).
    if (window.ResizeObserver) new ResizeObserver(reacomodar).observe(canvas);

    encuadrar();
    dibujar();
}
