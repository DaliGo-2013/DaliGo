# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-18 (v64 — QA del dueño del Bloque D ✅: GO E1 «Configuración de producción» 4→1 — EL CIERRE DEL MAPA). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ QA del Bloque D aprobado por el dueño (18-ago)

Verificado: Producción resalta en el Kardex, Volver al panel, sidebar limpia, botón de la
cabecera como entrada. **Bloque D cerrado con QA.** Marcador: 47 → 35.

## 🔨 GO — Lote E1: «Configuración de producción» 4→1 (35 → 32) — EL CIERRE

Máquinas · Tipos de botellón · Recetas · Moldes → un ítem. El lote mayor del mapa (−3),
guardado para el final y solo, como pidió el dueño. El reconocimiento del Director,
TERMINADO hoy:

### Cruce de audiencias (hecho): las 4 bajo `manage production`
MenuPrincipal L90-95: permiso IDÉNTICO por construcción en los 4 ítems. **Las 4 pestañas
SIN gateo** — la precondición B1, la más limpia, para el lote más grande. (El soplador
con `report production` no ve nada de esto, como siempre.)

### Novedad del reconocimiento que CORRIGE al radar v63 (buenas noticias × 2)
1. **`grid-cols-4` YA está en el bundle** — lo usan aprobaciones/carga/devoluciones/
   instalaciones. Extender el tab-nav NO cambia el CSS compilado: **bundle byte-idéntico
   alcanzable**. La receta I-06 queda de respaldo por si otra clase nueva aparece.
2. **`DashboardColoresTest` NO fija la key `maquinas`** (verificado por grep). Igual:
   **conserva la key `maquinas`** — menos churn, precedente C2 (key `auditoria`).

### La forma

1. **Anfitriona: Máquinas** (primera de la fila física: máquina → molde → tipo → receta),
   ítem rebautizado **«Configuración de producción»**, key `maquinas` conservada.
   `activo`: `['admin.maquinas.*', 'admin.tipos-botellon.*', 'admin.recetas.*',
   'admin.moldes.*']` — wildcards limpios (prefijos únicos, sin vecinos que enciendan).
   Si tu estudio del código da una anfitriona mejor, propónla en el parte ANTES de
   forjar distinto — contra-evidencia admitida, como en el Lote 5.
2. **La deuda del `<x-tab-nav>` se paga aquí** (`tab-nav.blade.php` L19): fuera el
   ternario `count===3 ? cols-3 : cols-2` → **mapa count→clase** (2,3,4; con default
   sano). Con 4 pestañas: las 4 en UNA fila. Es el primer cambio al componente
   compartido desde su nacimiento en el Lote 3 — la batería debe incluir a TODOS sus
   consumidores (Catálogo, Documentos, Agenda, Usuarios, Registro del sistema,
   Simulador).
3. **`admin/maquinas/_tabs.blade.php`**: «Máquinas · Tipos de botellón · Recetas ·
   Moldes», sin gateo. Montaje SOLO en los 4 index (hijas fuera: moldes tiene
   show/mantencion, máquinas y tipos create/edit — precedente Documentos/C1). Revisa el
   layout de cada index (¿`space-y-6` o el `div.mb-6` de C1?).
4. **Ítems `tipos-botellon`, `recetas`, `moldes` FUERA del menú.** Comentarios con
   rastro donde haya notas defendiendo lugar propio.
5. **P-NAV-06, molde D1 exacto — SOLO para Tipos de botellón**: sale del candado con
   rastro (sus vidas) + recupera su `<x-volver>` (al index de Máquinas o al panel — lo
   que el flujo pida) + entra a `test_pantalla_hija_tiene_exactamente_un_volver` en el
   MISMO commit. **Máquinas NO sale**: sigue siendo ítem (anfitriona) — su ruta sigue
   en el menú y el candado la sigue viendo. Recetas y Moldes nunca fueron huérfanas:
   solo pierden el ítem, la pestaña es su navegación (VolverTest deriva — declara lo
   que se mueva solo).
6. **`MenuConsolidacionesTest`: entradas 12ª, 13ª y 14ª** (tipos-botellon, recetas,
   moldes → anfitrión). **Mutación TRIPLE**: quitar cada patrón por separado → sus
   rojos exactos → restaurar → verde. La escala nueva del molde C2.
7. **Cards del Inicio: NO hay de los 4** (verificado por grep) — nada que retirar;
   decláralo con el grep en el parte.
8. **Links cruzados que deben sobrevivir** (batería dirigida): panel de Producción →
   máquinas/moldes; **backflush completo** (`RecetaBackflushTest` + `RecetaCrudTest`);
   **semáforo/mantención de moldes M11** (`ProduccionMoldeTest` + `moldes.mantencion`);
   OEE si toca máquinas. El kardex de D1 ni se toca.
9. Reflejos: `Str::is` entre los 4 patrones nuevos y los del `activo` de `produccion`
   (lista explícita) + `produccion.mi.*` del soplador — todo disjunto a ojo, decláralo.

### Verificación (invariante, con el peso del cierre)
Rama `feature/menu-e1-configuracion-produccion` desde main FRESCO. Suite COMPLETA de
main fresco ANTES (baseline Director: **2196/15.231** en `61dd90d`; Marcos sigue activo —
recuenta). Batería dirigida = la de siempre + TODOS los consumidores del tab-nav + la
carpeta Producción completa. Conteo tinker: **32**. Bundle: si quedó byte-idéntico,
decláralo; si no, I-06 (recompilar sobre el árbol del lote + superset vs ambos padres).
Parte al buzón; espera doble llave. Con E1 en producción **el mapa F0 queda CERRADO**.

## 📡 Después de E1
QA del dueño (los 12 puntos ya entregados; críticos: 4-en-fila en celular + backflush
intacto) → **PLAN-MENU-DENSIDAD CERRADO: 47 → 32**. El Director prepara el acta de
cierre del proyecto y el veredicto de qué sigue (decisión del dueño).

## Estado
Max-2 en pausa (v24). Marcos activo. Baseline: 2196/15.231 en `61dd90d`.

CIERRE: GO E1. El último lote del mapa — un lote, un parte, una llave. A cerrar con la
misma mano que abrió: fierro.
