// Limpia el HTML capturado por tests/Design/DesignCaptureTest.php y lo deja como
// preview estático en design/src/: sin scripts, sin CSRF, sin assets de Vite,
// forms neutralizados y links inertes. Inserta el placeholder de CSS inline y el
// marcador @dsCard como PRIMERA línea (requisito del pane de claude.ai/design).
// Uso: node design/tools/clean-capture.mjs
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
mkdirSync(join(raiz, 'src'), { recursive: true });

const PANTALLAS = [
    { in: 'dashboard.html', out: '20-pantalla-dashboard.html', titulo: 'DaliGo · Dashboard (pantalla actual)' },
    { in: 'produccion.html', out: '21-pantalla-produccion.html', titulo: 'DaliGo · Panel de Producción (pantalla actual)' },
    { in: 'servicio-tecnico.html', out: '22-pantalla-servicio-tecnico.html', titulo: 'DaliGo · Servicio Técnico (pantalla actual)' },
];

const MARCADOR = '<!-- @dsCard group="Pantallas actuales" -->';

for (const p of PANTALLAS) {
    const origen = join(raiz, '.capture', p.in);
    if (!existsSync(origen)) {
        console.error(`FALTA ${origen} — corre primero: php artisan test tests/Design/DesignCaptureTest.php`);
        process.exitCode = 1;
        continue;
    }
    let html = readFileSync(origen, 'utf8');

    // 1) Fuera todo <script> (Alpine/Vite/inline). Los atributos x-data/x-show
    //    quedan inertes sin JS; los dropdowns server-side ya vienen cerrados.
    html = html.replace(/<script\b[\s\S]*?<\/script>/gi, '');

    // 2) Fuera assets de Vite (css/js del build) y preloads; se conserva bunny.net.
    html = html.replace(/<link\b[^>]*\/build\/assets\/[^>]*>/gi, '');
    html = html.replace(/<link\b[^>]*rel="(?:modulepreload|preload)"[^>]*>/gi, '');

    // 3) Fuera CSRF (meta + inputs ocultos).
    html = html.replace(/<meta\b[^>]*name="csrf-token"[^>]*>/gi, '');
    html = html.replace(/<input\b[^>]*name="_token"[^>]*>/gi, '');

    // 4) Forms neutralizados (solo vista): sin action/method/onsubmit.
    html = html.replace(/<form\b([^>]*)>/gi, (m, attrs) => {
        const limpio = attrs
            .replace(/\s(?:action|method)="[^"]*"/gi, '')
            .replace(/\son\w+="[^"]*"/gi, '');
        return `<form${limpio}>`;
    });
    html = html.replace(/\sonclick="[^"]*"/gi, '');

    // 5) Links inertes: todo href http(s) local o de la app → "#".
    html = html.replace(/href="https?:\/\/(?:localhost|127\.0\.0\.1)[^"]*"/gi, 'href="#"');

    // 6) Placeholder de CSS inline (build.mjs lo reemplaza por el CSS compilado).
    if (!html.includes('/*__DALIGO_CSS__*/')) {
        html = html.replace(/<\/head>/i, '    <style>/*__DALIGO_CSS__*/</style>\n</head>');
    }

    // 7) Título propio de la tarjeta.
    html = html.replace(/<title>[\s\S]*?<\/title>/i, `<title>${p.titulo}</title>`);

    // 8) Marcador @dsCard como PRIMERA línea.
    html = html.replace(/^﻿?\s*/, '');
    if (!html.startsWith('<!-- @dsCard')) {
        html = `${MARCADOR}\n${html}`;
    }

    const destino = join(raiz, 'src', p.out);
    writeFileSync(destino, html, 'utf8');
    console.log(`OK ${p.in} -> src/${p.out} (${(html.length / 1024).toFixed(1)} KB)`);
}
