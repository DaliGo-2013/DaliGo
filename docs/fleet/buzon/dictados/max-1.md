# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-17 (v61 — C2 EN PRODUCCIÓN: Bloque C COMPLETO. EN PAUSA hasta el QA del dueño → luego Bloque D). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ C2 está EN PRODUCCIÓN (merge `6fa64cd`, doble llave 17-ago) — menú 38 → 36

Suite del Director sobre el árbol mergeado: **2186 verdes / 15.184, CERO rojos** (calcada
al parte). Conté el menú (36), da. Rama borrada. **Bloque C COMPLETO** — la primera
consolidación múltiple de la casa quedó en producción en un solo lote.

Lo que quedó fino en C2 (para la bitácora de los moldes):

1. **Ítem con `view audit` a secas y no canAny** — decisión tuya, declarada, con la
   doctrina bien enunciada: *el menú jamás ofrece un 403*. Esa frase queda como regla
   citable para D y E.
2. **El rastro del QA 15-07 preservado por contexto** (bandeja vs historial): comentario +
   docblock + campanita con el nombre largo. Reemplazar el porqué viejo con el porqué
   nuevo, sin borrar la historia.
3. **El amolde L409 anti verde-engañoso** (negar una cadena que ya no existe no vigila
   nada) — cazado a la primera, con la bitácora 29-07 citada. Y verificaste que
   `AprobacionBandejaTest` sigue verde POR LA SUPERFICIE CORRECTA, no por accidente.
4. **Mutación DOBLE** — las dos mitades del candado discriminan por separado. El molde
   para las consolidaciones múltiples que vienen (E es 4→1).

## ⏸️ EN PAUSA — el Bloque D espera el QA del dueño del Bloque C

El dueño va a hacer el QA del Bloque C completo en el celular:
- Administración con 3 ítems (Usuarios, Sucursales, Registro del sistema).
- Usuarios con pestañas «Usuarios · Roles» (la de Roles solo para quien porta
  `manage roles`).
- El Registro con sus tres pestañas en una fila (`grid-cols-3`) en pantalla chica.
- La bandeja «Aprobaciones» intacta con su badge; la campanita con sus 4 links.
- El Inicio con UNA card «Registro del sistema», sin las de Notificaciones/Aprobaciones
  ni la de Roles.

Cuando dé el visto bueno, llega el v62 con el **Bloque D · Kardex→Producción**. Adelanto
para el radar (NO arranques): es **ex-huérfana** (candado duro además del mini-candado),
entra al patrón `activo` del ítem Producción — un lote corto. Y tras D viene **E**, el
cierre: Configuración de producción 4→1, donde se paga la deuda del `<x-tab-nav>` a 4
pestañas (`grid-cols-4` no existe todavía: hoy el componente resuelve 3 ? cols-3 : cols-2
y con 4 caería a 2 columnas sin avisar).

## Estado
- Marcador: **47 → 36 en diez lotes** + 2 vive-solos con evidencia. Mapa final: 32.
- Max-2 en pausa (v24). Marcos activo en visita pública/agenda.
- Baseline del Director: **2186/15.184** en `6fa64cd`.

CIERRE: sin acción. Bloque C completo — récord de la casa: 3 lotes en un día con cero
rojos propios. Espera el QA del dueño.
