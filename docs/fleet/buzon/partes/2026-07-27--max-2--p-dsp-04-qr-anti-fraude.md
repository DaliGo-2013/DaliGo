# Parte de cierre — Max-2 · P-DSP-04 · QR anti-fraude de retiro (M07)

CUENTA: Max-2 (Forjador B, stream 2 · DESPACHOS) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **P-DSP-04** — GO en firme del dictado v10
ESTADO: **HECHA**

## EVIDENCIA

- Rama NUEVA desde main fresco, como ordenó el dictado v10: **`feature/despachos-qr`**
  (base `origin/main` @ `6f4c8c0`). `feature/despachos-v1` cumplió su ciclo: verifiqué que
  `origin/main..origin/feature/despachos-v1` está **vacío** (main tiene todo) antes de dejarla.
- Commit: **`6704c43`** — `feat(despachos): P-DSP-04 — QR anti-fraude de retiro (M07)`
- TESTS: **1008 verdes / 5486 aserciones**, suite COMPLETA.
  **Baseline de main medida por mí en el worktree hoy: 989 / 5394** — no las 920/4418 del
  dictado; main siguió avanzando entre tu medición y esta sesión. P-DSP-04 aporta **+19**
  (`tests/Feature/Despachos/RetiroQrTest.php`).
- Bundle **`app-bJbInchW.css`**: superset verificado contra el de main (`app-DcH-lDk3.css`,
  que es el que yo generé en el refresh) — **0 de 584 clases perdidas**, +4 propias
  (`text-5xl`, `p-10` del monitor; `text-brand-50`/`text-red-50` de los veredictos).
- Paleta: grep de los 15 matices prohibidos sobre mis vistas nuevas = **0**. El rojo aparece
  solo en el rechazo del escaneo y en el saldo pendiente (excepción sancionada: lo negativo).
- Migración nueva: `entrega_observacion` **varchar(191)**, no 255 — leído tu aviso de I-07.
  `ConfiguracionSeedLongitudTest` (tu candado) pasa.

## Los 6 puntos del dictado

**1 · Lock + evidencia.** `validarRetiro` abre transacción, toma la fila con `lockForUpdate` y
**re-lee el estado con la fila bloqueada**. Los 3 resultados dejan fila en `escaneos_despacho`
(append-only): `valido` → pasa a `retirado` y sella hora; `doble_retiro` (ya salió de bodega) →
**ALERTA y el estado NO se toca**; `estado_invalido` (ciclo ya cerrado) → tampoco retira.
**Mutación verificada** (doctrina del verde-engañoso): reemplacé el lock por la instancia stale
y `test_dos_escaneos_sobre_la_misma_instancia_stale_solo_uno_es_valido` se puso **rojo**; con el
lock, verde. El test protege lo que dice proteger.

**2 · QR firmado.** `URL::signedRoute` sobre el **código `DSP-`**, no el id — el código ya nacía
impredecible en P-DSP-03, así que no es enumerable ni alterable. La página imprimible reusa
`canvas[data-qr]` → `dibujarQrsMostrador` de M12: **cero JS nuevo, cero dependencia de servidor**
(el chunk `qrcode` ya viaja en el bundle). `Despacho::urlFicha()` emite la URL firmada y la usan
los dos caminos de llegada: el QR pegado en la carga y el enlace del panel.

**3 · Superficie — decisión.** **Autenticada (`manage despachos`) Y ADEMÁS firmada**, no una de
las dos:
- *Solo firmada* (semi-pública) sería un agujero: quien le saque una foto al QR —el cliente,
  alguien en la fila— marcaría el retiro desde su celular sin pasar por bodega. Eso **es** el
  fraude que M07 viene a cerrar.
- *Solo autenticada* perdería la integridad del link: la firma es lo que impide editar el código
  en la barra de direcciones para apuntar a otro despacho (tengo test de eso).
Con las dos: el QR identifica la carga sin ser adivinable y el retiro queda a nombre de un
operador real, cuyo `user_id` viaja a la evidencia. Documentado en el docblock del controlador.
**Además: el GET solo LEE.** Un escáner de bodega o un F5 repiten el GET; si el GET marcara el
retiro, cada recarga sería un "segundo escaneo" con su alerta falsa — y una alerta falsa es una
alerta que nadie mira, justo lo contrario de lo que este paso construye. El POST es el escaneo.

**4 · Cola «McDonald's».** `admin/despachos/cola` para el monitor: cargas preparadas, la que
espera hace más rato primero, tipografía grande. El poll pide el **conteo** JSON
(`porConfirmarConteo` de ST como patrón) y **solo recarga si el número cambió**; se pausa con la
pestaña oculta. Un monitor encendido todo el día no puede recargar HTML cada pocos segundos.

**5 · Entrega total/parcial.** El parcial **exige** el saldo (`entrega_observacion`) y el saldo
se ve en el listado y en la ficha. Mismo lock: cerrar dos veces no pisa la hora del primero.
**Alcance honesto:** la UI rica de entrega (firma + foto + hora offline) es **P-DSP-05**, del
conductor. Lo de acá es la contraparte de bodega + la regla de dominio con sus tests, para que
P-DSP-05 se apoye en ella en vez de re-inventarla.

**6 · No dupliqué** el guard de re-verificación contra Bsale de `crearDesdeDocumento`.

## Contratos nuevos: nacidos cumpliéndolos

Las 3 pantallas nuevas usan `<x-app-layout ancho="...">` (sin contenedor propio), salida por
`:back` del `page-header` (sin `:cancel`), y los tests asertan según errores amables. Nota que
vale para el próximo: **el rechazo por firma inválida también es "amable"** (GET → Inicio con
aviso, no 403 crudo). Para el operador de bodega es mejor que una pantalla técnica, pero al
asertar hay que esperar `assertRedirect(dashboard)`, no `assertForbidden`.

## Dos tropiezos propios, para que no los repita nadie

1. **`git checkout -- <archivo>` me borró trabajo NO commiteado.** Al restaurar la mutación de
   prueba con `git checkout --`, el archivo volvió a HEAD… que era la versión de *main*, sin mis
   ~110 líneas de P-DSP-04. Lo reconstruí completo y la suite quedó verde otra vez, pero la
   lección es dura: **mutar SOLO sobre árbol ya commiteado**, o restaurar desde una copia propia.
   La bitácora dice "revertir la mutación con `git checkout --`, nunca a mano" — le falta la
   condición: eso vale cuando el resto del archivo ya está commiteado.
2. **Helper de test con UNIQUE fijo.** Mi `despacho()` creaba siempre `bsale_document_id = 900`;
   cualquier test con dos despachos moría por violación de unique (5 rojos de golpe). Contador
   propio. Prima del gotcha 2026-07-13 (factory con valor aleatorio): un helper con valor **fijo**
   en columna unique falla en cuanto se usa dos veces.

## Sobre tu aviso del cron

`bsale:sync-documents` en producción: no lo pude observar desde el worktree (no tengo acceso al
`storage/logs` del servidor). Si querés que lo revise, necesito el log por el canal de siempre.

/usage INICIO → FIN: sesión heredada del hilo del dueño, no comparable con un asiento limpio.

## SIGUIENTE

**P-DSP-05 — PWA del conductor** (M08-MVP): hoja de ruta por zona con lectura offline, entrega
con firma+foto+hora, cola IndexedDB `entregas` con `entrega_uuid` + unique + `lockForUpdate` +
`ValidationException` + rama `expectsJson()` (el patrón de la cola offline del soplador,
bitácora 2026-07-02). Las columnas `capturado_at`, `entrega_uuid`, `firma_path`, `foto_path` ya
existen desde P-DSP-03 y `registrarEntrega` ya tiene la regla del parcial: P-DSP-05 se apoya.

Aplicando **la regla que adoptaste** (refrescar por paso): esta rama tiene 1 commit sobre main
fresco, así que el merge es de ventana corta. Recomiendo doble llave ahora y arrancar P-DSP-05
desde main otra vez, en vez de encadenar dos pasos en la rama.
