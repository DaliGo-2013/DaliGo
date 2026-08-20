# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-20 (v80 — OPE-3 EN PRODUCCIÓN: fase B de
> Operación COMPLETA. GO F0-LOGÍSTICA: la auditoría que además GATILLA el
> reparto a dos forjadores). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ OPE-3 está EN PRODUCCIÓN (merge `ab0a8d1`, doble llave 20-ago)

Suite del Director: **2303 / 16.048, CERO rojos** — tu +4/+19 clavado, sin el
+24 como predijiste. Rama borrada. **Fase B de Operación COMPLETA**: 3 lotes,
los 4 hallazgos aprobados forjados, cero rojos propios. Lo que quedó fino: la
doble vuelta de la Ñ derivada (un patrón nuevo trae su variante solo), el
porqué del nivel 2 ESCRITO EN EL ARCHIVO de config, y la mutación que puso
rojo un candado VIEJO — la constante era exactamente lo que ese test vigilaba.
Eso va al acta del módulo.

El QA del dueño del módulo Operación corre EN PARALELO (desviación declarada
del patrón QA-antes-de-F0: tu F0 es solo-docs y el dueño ya definió que
F0-LOGÍSTICA gatilla el reparto con Max-2 — retrasarlo retrasa la mesa grande.
Si su QA trae hallazgos, se dictan como lotes de fix y se secuencian).

## 🔨 GO — F0-LOGÍSTICA: auditoría SOLO DOCS del 4º módulo (M)

Formato invariante (anexo **§5.4** del plan): valor · file:line · qué controla
EN PALABRAS DE NEGOCIO · repetido · veredicto propuesto (3 niveles) · esfuerzo.
CERO código. Alcance del apartado Logística de la sidebar: **Despachos**
(cola/hoja de ruta/PWA conductor — respeto de autor Max-2: qué es negocio y
qué es motor), **Cargas/Simulador**, **Vehículos**, **Conductores**
(vive-solo), **Bodegas/Traslados** (lo que no cayó en M04/Operación — deslinda
con §5.3, sin doble conteo).

Semillas del Director (verifica, no asumas):
- El simulador de carga: capacidades/dimensiones de vehículos que estén a mano
  (el dueño ya pidió una vez «capacidad de carga» en el backlog de Trello).
- La PWA del conductor: umbrales de reintento/cola offline — ¿motor (nivel 3)
  o negocio?
- QR anti-fraude de despachos: ventanas de validez si las hay.
- Los `paginate(25)` del módulo (adopción POR_PAGINA, molde COM-2).
- `max:` y topes en formularios de despacho/carga.

Radar del cruce: hallazgos de OTROS módulos se ANOTAN para su anexo, no se
desparraman (doctrina vigente).

### Entrega
Parte al buzón con el mapa en §5.4. SOLO DOCS — sin doble llave de código.
**Tu parte gatilla el REPARTO**: el Director dicta entonces qué módulo audita
Max-2 en paralelo (territorios disjuntos; lotes de fase B secuenciados cuando
toquen ConfiguracionController/Seeder).

## Estado
Max-2: en pausa LEYENDO los moldes (v34) — entra al reparto con tu parte.
Marcos: Trello lo identifica solo (workflow por autor). Baseline: 2303/16.048
en `ab0a8d1`.

CIERRE: GO F0-LOGÍSTICA. Tercer módulo saldado en código; el cuarto abre la
cancha para dos.
