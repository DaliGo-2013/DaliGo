/**
 * Visor 3D del simulador de carga (LOGÍSTICA).
 *
 * SIN LIBRERÍAS a propósito. Todo lo que hay que dibujar son prismas
 * rectangulares: la silueta del camión (cabina, chasis, ruedas, caja) y los
 * bultos. Proyectar prismas y ordenarlos por profundidad son ~150 líneas, contra
 * los ~150 KB comprimidos que costaría una librería 3D. Si algún día hacen falta
 * luces, texturas o formas curvas, ahí sí conviene traerla — y entra por import
 * dinámico igual que ésta, así que no cambia nada del bundle global.
 *
 * NO existe ningún modelo 3D: la silueta se deriva de las medidas del vehículo y
 * cada bulto de las suyas. Cambiar un número en la base cambia el dibujo.
 *
 * Se carga solo en esta pantalla (ver el import dinámico en app.js).
 */

const CARAS = [[0, 1, 2, 3], [4, 5, 6, 7], [0, 1, 5, 4], [3, 2, 6, 7], [0, 3, 7, 4], [1, 2, 6, 5]];
// Sombreado por cara: da volumen sin necesitar luces ni normales.
const SOMBRA = [0.78, 0.92, 1.0, 0.72, 0.86, 0.66];

const v8 = (x, y, z, a, b, c) => [
    [x, y, z], [x + a, y, z], [x + a, y + c, z], [x, y + c, z],
    [x, y, z + b], [x + a, y, z + b], [x + a, y + c, z + b], [x, y + c, z + b],
];

export default function iniciarCarga3d(canvas, datos) {
    const ctx = canvas.getContext('2d');
    const veh = datos.vehiculo;
    // La carga viaja SIEMPRE como lista de bloques (cupo máximo = un bloque;
    // carga mixta = un bloque por tipo colocado, con su color y su posición).
    const bloques = datos.bloques || [];

    let yaw = -0.85, pitch = -0.3, cant = Math.round(datos.tope * 0.6), anim = null, arrastre = null;
    let CX = 0, CY = 0, ESC = 100, OFF = [0, 0, 0], cola = [];

    function proyectar(p) {
        const x0 = p[0] - OFF[0], y0 = p[1] - OFF[1], z0 = p[2] - OFF[2];
        const cy = Math.cos(yaw), sy = Math.sin(yaw), cp = Math.cos(pitch), sp = Math.sin(pitch);
        const x = x0 * cy - z0 * sy, z = x0 * sy + z0 * cy;
        const y2 = y0 * cp - z * sp, z2 = y0 * sp + z * cp;
        const f = 1 / (1 + z2 * 0.048);
        return [CX + x * ESC * f, CY - y2 * ESC * f, z2];
    }

    function prisma(x, y, z, a, b, c, col, alpha = 1, borde = 'rgba(0,0,0,.22)') {
        const pv = v8(x, y, z, a, b, c).map(proyectar);
        CARAS.forEach((f, k) => {
            const zc = (pv[f[0]][2] + pv[f[1]][2] + pv[f[2]][2] + pv[f[3]][2]) / 4;
            const s = SOMBRA[k];
            cola.push({
                z: zc,
                pts: f.map((i) => pv[i]),
                fill: `rgba(${col.map((v) => Math.round(v * s)).join(',')},${alpha})`,
                borde,
            });
        });
    }

    /** Rueda: octógono extruido. Redonda sin necesitar geometría curva. */
    function rueda(cx, cy, z, r, ancho) {
        const N = 8, paso = Math.PI / N, per = [];
        for (let i = 0; i < N; i++) {
            const a = paso + (i * 2 * Math.PI) / N;
            per.push([cx + Math.cos(a) * r, cy - r + Math.sin(a) * r]);
        }
        const A = per.map((p) => proyectar([p[0], p[1], z]));
        const B = per.map((p) => proyectar([p[0], p[1], z + ancho]));
        const zc = (q) => q.reduce((s, p) => s + p[2], 0) / q.length;
        for (let i = 0; i < N; i++) {
            const j = (i + 1) % N, q = [A[i], A[j], B[j], B[i]];
            cola.push({ z: zc(q), pts: q, fill: 'rgb(40,40,45)', borde: null });
        }
        cola.push({ z: zc(A), pts: A, fill: 'rgb(58,58,64)', borde: 'rgba(0,0,0,.3)' });
        cola.push({ z: zc(B), pts: B, fill: 'rgb(30,30,34)', borde: 'rgba(0,0,0,.3)' });
    }

    function pintar() {
        cola.sort((a, b) => b.z - a.z);
        cola.forEach((o) => {
            ctx.beginPath();
            o.pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
            ctx.closePath();
            ctx.fillStyle = o.fill;
            ctx.fill();
            if (o.borde) { ctx.strokeStyle = o.borde; ctx.lineWidth = 1; ctx.stroke(); }
        });
        cola = [];
    }

    function dibujar() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const chas = 0.18;
        const largoCab = Math.min(1.75, veh.largo * 0.42);
        const altoCab = Math.min(veh.alto * 0.82, 1.95);
        const suelo = -chas - 0.62;
        const total = veh.largo + largoCab;

        OFF = [(veh.largo - largoCab) / 2, veh.alto / 2 + suelo / 2, veh.ancho / 2];
        CX = canvas.width / 2;
        CY = canvas.height / 2 + 26;
        ESC = Math.min(canvas.width / (total * 1.45), canvas.height / (veh.alto * 3.0), 168);

        // sombra en el piso
        const sp = [[-largoCab, suelo, 0], [veh.largo, suelo, 0], [veh.largo, suelo, veh.ancho], [-largoCab, suelo, veh.ancho]].map(proyectar);
        ctx.beginPath();
        sp.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.fillStyle = 'rgba(0,0,0,.06)';
        ctx.fill();

        // silueta
        prisma(-largoCab, -chas, 0, veh.largo + largoCab, veh.ancho, chas, [70, 74, 80]);
        prisma(-largoCab, 0, 0.02, largoCab, veh.ancho - 0.04, altoCab, [58, 110, 170]);
        prisma(-largoCab + 0.06, altoCab * 0.42, -0.01, largoCab * 0.62, veh.ancho - 0.12, altoCab * 0.4, [120, 170, 215], 0.95);
        const rr = 0.42, rw = 0.24;
        const ejes = veh.ejes === 3
            ? [-largoCab * 0.55, veh.largo * 0.6, veh.largo * 0.6 + 1.05]
            : [-largoCab * 0.55, veh.largo * 0.72];
        ejes.forEach((ex) => [-0.04, veh.ancho - rw + 0.04].forEach((rz) => rueda(ex, -chas, rz, rr, rw)));

        // carga, en orden de estiba: bloque a bloque (fondo -> puerta, el
        // controlador los manda ya ordenados), y dentro de cada bloque
        // fondo -> puerta, abajo -> arriba
        let puestos = 0, listo = false;
        for (const blq of bloques) {
            if (listo) break;
            const rej = blq.rejilla, ori = blq.orientacion, col = blq.color || [234, 88, 12];
            let delBloque = 0;
            for (let ix = 0; ix < rej.largo && !listo; ix++) {
                for (let iz = 0; iz < rej.ancho && !listo; iz++) {
                    for (let iy = 0; iy < rej.alto; iy++) {
                        if (puestos >= cant) { listo = true; break; }
                        if (delBloque >= blq.cantidad) break;
                        prisma(blq.x + ix * ori.largo, iy * ori.alto, blq.y + iz * ori.ancho,
                            ori.largo * 0.985, ori.ancho * 0.985, ori.alto * 0.985, col);
                        puestos++;
                        delBloque++;
                    }
                }
            }
        }

        // caja: piso opaco + paredes translúcidas para ver la carga adentro
        prisma(0, -0.04, 0, veh.largo, veh.ancho, 0.04, [225, 225, 228]);
        prisma(0, 0, 0, veh.largo, veh.ancho, veh.alto, [252, 252, 253], 0.16, 'rgba(90,90,95,.55)');
        pintar();

        ctx.fillStyle = '#8a8a8a';
        ctx.font = '600 15px -apple-system,system-ui,sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText('PUERTA →', canvas.width - 22, 30);
        ctx.textAlign = 'left';

        const n = document.getElementById('carga3dN');
        if (n) n.textContent = cant;
    }

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

    dibujar();
}
