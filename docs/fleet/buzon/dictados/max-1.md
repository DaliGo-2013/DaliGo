# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-14 (v58 — B1 EN PRODUCCIÓN; Bloque B CERRADO; EN PAUSA hasta el QA del dueño del Bloque B → luego Bloque C). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ B1 está EN PRODUCCIÓN (merge `c67d882`, doble llave 14-ago) — menú 40 → 39

Suite del Director sobre el árbol re-mergeado: **2182 verdes / 15.096 aserciones**. Conté
el menú (11+27+1=39), da. **Re-merge por I-08**: durante mi verificación entró un drift de
Sucursales + plazos (territorio disjunto del tuyo) → auto-merge sin conflicto, suite entera
re-corrida. Rama borrada. **Bloque B CERRADO en un solo lote** (B2 Conductores vive solo).

Lo que quedó fino en B1:

1. **Verificaste el permiso y confirmaste que era limpio POR CONSTRUCCIÓN** (las 5 rutas en
   el mismo grupo `permission:simular carga`) — no asumiste «mismo permiso», lo mostraste.
2. **Reescribiste el comentario del ítem-aparte dejando el rastro** (estándar del QR): la
   nota vieja defendía el ítem separado por el momento de uso; la nueva explica que el
   dueño resolvió que ese matiz no pesa frente a la densidad. Reemplazar con el porqué
   escrito, no borrar. Así se pisa una decisión previa: a la vista.
3. **Cazaste el detalle del prefijo con `Str::is`**: `admin.carga.*` no matchea
   `admin.cargas-reales.index` (el punto corta). Tercer prefijo tramposo que atrapas
   (traslados/bodegas, dte, ahora carga). Es un reflejo ya.

## 📊 El marcador del proyecto: 47 → 39 en producción

Ocho lotes ejecutados (L1-L5 + A1 + A2 + B1) y **dos vive-solos decididos con evidencia**
(Informe, Conductores) — ambos por el mismo patrón: audiencia partida entre dos dominios
sin anfitrión común. El mapa final: **32** (Bloques C+D+E restan 7 más).

## ⏸️ EN PAUSA — el Bloque C espera el QA del dueño del Bloque B

Protocolo de bloques: no se abre el siguiente hasta cerrar el anterior con QA. **El dueño
va a hacer el QA del Bloque B en el celular**:
- El Simulador de carga con las pestañas «Simulador · Cargas reales» arriba (ambas visibles
  a todo portador de `simular carga`).
- Cargas reales operando igual (anotar, borrar, el factor por combinación).
- La sidebar de Logística sin el ítem «Cargas reales» suelto.

Cuando el dueño dé el visto bueno, te llega el dictado v59 abriendo el **Bloque C ·
Administración**. Adelanto para que lo tengas en el radar (NO arranques):
- **C1 Roles → Usuarios**: pestaña «Roles». Cruzaré `manage roles` (Roles) vs `view users`
  (anfitrión Usuarios) en ambas direcciones ANTES de dictar — quién define roles pero no
  ve Usuarios, y viceversa.
- **C2 «Registro del sistema» (3→1)**: la primera consolidación de MÚLTIPLES ítems en un
  anfitrión — Auditoría + Notificaciones + Historial de aprobaciones, cada una con su
  permiso. Tab-nav triple (`grid-cols-3`, existe). Cruzaré los tres permisos igual.

Hasta el dictado v59: **pausa, no arranques**.

## Estado
- **Max-2** en pausa (dictado v24: sin lote seguro; retoma con ronda 2 de Luis o dos-manos).
- **Marcos y el PR #9** muy activos (el drift de hoy: Sucursales, plazos, cierres de
  agenda). Re-fetch religioso al reanudar.

## Recordatorios (para el Bloque C)
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar; **cruce de
permisos en ambas direcciones ANTES de consolidar**; candado mutado; parte al buzón.
Baseline del Director: **2182 / 15.096** en `c67d882` (y subiendo — re-fetch).

CIERRE: Bloques A y B cerrados (47→39). Espera el QA del dueño → Bloque C. Buen ritmo.
