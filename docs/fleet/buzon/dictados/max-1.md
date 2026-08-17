# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-17 (v60 — C1 EN PRODUCCIÓN; GO C2 «Registro del sistema» 3→1, la primera consolidación múltiple). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ C1 está EN PRODUCCIÓN (merge `fc47fd1`, doble llave 17-ago) — menú 39 → 38

Suite del Director sobre el árbol mergeado: **2186 verdes / 15.142, CERO rojos**. Conté el
menú (38), da. Rama borrada. Tu decisión de la card «Roles» (retirarla, precedente Lote 1):
correcta y bien declarada — así se maneja un hallazgo fuera de dictado.

## 🙏 Tu AVISO del rojo ajeno: ORO — ya está saldado

Tu diagnóstico era exacto y el Director lo llevó una capa más abajo: no era solo «test
stale», era **bomba de calendario** — `addDays(5)` cae en fin de semana los lunes y martes,
y la regla nueva de `684a989` (lunes a viernes) lo rechaza. Por eso a mí me dio verde el
viernes (B1) y a ti rojo hoy lunes, dos veces, determinista. **Hotfix del Director directo
a main (`5892eea`, con llave del dueño): `addWeekdays(5)` + comentario del porqué.** CI
Tests de main de vuelta en verde. Aislar el rojo en baseline limpia y NO tocarlo por ser
territorio ajeno: exactamente el protocolo. Sigue así.

## 🔨 GO — Lote C2: «Registro del sistema» (3→1) — menú 38 → 36

La PRIMERA consolidación de MÚLTIPLES ítems en un anfitrión. El cruce ya lo hicimos los
dos: `view audit`, `view notificaciones`, `view aprobaciones` viven SOLO en la lista
maestra → **audiencia idéntica (admin) por construcción**. Sin nudo. Aún así: pestañas
gateadas cada una por SU permiso (en render, como C1) — si la UI sumó permisos a alguien,
la forma aguanta sola.

### La forma

1. **Anfitrión: Auditoría** (`admin.audits.index`), rebautizada en el menú como
   **«Registro del sistema»**. Fuera los ítems `notificaciones` y `historial-aprobaciones`;
   sus patrones (`admin.notificaciones.*`, `admin.aprobaciones.*`) entran al `activo` del
   anfitrión. −2 rótulos.
2. **`admin/audits/_tabs.blade.php`**: «Cambios · Notificaciones · Aprobaciones» —
   **tab-nav TRIPLE** (`grid-cols-3` ya existe en el componente; primera vez que se usa en
   una consolidación). Cada pestaña bajo su `can(...)`.
3. Montaje en los 3 index. Revisa el layout de cada uno (¿`py-12` sin `space-y`? — el
   `div.mb-6` de C1 si aplica).
4. **`MenuConsolidacionesTest`: entradas 9ª y 10ª** (`admin.notificaciones.` y
   `admin.aprobaciones.` → anfitrión; respeta la forma exacta del mapa del candado, ruta
   hoja o `{prefijo}index`). **Mutación DOBLE**: quitar cada patrón por separado → sus 2
   rojos exactos cada uno → restaurar → verde.
5. **Cards del Inicio: son 3, no 2** (el radar v59 se quedó corto — L45-47 de
   `AccesosDashboard`: auditoria, notificaciones, aprobaciones). Decisión dictada, con el
   precedente C1/Lote 1: **queda UNA card «Registro del sistema»** (ruta del anfitrión,
   desc que abarque: «Cambios, notificaciones y aprobaciones») y se RETIRAN las otras dos,
   con comentario del porqué. Si el candado Dashboard exige otra cosa, decláralo.
6. **Sobreviven**: los links de la campanita (apuntan a rutas conservadas) y
   `admin.notificaciones.prueba` (mismo prefijo que su index — cae en el `activo` sin lío).
7. Comentarios con rastro si algún ítem tenía nota defendiendo su lugar.
8. Prefijos: corre tu `Str::is` entre `admin.audits.*` y los dos nuevos patrones — disjuntos
   a ojo, pero decláralo.

### Verificación (invariante)
Rama `feature/menu-c2-registro-sistema` desde main FRESCO (`fc47fd1` o posterior — el
hotfix del calendario ya está dentro). Suite COMPLETA de main fresco ANTES (baseline
Director: **2186/15.142** en `fc47fd1`). Batería dirigida + Dashboard/DashboardColores +
las carpetas Audits/Notificaciones/Aprobaciones. Conteo tinker: **36**. Parte al buzón;
espera doble llave. NO arranques D.

## 📡 Después de C2
QA del dueño del Bloque C completo (Usuarios·Roles + Registro del sistema) → Bloque D
(Kardex→Producción, ex-huérfana, candado duro) → E (Configuración de producción 4→1 + la
deuda del `<x-tab-nav>` a 4 pestañas). Mapa final: **32**.

## Estado
Max-2 en pausa (v24). Marcos activísimo en visita pública/agenda — re-fetch religioso.

CIERRE: GO C2. El lote más denso hasta ahora — un lote, un parte, una llave. Fierro.
