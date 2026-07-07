/**
 * Cola offline de tandas de producción (spike P-SPK-02).
 *
 * Cuando el soplador registra una tanda SIN señal, la guardamos en IndexedDB y
 * la reenviamos sola cuando vuelve la conexión (evento 'online' + al cargar la
 * página — iOS no tiene Background Sync, así que NO usamos sync events).
 *
 * Idempotencia: cada item lleva un cliente_uuid; el servidor ignora el UUID ya
 * registrado (unique [reporte_id, cliente_uuid]), así un reintento no duplica.
 *
 * Clasificación de errores del drenado (para no borrar en silencio ni reintentar
 * en bucle):
 *   - 2xx           -> éxito, se borra de la cola (incluye el caso idempotente).
 *   - 422 / 403     -> PERMANENTE (validación fallida o reporte ya no editable):
 *                      se marca el item como error y se deja para acción manual.
 *   - 419 / 5xx / red -> TRANSITORIO: se queda en cola, se reintenta luego.
 * Tras varios transitorios seguidos se deja de auto-reintentar (evita bucle si
 * el servidor está caído); el usuario puede recargar para forzar otro intento.
 */

const DB_NAME = 'daligo';
const STORE = 'tandas';
const MAX_INTENTOS = 5;

function abrir() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => {
            const db = req.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'uuid' });
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

function tx(db, modo) {
    return db.transaction(STORE, modo).objectStore(STORE);
}

function prom(req) {
    return new Promise((resolve, reject) => {
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

/** Encola una tanda. `item` = { uuid, url, campos: {...} }. */
export async function encolar(item) {
    const db = await abrir();
    await prom(tx(db, 'readwrite').put({ ...item, intentos: 0, error: false, creado: Date.now() }));
}

/** Todos los items en cola (incluidos los marcados con error). */
export async function todos() {
    const db = await abrir();
    return prom(tx(db, 'readonly').getAll());
}

/** Cuántos items hay pendientes de enviar (excluye los marcados error permanente). */
export async function pendientes() {
    return (await todos()).filter((i) => !i.error).length;
}

async function borrar(uuid) {
    const db = await abrir();
    await prom(tx(db, 'readwrite').delete(uuid));
}

async function guardar(item) {
    const db = await abrir();
    await prom(tx(db, 'readwrite').put(item));
}

let drenando = false;

/**
 * Reenvía la cola. Guard de reentrada: 'online' y 'load' pueden dispararse casi
 * juntos. Devuelve { enviados, permanentes, transitorios } para que el llamador
 * decida si recargar. El CSRF se lee FRESCO del <meta> de la página viva (nunca
 * se serializa en la cola: quedaría stale tras un rato offline).
 */
export async function drenar() {
    if (drenando || !navigator.onLine) return { enviados: 0, permanentes: 0, transitorios: 0 };
    drenando = true;
    let enviados = 0, permanentes = 0, transitorios = 0;

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
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
                transitorios++; // fallo de red: se queda en cola
                continue;
            }

            if (resp.ok) {
                await borrar(item.uuid);
                enviados++;
            } else if (resp.status === 422 || resp.status === 403) {
                await guardar({ ...item, error: true }); // permanente
                permanentes++;
            } else {
                // 419 (sesión), 5xx, etc.: transitorio con tope de intentos.
                const intentos = (item.intentos ?? 0) + 1;
                await guardar({ ...item, intentos, error: intentos >= MAX_INTENTOS });
                transitorios++;
            }
        }
    } finally {
        drenando = false;
    }

    return { enviados, permanentes, transitorios };
}

/**
 * Engancha el drenado a los eventos de red y a la carga. Tras drenar con éxito
 * (cola vacía de pendientes y ≥1 enviado), recarga para reconciliar la UI con
 * la fuente de verdad del servidor. Si quedan items rechazados/transitorios NO
 * recarga (perdería el aviso en pantalla).
 */
export function iniciarColaOffline() {
    const intentar = async () => {
        if ((await pendientes()) === 0) return;
        const { enviados } = await drenar();
        if (enviados > 0 && (await pendientes()) === 0) {
            window.location.reload();
        } else {
            window.dispatchEvent(new CustomEvent('daligo:cola-cambio'));
        }
    };

    window.addEventListener('online', intentar);
    if (document.readyState === 'complete') intentar();
    else window.addEventListener('load', intentar);
}
