/**
 * Cola offline de DaliGo (spike P-SPK-02; generalizada en P-DSP-05).
 *
 * DOS stores en la misma BD:
 *   - 'tandas'   (v1): las tandas del soplador — payload JSON plano.
 *   - 'entregas' (v2): las entregas del conductor — payload multipart, porque
 *     la firma y la foto son ARCHIVOS. IndexedDB guarda los Blobs por
 *     structured clone; al drenar se reconstruye el FormData.
 *
 * Cuando el operario registra SIN señal, guardamos en IndexedDB y reenviamos
 * solos al volver la conexión (evento 'online' + al cargar la página — iOS no
 * tiene Background Sync, así que NO usamos sync events).
 *
 * Idempotencia: cada item lleva un uuid; el servidor ignora el que ya registró
 * (unique [reporte_id, cliente_uuid] en tandas; unique entrega_uuid en
 * despachos), así un reintento no duplica.
 *
 * Clasificación de errores del drenado (para no borrar en silencio ni
 * reintentar en bucle):
 *   - 2xx             -> éxito, se borra de la cola (incluye el caso idempotente).
 *   - 422 / 403       -> PERMANENTE (validación fallida / sin permiso / ya no
 *                        corresponde): se marca el item con error y su status —
 *                        la UI de rechazados lo muestra para acción manual.
 *   - 419 / 5xx / red -> TRANSITORIO: se queda en cola, se reintenta luego.
 * Tras varios transitorios seguidos se deja de auto-reintentar (evita bucle si
 * el servidor está caído); el usuario puede recargar para forzar otro intento.
 */

const DB_NAME = 'daligo';
const STORE_TANDAS = 'tandas';
const STORE_ENTREGAS = 'entregas';
const MAX_INTENTOS = 5;

function abrir() {
    return new Promise((resolve, reject) => {
        // v2: se agrega el store 'entregas'. El upgrade es ADITIVO (los checks
        // de contains lo hacen idempotente): una BD v1 conserva sus tandas.
        const req = indexedDB.open(DB_NAME, 2);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE_TANDAS)) {
                db.createObjectStore(STORE_TANDAS, { keyPath: 'uuid' });
            }
            if (!db.objectStoreNames.contains(STORE_ENTREGAS)) {
                db.createObjectStore(STORE_ENTREGAS, { keyPath: 'uuid' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function tx(db, modo, store) {
    return db.transaction(store, modo).objectStore(store);
}

function prom(req) {
    return new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function todosDe(store) {
    const db = await abrir();
    return prom(tx(db, 'readonly', store).getAll());
}

async function borrarDe(store, uuid) {
    const db = await abrir();
    await prom(tx(db, 'readwrite', store).delete(uuid));
}

async function guardarEn(store, item) {
    const db = await abrir();
    await prom(tx(db, 'readwrite', store).put(item));
}

/**
 * Clasifica la respuesta de UN item y actualiza la cola. Compartido por ambos
 * drenados; devuelve 'enviado' | 'permanente' | 'transitorio'.
 */
async function clasificar(store, item, resp) {
    if (resp.ok) {
        await borrarDe(store, item.uuid);
        return 'enviado';
    }
    if (resp.status === 422 || resp.status === 403) {
        // Se guarda el status: la UI de rechazados distingue "datos inválidos"
        // (422) de "sin permiso / ya no corresponde" (403).
        await guardarEn(store, { ...item, error: true, status: resp.status });
        return 'permanente';
    }
    // 419 (sesión), 5xx, etc.: transitorio con tope de intentos.
    const intentos = (item.intentos ?? 0) + 1;
    await guardarEn(store, { ...item, intentos, error: intentos >= MAX_INTENTOS });
    return 'transitorio';
}

function tokenCsrf() {
    // FRESCO del <meta> de la página viva (nunca se serializa en la cola:
    // quedaría stale tras un rato offline).
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ---------------------------------------------------------------------------
// TANDAS del soplador (API original, intacta — mi-reporte.blade.php la consume
// vía window.dgCola).
// ---------------------------------------------------------------------------

/** Encola una tanda. `item` = { uuid, url, campos: {...} }. */
export async function encolar(item) {
    await guardarEn(STORE_TANDAS, { ...item, intentos: 0, error: false, creado: Date.now() });
}

/** Todos los items en cola (incluidos los marcados con error). */
export async function todos() {
    return todosDe(STORE_TANDAS);
}

/** Cuántos items hay pendientes de enviar (excluye los marcados error permanente). */
export async function pendientes() {
    return (await todos()).filter((i) => !i.error).length;
}

let drenando = false;

/**
 * Reenvía la cola de tandas. Guard de reentrada: 'online' y 'load' pueden
 * dispararse casi juntos. Devuelve { enviados, permanentes, transitorios }.
 */
export async function drenar() {
    if (drenando || !navigator.onLine) return { enviados: 0, permanentes: 0, transitorios: 0 };
    drenando = true;
    const conteo = { enviados: 0, permanentes: 0, transitorios: 0 };

    try {
        const token = tokenCsrf();
        for (const item of await todos()) {
            if (item.error) continue; // permanente: espera acción manual

            let resp;
            try {
                resp = await fetch(item.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ ...item.campos, cliente_uuid: item.uuid }),
                });
            } catch (e) {
                conteo.transitorios++; // fallo de red: se queda en cola
                continue;
            }

            conteo[(await clasificar(STORE_TANDAS, item, resp)) + 's']++;
        }
    } finally {
        drenando = false;
    }

    return conteo;
}

// ---------------------------------------------------------------------------
// ENTREGAS del conductor (P-DSP-05): multipart con firma + foto como Blobs.
// ---------------------------------------------------------------------------

/**
 * Encola una entrega. `item` = { uuid, url, campos: {...}, blobs: { firma, foto } }.
 * Los Blobs viajan a IndexedDB por structured clone; al drenar se reconstruye
 * el FormData. `campos` NO lleva el uuid (se agrega al drenar como entrega_uuid).
 */
export async function encolarEntrega(item) {
    await guardarEn(STORE_ENTREGAS, { ...item, intentos: 0, error: false, creado: Date.now() });
}

/** Todas las entregas en cola (incluidas las rechazadas — la UI las lista). */
export async function todasEntregas() {
    return todosDe(STORE_ENTREGAS);
}

/** Entregas pendientes de enviar (excluye rechazadas). */
export async function pendientesEntregas() {
    return (await todasEntregas()).filter((i) => !i.error).length;
}

/** Descarta una entrega (la UI de rechazados llama esto tras mostrarle el motivo). */
export async function borrarEntrega(uuid) {
    await borrarDe(STORE_ENTREGAS, uuid);
}

let drenandoEntregas = false;

/** Reenvía la cola de entregas reconstruyendo el multipart de cada una. */
export async function drenarEntregas() {
    if (drenandoEntregas || !navigator.onLine) return { enviados: 0, permanentes: 0, transitorios: 0 };
    drenandoEntregas = true;
    const conteo = { enviados: 0, permanentes: 0, transitorios: 0 };

    try {
        const token = tokenCsrf();
        for (const item of await todasEntregas()) {
            if (item.error) continue;

            const fd = new FormData();
            for (const [k, v] of Object.entries(item.campos ?? {})) {
                fd.append(k, v);
            }
            fd.append('entrega_uuid', item.uuid);
            // Los nombres de archivo importan: el server valida mimetypes por
            // contenido, pero un nombre con extensión ayuda al diagnóstico.
            if (item.blobs?.firma) fd.append('firma', item.blobs.firma, 'firma.png');
            if (item.blobs?.foto) fd.append('foto', item.blobs.foto, 'entrega.jpg');

            let resp;
            try {
                resp = await fetch(item.url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        // SIN Content-Type: el navegador pone el boundary del
                        // multipart. Escribirlo a mano lo rompe.
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                });
            } catch (e) {
                conteo.transitorios++;
                continue;
            }

            conteo[(await clasificar(STORE_ENTREGAS, item, resp)) + 's']++;
        }
    } finally {
        drenandoEntregas = false;
    }

    return conteo;
}

// ---------------------------------------------------------------------------

/**
 * Engancha el drenado de AMBAS colas a los eventos de red y a la carga. Tras
 * drenar con éxito (cero pendientes y ≥1 enviado), recarga para reconciliar la
 * UI con la fuente de verdad del servidor. Si quedan items rechazados o
 * transitorios NO recarga (perdería el aviso en pantalla).
 */
export function iniciarColaOffline() {
    const intentar = async () => {
        if ((await pendientes()) + (await pendientesEntregas()) === 0) return;
        const tandas = await drenar();
        const entregas = await drenarEntregas();
        const enviados = tandas.enviados + entregas.enviados;
        if (enviados > 0 && (await pendientes()) + (await pendientesEntregas()) === 0) {
            window.location.reload();
        } else {
            window.dispatchEvent(new CustomEvent('daligo:cola-cambio'));
        }
    };

    window.addEventListener('online', intentar);
    if (document.readyState === 'complete') intentar();
    else window.addEventListener('load', intentar);
}
