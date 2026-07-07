/**
 * Service worker de DaliGo (spike P-SPK-01, base para la cola offline P-SPK-02).
 *
 * Estrategia CONSERVADORA pensada para una app Blade con sesion/CSRF:
 *  - JAMAS se cachea HTML autenticado ni JSON: las paginas siempre van a la red.
 *  - /build/* (assets de Vite, nombres hasheados = inmutables): cache-first en
 *    un cache runtime aparte, con poda por conteo (los deploys diarios generan
 *    hashes nuevos que se irian acumulando).
 *  - Navegaciones: red primero; si la RED FALLA (catch), se sirve /offline.
 *    OJO: el fallback va SOLO en el catch — nunca por status/response.ok. Una
 *    navegacion con redirect (el 302 del middleware auth, login/logout) llega
 *    aca como "opaqueredirect" con status 0, y un chequeo por ok mostraria la
 *    pagina offline en cada redirect. 419/500 tambien se muestran tal cual.
 *  - Todo lo demas (non-GET, cross-origin como las fuentes de bunny.net, /up):
 *    return temprano SIN respondWith — el navegador lo maneja nativo. Envolver
 *    el passthrough en fetch() rompe redirects/streaming y suma fallos.
 *
 * REGLA DE INVALIDACION: si se toca resources/views/offline.blade.php hay que
 * subir la version de CACHE aqui (el update del SW es byte-diff de ESTE archivo;
 * cambiar el Blade no lo gatilla). Comentario espejo en offline.blade.php.
 */
const CACHE = 'daligo-v1';
const RUNTIME = 'daligo-runtime-v1';
const OFFLINE_URL = '/offline';
const MAX_RUNTIME_ENTRIES = 30;

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE);
            // cache:'reload' salta el HTTP cache (no congelar una copia vieja);
            // una respuesta redirected cacheada para navegacion revienta en
            // Chrome con TypeError, por eso se valida antes de guardar.
            const resp = await fetch(OFFLINE_URL, { cache: 'reload' });
            if (resp.ok && !resp.redirected) {
                await cache.put(OFFLINE_URL, resp);
            }
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keep = [CACHE, RUNTIME];
            for (const key of await caches.keys()) {
                if (!keep.includes(key)) await caches.delete(key);
            }
            await self.clients.claim();
        })(),
    );
});

/** Cache-first para un asset inmutable de /build, con poda por conteo. */
async function assetCacheFirst(request) {
    const cache = await caches.open(RUNTIME);
    const hit = await cache.match(request);
    if (hit) return hit;

    const resp = await fetch(request);
    if (resp.ok) {
        await cache.put(request, resp.clone());
        const keys = await cache.keys();
        if (keys.length > MAX_RUNTIME_ENTRIES) {
            // FIFO simple: sobran los mas viejos (hashes de deploys anteriores).
            await Promise.all(keys.slice(0, keys.length - MAX_RUNTIME_ENTRIES).map((k) => cache.delete(k)));
        }
    }
    return resp;
}

/** Fallback offline con red de seguridad si el cache fue purgado (iOS). */
async function paginaOffline() {
    const hit = await caches.match(OFFLINE_URL);
    return hit ?? new Response(
        '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sin conexión</title><body style="font-family:sans-serif;text-align:center;padding:4rem 1rem;color:#171717"><h1 style="font-size:1.25rem">Sin conexión</h1><p>Revisa tu señal e inténtalo de nuevo.</p></body></html>',
        { headers: { 'Content-Type': 'text/html; charset=utf-8' } },
    );
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Passthrough total (sin respondWith): non-GET, cross-origin y health check.
    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname === '/up') {
        return;
    }

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(assetCacheFirst(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => paginaOffline()));
    }
    // El resto de GETs same-origin (iconos, favicon, etc.): passthrough nativo.
});
