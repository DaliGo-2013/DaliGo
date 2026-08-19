# Parte de Max-1 — 2026-08-18 · Dictado v70, DASH-3 HECHO: la card Sucursales deja de mentir sola — módulo Dashboard COMPLETO

> Forjador A, stream 1 · rama `feature/param-dash-3-card-sucursales` (commit `ecc0361`)
> — **espera doble llave**. Con este merge, el mapa F0-DASH queda SALDADO: 4 nivel 1
> ejecutados, 4 nivel 3 con porqué, 6 cross anotados. Comercial (v71) NO arrancado.

## El número

| | |
|---|---|
| El hallazgo | #5 del mapa (el estrella): la desc de la card Sucursales deriva de la tabla — **fuente viva, sin clave de configuración** (nivel 1 por derivación, no por perilla, como dictó el punto 4) |
| Regla de oro | con el seeder de siempre, la card dice **byte a byte** «Mirador, Coquimbo, Abate Molina, Buzeta» — candado propio |
| Suite | baseline main `9312ae7` (worktree aislado): **2204 verdes / 15.421, CERO rojos** (= la referencia del Director en `0c2bcad`) · rama: **2208 verdes / 15.430, CERO rojos** — **delta EXACTO +4 tests** (los candados nuevos) y cero tests existentes con cifra cambiada |
| Bundle | **byte-idéntico** (la desc es dato, cero clases) |

## ⚠️ Contra-evidencia declarada: el orden es por id, NO el de la pantalla

El dictado ofrecía «alfabético o el orden que la pantalla de Sucursales ya use», y la
pantalla usa `orderBy('nombre')` — pero alfabético da «Abate Molina, Buzeta, Coquimbo,
Mirador», que ROMPE el candado byte-a-byte del propio dictado (5.1, «la regla de oro de
este lote»). El único orden que reproduce el string vigente es **id ASC** (el orden del
seeder: la central primero, después por antigüedad de apertura). La regla de oro mandó:
`orderBy('id')`, comentado en el controller. Si el dueño prefiere alfabético, es una
línea + amolde del candado — decisión suya en el QA.

## La forma

1. **La derivación vive donde el dashboard arma las cards** (el map del zócalo en
   `DashboardController`), gateada por `manage sucursales` — cero queries para un
   soplador. `AccesosDashboard` sigue constante: SOLO la desc de esa card se resuelve
   al render, como dictó el punto 2.
2. **Una sola fuente del fallback**: la constante de `AccesosDashboard` pasó a decir
   «Plazos y datos por sucursal» (el texto dictado) con el comentario de su rol — con
   tabla vacía la derivación devuelve string vacío y el map cae a la constante sin
   rama extra ni error.
3. Activas por la columna real (`activa`, boolean — la misma que ya filtra
   `scopeRecepcionServicioTecnico`), unidas con «, ».

## Candados (`ParametrosDashboardTest` +4 → 12)

1. **Byte a byte** con el seeder de siempre (la regla de oro del lote).
2. **Sucursal nueva aparece sola**: una 5ª activa («Rancagua») entra a la desc sin
   tocar código — el hallazgo que define el proyecto, ahora vigilado.
3. **Desactivada desaparece**: Buzeta apagada → fuera de la desc (assertDontSee).
4. **Fallback**: tabla vacía → página 200 + «Plazos y datos por sucursal».

**Mutación dictada, verificada — y discriminó de más**: hardcodear la lista de nuevo
puso en rojo NO solo el candado de sucursal-nueva (el que pedía el dictado) sino
también el de desactivada y el de fallback — **3 rojos exactos** → restaurar
(`git checkout --` + grep del marcador) → 12/12 verde (77 aserciones). Los candados
viejos del archivo (DASH-1/2) ni se inmutaron.

## Regla de oro

Batería dirigida: **74 verdes** (ParametrosDashboard 12 + DashboardTest completo +
DashboardColores + SucursalManagement) — cero amoldes, cero cifras viejas cambiadas.
`MenuPrincipalTest::test_cards_del_dashboard_son_subconjunto_del_menu` ni se entera
(key/ruta/permiso de la card intactos — solo la desc deriva).

## Para el radar del Director — el QA del módulo COMPLETO

Con DASH-3 en producción, el Dashboard queda con: **4 perillas** en Configuración
(grupo dashboard: serie, merma, y los dos cortes con su validación cruzada), **1 card
viva** (Sucursales), y el Inicio **idéntico a como se veía antes del proyecto** sin
tocar nada — esa es la prueba reina del QA. El extra sabroso: crear una sucursal de
prueba y verla aparecer en la card al tiro (y borrarla/apagarla después).

Espero el v71 (auditoría de Comercial, fase A solo-docs) — no lo arranco solo.

## Fuera de alcance (declarado)

Clave de configuración para la card (dictado: no hay perilla) · los 4 nivel-3 del mapa
(confirmados con porqué) · Comercial (v71) · territorio de Marcos y Max-2.
