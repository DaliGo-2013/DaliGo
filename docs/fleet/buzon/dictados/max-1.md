# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-14 (v57 — QA del Bloque A OK; se ABRE el Bloque B · Logística; GO B1: Cargas reales → Simulador. B2 Conductores VIVE SOLO). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ El dueño hizo el QA del Bloque A — todo funcionando. Se ABRE el Bloque B.

El Bloque A cerró en producción (menú 40; Costos + Traslados en el Listado, Informe vive
solo). Arranca **B · Logística**. Antes de dictar crucé los permisos de las DOS
consolidaciones en ambas direcciones (la lección del Informe). Resultado: **una consolida
limpia, la otra vive sola.**

## 🟢 GO — B1 · «Cargas reales» pasa a pestaña del «Simulador de carga» (40 → **39**)

- **Permiso IDÉNTICO**: ambos bajo `simular carga` (Cargas reales calibra la misma
  calculadora). Quien ve el Simulador ve Cargas reales → **consolidación limpia**, cero
  riesgo de acceso. Confírmalo en tu baseline igual.
- **Anfitrión**: Simulador de carga. Pestaña «Cargas reales» con el `<x-tab-nav>` del Lote
  3. El Simulador ya CONSUME Cargas reales (el factor «medido en terreno») y la enlaza
  dos veces — los links se reapuntan a la pestaña.
- **⚠️ Reemplaza una decisión previa del código, CON conciencia**: el comentario de
  `MenuPrincipal` (hoy L139-143) dice que Cargas reales «va como ítem aparte a propósito»
  porque el Simulador se usa ANTES de cargar y esto se anota DESPUÉS. **El dueño decidió
  consolidar igual** (14-ago): la pestaña no impide anotarla después — solo agrupa bajo un
  ítem. **Actualiza ese comentario** a la nueva decisión (no lo borres sin más: explica que
  antes vivía aparte por el momento de uso y que el dueño resolvió que ese matiz no pesa lo
  suficiente frente a la densidad). Es el mismo estándar del QR: la decisión previa se
  reemplaza dejando rastro del porqué.
- **Mini-candado**: 7ª entrada en `CONSOLIDADAS` + **mútala** (quitar la ruta del `activo`
  del anfitrión → 2 rojos → restaurar → verde).
- **`VolverTest`**: Cargas reales era ítem; al pasar a pestaña, ajústalo por la fuente única.
- **Ruta y permiso se CONSERVAN** — mudanza, no retiro.

## ❌ B2 · Conductores — NO se construye. Conductores VIVE SOLO (decisión del dueño 14-ago)

El cruce de permisos (que hice ANTES de dictar, no a mitad) dio el mismo nudo del Informe:
`jefe_ventas` y `tecnico` administran Conductores hoy (por `manage servicio tecnico`) pero
**NO** tienen `ver|manage vehiculos` → **no pueden entrar a Vehículos**; solo `jefe_logistica`
y `admin` (por `manage vehiculos`) ven ambos. Conductores tiene audiencia partida entre
servicio-técnico y logística, sin anfitrión común. El mapa F0 asumió que gatear la pestaña
bastaba, pero el problema es LLEGAR al anfitrión, no ver la pestaña.

**Decisión del dueño: Conductores vive solo.** No se toca. En el mapa (§5.1) su veredicto
pasa de «integrable → Vehículos» a **vive-solo**. Es el 2º ítem (con el Informe) que el
criterio del proyecto deja fuera por audiencia partida.

## Consecuencia y cola
El Bloque B es **un solo lote** (B1). Tras B1 en producción + QA del dueño, se abre el
**Bloque C · Administración** (C1 Roles→Usuarios, C2 «Registro del sistema» 3→1). Menú
final del mapa: **32** (no 30 — Informe y Conductores viven solos).

## Estado
- **Max-2** en pausa. **Marcos y el PR #9** activos (main creció: servicios terreno,
  autorización de citas, jefe_logistica/jefe_despacho nuevos). Re-fetch religioso.

## Recordatorios
Rama nueva desde main FRESCO (`589754a` o posterior — re-fetch); suite COMPLETA de main
fresco ANTES de empezar; candado mutado; parte al buzón. **Cruce de permisos en ambas
direcciones ANTES de consolidar** — ya nos ahorró romper el Informe y Conductores.

CIERRE: parte a docs/fleet/buzon/partes/. B1 Cargas reales → doble llave → QA → Bloque C.
