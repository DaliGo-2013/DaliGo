# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-12 (v48 — Lote 1 EN PRODUCCIÓN; GO Lote 2: retiro del boceto «Seguimiento»). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Lote 1 está EN PRODUCCIÓN (merge `ca91422`, doble llave 12-ago)

Verificación del Director con dos corridas de suite (Marcos ganó la carrera del primer
push — I-08 de siempre): **1966 verdes / 13.963 aserciones, cero rojos**. Deploy y Tests
de CI verdes. Rama borrada. **Menú: 47 → 46.** El proyecto de densidad tiene su primera
resta en producción.

Tres cosas de tu lote que quedan como estándar: el `aria-current="true"` en vez de
`"page"` (esquivar la colisión con el conteo de SidebarTest ANTES de que muerda, con el
porqué escrito en el propio partial), el mini-candado genérico con mapa `CONSOLIDADAS`
—los 4 lotes que siguen lo heredan con una línea— y la carrera de 11 commits que
detectaste y absorbiste tú mismo durante el gate.

Nota de entorno (mía, no tuya): un rojo de `ErroresServidorTest` en mi primera corrida
era vendor desincronizado en MI worktree — `composer install` y 12/12 verde sin tocar
código. Tu cifra estaba bien desde el principio.

## 🟢 GO — Lote 2 · Retiro del boceto «Seguimiento» (47→46 → **45**)

El más barato de la cola y el único que RESTA código en vez de moverlo:

- **Retiro COMPLETO**: ítem del menú + rutas + controlador/acciones si son exclusivas +
  vista(s) + cualquier assert que lo cubra. **No se esconde: se retira** — si algún día
  se retoma, vive en git (la rama `feature/st-seguimiento-boceto` sigue en el remoto).
- **Verifica antes de cortar**: que nada más enlace a esas rutas (grep de `route(` en
  vistas y controladores) y que no haya permiso huérfano que quede sin usar (si lo hay,
  decláralo — no lo borres del seeder sin declararlo, la matriz es territorio sensible).
- **Candados**: la suite completa debe quedar verde sin amoldar tests ajenos; si algún
  test cubría el boceto, se retira CON él y se declara en el parte. El mini-candado del
  Lote 1 no aplica acá (no hay anfitrión) — el conteo del menú viaja en el parte.
- Si al abrir el boceto encuentras que SÍ tiene datos reales o usuarios (contra lo que
  dijo tu F0), **para y repórtalo**: el retiro se cancela y pasa a decisión del dueño.

## Después de este lote (la cola aprobada, no arranques sin dictado)
Lote 3 Estado→Documentos · Lote 4 QR→Listado ST · Lote 5 Servicios de terreno→Agenda.
El resto del mapa F0 sigue esperando visto bueno del dueño, apartado por apartado.

## Territorio
- **Max-2** en pausa (M11 100 % construido).
- **Marcos** MUY activo en el simulador (2 carreras ganadas hoy). Rama corta, push
  temprano, re-fetch religioso.

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (baseline
del Director: **1966 / 13.963** en `ca91422`). Suite completa antes del push. Parte al
buzón → doble llave → Lote 3.

CIERRE: parte a docs/fleet/buzon/partes/ + push. Restar también es construir.
