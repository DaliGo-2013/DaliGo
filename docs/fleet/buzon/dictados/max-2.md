# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-14 (v5 — **SHAPE RECIBIDO: GO P-DSP-01**). Manda sobre lo anterior.

## 🟢 DESBLOQUEADO: el shape de documents.json ESTÁ EN EL BUZÓN
`docs/fleet/buzon/anexo-p-dsp-00-shape-documents.md` — salida íntegra de la IA-cPanel
(APROBADO 1/1) + reconciliación del Director contra el plan §1.2. Riesgo #1 RESUELTO:
`details` viene en el GET (sobre anidado `{items}`). PAUSA el micro-backlog M15 si lo
empezaste y arranca P-DSP-01 con estos 4 ajustes OBLIGATORIOS a la migración/sync:
1. `state` es **INT** (no string(32) como asumía el plan) — igual `commercialState` y
   `cancellationStatus` (guárdalos: detectan anulación, mitigan el riesgo #2).
2. El nodo `client` puede NO venir (los 3 docs de muestra no lo traían — probable boleta):
   sync tolerante a ausencia, match solo cuando venga; verifica contra una factura durante
   la implementación.
3. La línea de detalle NO trae `description`: el fallback `descripcion` sale del nodo
   `variant` (4 claves — confirma si trae code/description) o del producto espejado.
4. **675.912 documentos totales**: backfill completo PROHIBIDO. Sync por ventana
   `emissiondaterange` con fecha de arranque configurable (default conservador: últimos 7
   días al primer run). El Director le plantea la fecha definitiva al dueño — no te bloquea.
Confirmado sin cambios: epoch→datetime, montos int/decimal(14,4) OK, `office.id` para el
match, `document_type.id` anidado. Al cerrar P-DSP-01 archiva el anexo (ya redactado) en
`docs/qa/INFRA/2026-07-14--INFRA--p-dsp-00-shape-documents.md` y marca P-DSP-00 [x].

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño usarlo); si no, Opus 4.8 · high.

## Causa raíz de por qué tu delegación a IA-cPanel no respondió (ahora resuelta)
No era que Mauricio no la despachara: **el server corre `main`, no tu rama** — el
`bsale:explore` desplegado no tenía la sección §7 que agregaste en `feature/despachos-v1`. Un
comando delegado al operador de cPanel solo puede correr lo que YA está en producción.
Mauricio (con Fable 5, fuera de tu cola) trajo SOLO ese archivo a main
(`feature/bsale-explore-documentos`) y el Director lo mergeó con doble llave (`a6cd35a`,
Deploy+Tests success 14-07 15:22) — **ya está desplegado y servido**.

**Gotcha para la bitácora** (territorio de CLAUDE.md, no tuyo hoy — pero anótalo cuando toques
esa unidad): todo comando de diagnóstico que se delega a un operador remoto (IA-cPanel,
soporte) debe estar en `main` desplegado ANTES de despachar la delegación; nunca en una rama
sin mergear.

## SIGUIENTE: la delegación se re-despacha — sigues bloqueado hasta que vuelva la salida
Mauricio va a correr `bsale:explore` en el server ahora que está desplegado y traerte la
salida de "Documentos de venta". Tú NO tienes nada que hacer para acelerar esto — sigue en el
micro-backlog M15 (aprobado, ver abajo) hasta que la salida llegue al buzón.

## ✅ P-DSP-00 (código) y P-DSP-02 (zonas) — siguen VERIFICADOS (sin cambios respecto a v3)
`3a79b69`/`0a4d063`: comando read-only con valores redactados ✓ · 3 migraciones aditivas +
`Cliente::zonaEfectiva()` con precedencia cliente-explícito > vendedor > null ✓ · 6 tests,
suite 576 verde tras refresco con M14. Buen criterio adelantar P-DSP-02 sin esperar el shape.

## ✅ Micro-backlog M15 — sigue aprobado, continúa ahí
Rama `feature/m15-microbacklog` desde main FRESCO. Alcance (talla S/M):
- Correo destino configurable en el panel de notificaciones (hoy hardcodeado — confirma dónde).
- Error SMTP sin truncar en el log/vista de notificación fallida.
- Endurecer `test_campanita_visible_en_el_nav` (o el que corresponda) contra flaky.
Suite verde por commit. Parte al buzón por ítem. Cuando llegue la salida del shape al buzón,
PAUSA el micro-backlog y vuelve a P-DSP-01 (prioridad: destrabar la unidad grande).

## PRE-STAGE para cuando llegue el shape (delegación YA despachada a IA-cPanel 14-07)
Para que el turnaround sea inmediato, cuando la salida aterrice en el buzón reconcilia
contra el plan ANTES de escribir la migración:
1. **details:** ¿"✅ details PRESENTE"? Si NO → GET a `documents/{id}/details.json` como plan B
   y la migración no cambia (solo el `DocumentSync` hace 1+N con `each`); documenta cuál fue.
2. **Cabecera vs §1.2:** diff claves reales vs columnas asumidas (`number`, `totalAmount`,
   `netAmount`, `taxAmount`, `state`, `informedSii`, `urlPdf`, `emissionDate`,
   `document_type`/`client`/`office` como nodos). Columna asumida que NO existe → fuera de la
   migración (no inventar); clave real útil no prevista → anótala, decide si entra a v1.
3. **Fechas:** confirmar epoch (int ~10 dígitos — el explorador marca "(¿epoch?)") → cast
   epoch→datetime en el sync, como las 4 syncs existentes.
4. **client/office:** confirmar que traen `id` de Bsale para el match contra
   `clientes.bsale_client_id` / `bodegas.bsale_office_id` (mapas `pluck`, patrón existente).
5. Archiva la salida (ya redactada) en `docs/qa/INFRA/2026-07-14--INFRA--p-dsp-00-shape-documents.md`
   — es la evidencia que cierra P-DSP-00 [x].
Observación del Director al plan (no bloquea): `hourlyAt(45)` comparte slot con `sync-stock`
(corren secuenciales en el mismo `schedule:run`; stock procesa ~28k filas). Si el minuto :45
queda pesado, considera `hourlyAt(30)` junto a prices (más liviana) — decisión tuya al
implementar, documentada en el plan.

CIERRE por ítem: parte a docs/fleet/buzon/partes/ + push.
