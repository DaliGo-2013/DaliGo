// Ensambla design/dist a partir de design/src:
//  - Inyecta el CSS compilado (design/.capture/design-tw.css; fallback: el bundle
//    de producción public/build/assets/app-*.css vía manifest) en el placeholder
//    /*__DALIGO_CSS__*/ de cada HTML → previews AUTOCONTENIDOS.
//  - Verifica que el marcador @dsCard sea la PRIMERA línea de cada HTML.
//  - Valida < 256 KiB por archivo (límite de claude.ai/design) — aborta si se pasa.
//  - Copia los .md (brief) tal cual.
// Uso: node design/tools/build.mjs
import { readFileSync, writeFileSync, readdirSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const repo = join(raiz, '..');
const srcDir = join(raiz, 'src');
const distDir = join(raiz, 'dist');
const LIMITE = 256 * 1024;

// CSS: preferir el build dedicado (incluye clases de las variantes); si no existe,
// caer al bundle de producción (solo clases ya usadas por la app).
let css = '';
const dedicado = join(raiz, '.capture', 'design-tw.css');
if (existsSync(dedicado)) {
    css = readFileSync(dedicado, 'utf8');
    console.log(`CSS: design-tw.css dedicado (${(css.length / 1024).toFixed(1)} KB)`);
} else {
    const manifest = JSON.parse(readFileSync(join(repo, 'public', 'build', 'manifest.json'), 'utf8'));
    const entrada = Object.values(manifest).find((e) => e.file?.endsWith('.css') || e.css?.length);
    const archivo = entrada.file?.endsWith('.css') ? entrada.file : entrada.css[0];
    css = readFileSync(join(repo, 'public', 'build', archivo), 'utf8');
    console.warn(`CSS: FALLBACK bundle de producción ${archivo} — clases nuevas de las variantes pueden faltar.`);
}

mkdirSync(distDir, { recursive: true });

let errores = 0;
for (const nombre of readdirSync(srcDir).sort()) {
    const origen = join(srcDir, nombre);

    if (nombre.endsWith('.md')) {
        const md = readFileSync(origen, 'utf8');
        writeFileSync(join(distDir, nombre), md, 'utf8');
        console.log(`OK ${nombre} (copiado, ${(md.length / 1024).toFixed(1)} KB)`);
        continue;
    }
    if (!nombre.endsWith('.html')) continue;

    let html = readFileSync(origen, 'utf8');

    if (!/^<!-- @dsCard /.test(html)) {
        console.error(`ERROR ${nombre}: la primera línea no es el marcador @dsCard.`);
        errores++;
        continue;
    }
    if (!html.includes('/*__DALIGO_CSS__*/')) {
        console.error(`ERROR ${nombre}: falta el placeholder /*__DALIGO_CSS__*/ en el <head>.`);
        errores++;
        continue;
    }

    html = html.replace('/*__DALIGO_CSS__*/', () => css);

    const bytes = Buffer.byteLength(html, 'utf8');
    if (bytes >= LIMITE) {
        console.error(`ERROR ${nombre}: ${(bytes / 1024).toFixed(1)} KB >= 256 KiB.`);
        errores++;
        continue;
    }

    writeFileSync(join(distDir, nombre), html, 'utf8');
    console.log(`OK ${nombre} (${(bytes / 1024).toFixed(1)} KB)`);
}

if (errores) {
    console.error(`\n${errores} error(es) — dist INCOMPLETO, no subir.`);
    process.exit(1);
}
console.log('\ndist listo: design/dist');
