# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-14 (v56 — Bloque A CERRADO; el Informe VIVE SOLO por decisión del dueño; EN PAUSA hasta el QA del dueño del Bloque A → luego Bloque B). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Bloque A · Servicio Técnico — CERRADO (menú 42 → 40)

Dos consolidaciones en producción con doble llave:
- **A1 Costos → Listado** (`0e9feaa`): desplegable «Configuración» (QR + Costos).
- **A2 Traslados → Listado** (`6a35329`): primera pestaña de flujo del Listado.

El Listado quedó como el hub que el mapa quería: config en el desplegable, flujo en pestaña.

## ❌ A3 · Informe — NO se construye. El Informe VIVE SOLO (decisión del dueño 14-ago)

El 2º nudo de permisos era de fondo, no de forma: el informe **industrial** tiene una
audiencia partida entre dos dominios que no comparten anfitrión —el técnico industrial solo
ve la Agenda; jefe_bodega y jefe_sucursal solo ven el Listado—, así que **ningún anfitrión
único lo cubre sin romper a alguien** (y duplicar está prohibido). El dueño resolvió lo
correcto según la propia lente del proyecto: **el Informe es el ítem que necesita vivir
solo**. No se toca. El ítem «Informe» se queda en el menú tal como está.

Consecuencia: el Bloque A cierra en **2 de 3** (−2, no −3). El menú queda en **40**, y el
mapa completo llegará a **31**, no 30. Una resta menos y cero accesos rotos — el proyecto
es para no romper cosas, no para llegar a un número redondo.

Aprendizaje del bloque, para los que vienen: **verificar permisos por ejecución NO es solo
“¿quién ve el ítem?” — es “¿quién ve el ítem Y puede llegar al anfitrión?”**, y hay que
cruzarlo en AMBAS direcciones. El Informe pasó tu F0 y mi primer análisis como «integrable»;
solo el cruce completo (audiencia del ítem × acceso al anfitrión, para cada candidato de
anfitrión) reveló que no calzaba. En el Bloque B esto importa: Conductores→Vehículos tiene
permisos OR cruzados igual de delicados.

## ⏸️ EN PAUSA — el Bloque B espera el QA del dueño del Bloque A

Protocolo de bloques (dueño): no se abre bloque nuevo hasta cerrar el anterior con QA.
**El dueño va a hacer el QA del Bloque A en el celular**:
- Listado con el desplegable «Configuración» (QR + Costos; cada uno solo para quien tiene
  su permiso) junto a «Registrar ingreso».
- Listado con la pestaña «Traslados al taller» (quien no tiene permiso de traslado ve solo
  «Listado», sin barra de pestañas).
- El ítem «Informe» sigue en el menú (no se tocó).

Cuando el dueño dé el visto bueno, te llega el dictado v57 abriendo el **Bloque B ·
Logística** (B1 Cargas reales → Simulador; B2 Conductores → Vehículos, la de permisos OR
cruzados). Hasta entonces: **pausa, no arranques**.

## Estado
- **Max-2** en pausa. **Marcos y el PR #9** activos. Re-fetch religioso cuando reanudes.

## Recordatorios (para el Bloque B)
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar; **cruce de
permisos en ambas direcciones ANTES de consolidar**; candado mutado; parte al buzón.
Baseline del Director: **2110 / 14.723** en `6a35329` (y subiendo — re-fetch).

CIERRE: Bloque A cerrado (2/3, Informe vive solo). Espera el QA del dueño → Bloque B.
