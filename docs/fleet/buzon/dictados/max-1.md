# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-13 (v52 — Lote 5 EN PRODUCCIÓN; fase «en vuelo» COMPLETA 47→42; EN PAUSA hasta el QA del dueño y el dictado del Bloque A). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Lote 5 está EN PRODUCCIÓN (merge `c5f5b47`, doble llave 13-ago) — y con él CIERRA la cola «en vuelo»

**Menú 43 → 42.** Suite del Director sobre el árbol mergeado: **2005 verdes / 14.193
aserciones** — tu delta 0 y tus +41 aserciones, exactos. Conté el menú (11+30+1=42), da.
Bundle byte-idéntico; el `<x-tab-nav>` compartido no lo tocaste (el guard vive en el
`_tabs`, correcto). Rama borrada.

**Cinco lotes, cinco restas, 47 → 42, cero pérdida de pantallas ni permisos.** Es un
proyecto entero cerrado limpio.

Lo mejor del Lote 5, y de la cola completa:

1. **Corregiste MI dictado con evidencia.** Escribí «permiso idéntico»; encontraste que es
   cierto solo para la mitad de escritura (seeder L172: el técnico industrial ve la Agenda
   con `ver agenda terreno`, sin `agendar servicio terreno`). Una pestaña sin gatear le
   daría 403 al usuario PRINCIPAL de esa pantalla. No te tragaste el error del dictado: lo
   mostraste con la línea del seeder y lo resolviste. Un forjador que confía ciegamente en
   el dictado propaga los errores del Director; tú los atrapas. Es exactamente lo que quiero.
2. **Reusaste el idioma correcto**: el `_tabs` de ST (pestañas calculadas por permiso) para
   el caso de la Agenda, y el `<x-tab-nav>` para la parte visual — sin tocar el componente
   compartido. Cada patrón en su lugar.
3. **La cola entera con proceso limpio**: de los lotes 3 al 5, rama cortada ANTES de tocar
   un archivo, baseline en worktree aislado, candado mutado en cada uno. Consistencia.

## ⏸️ EN PAUSA — la cola «en vuelo» terminó; el Bloque A espera dos cosas

No arranques nada. El mapa completo (47→30) está aprobado por el dueño **en bloques por
módulo** (§4.1 del plan), y la regla de apertura es dura:

- **El Bloque A (Servicio Técnico: Informe→Listado · Costos→Listado · Traslados→Listado)
  se abre con su PROPIO dictado**, no con este.
- Ese dictado no sale hasta que el dueño haga el **QA en celular de los lotes 4 y 5**
  (botón QR + pantalla QR con Volver; pestañas de la Agenda que el técnico industrial NO
  ve). Bloque cerrado con QA antes de abrir el siguiente — **nunca dos bloques a la vez**.

Cuando el dueño dé el visto bueno, te llega el dictado del Bloque A con A1 (Informe→Listado)
como primer lote. Hasta entonces: pausa.

## Territorio
- **Max-2** en pausa (M11 100 % construido).
- **Marcos** MUY activo en el simulador. Si retomas, rama corta y re-fetch religioso.

## Nota de infra (I-10, en el tablero)
GitHub sigue con 500 intermitentes en `git push` a main mientras su estado dice
«operacional». Receta: push a rama `tmp/*` (sube objetos + aísla el ref), reintenta main;
borra la temporal por API si el git también cae. §I-10.

## Recordatorios (para cuando se abra el Bloque A)
Rama nueva desde main FRESCO antes de tocar un archivo; suite COMPLETA de main fresco
ANTES de empezar; candado mutado; parte al buzón. Un lote por doble llave dentro del bloque.

CIERRE: cola «en vuelo» completa (47→42). Bloque A en espera del QA del dueño. Buen trabajo.
