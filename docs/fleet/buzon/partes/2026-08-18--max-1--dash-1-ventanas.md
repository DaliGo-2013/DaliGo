# Parte de Max-1 — 2026-08-18 · Dictado v68, DASH-1 HECHO: las dos ventanas del pulso son configurables — el molde de fase B quedó fijado

> Forjador A, stream 1 · rama `feature/param-dash-1-ventanas` (commit `694db68`) —
> **espera doble llave**. Primer lote de código del PLAN-PARAMETRICOS. DASH-2 NO
> arrancado: llega como v69.

## El número

| | |
|---|---|
| Parámetros nuevos | **2 claves nivel 1**: `dashboard_dias_serie_produccion` y `dashboard_dias_referencia_merma` (grupo `dashboard`, default 7 = el valor histórico, rango 2-31 en la UI) |
| Regla de oro | **verificada por candado**: con BD virgen la pantalla rinde idéntico (serie de 7, «prom. 7 días»); **cero tests existentes cambiaron de cifra** |
| Suite | baseline main `2842bf3` (worktree aislado): **2196 verdes / 15.310, CERO rojos** (la referencia del Director era 15.292 en `8fb0c5c` — main se movió con el lote de Marcos; mismo conteo de tests, recontado) · rama: **2200 verdes / 15.358, CERO rojos** — **delta EXACTO +4 tests** (los candados de ParametrosDashboardTest) y cero tests existentes con cifra cambiada |
| Bundle | **byte-idéntico** (los rótulos derivados son interpolaciones, cero clases nuevas) |

## Una decisión declarada: las claves van SIN punto

El dictado nombró las claves `dashboard.dias_serie_produccion` pero ordenó «sigue el
patrón de las claves existentes» — y el patrón de la casa es **snake_case sin puntos**
(las 15+ claves existentes: `notif_reintentos_max`, `devolucion_fotos_min`,
`cotizacion_vigencia_dias`…; el propio seeder convierte `.` → `_` en las plantillas).
Fui con el patrón: `dashboard_dias_serie_produccion` / `dashboard_dias_referencia_merma`,
agrupadas por `grupo = dashboard` (que es como la UI las junta). Si el Director prefiere
el punto literal, renombrar es una línea + seeder — pero rompería el idioma de la casa.

## La forma (de manual, como pedía el dictado)

1. **Seeder**: 2 entradas idempotentes (`firstOrCreate`) con la ayuda en español del
   negocio como `descripcion` (la UI la muestra como hint del campo) — dentro del tope
   de 191 que vigila `ConfiguracionSeedLongitudTest`. El deploy las siembra solo.
2. **Controller**: el default vive en UNA constante por clave
   (`DIAS_SERIE_PRODUCCION_DEFAULT = 7`, `DIAS_REFERENCIA_MERMA_DEFAULT = 7`) y cada
   lectura lleva **clamp inferior** `max(2, …)` — idioma exacto de
   `devolucion_fotos_min`: un valor roto que entre a la BD por fuera de la UI no puede
   dejar la serie vacía. El ±1 de la serie quedó comentado (hoy cuenta como día 1:
   `subDays($dias - 1)`).
3. **Rótulos derivados** (la alerta de los textos gemelos del mapa): «Últimos {N} días»
   y «prom. {N} días» se construyen desde el valor que usó el cálculo — viajan juntos
   en `pulsoProduccion`. **Plural fijo declarado**: el rango parte en 2, N=1 no existe.
4. **UI de Configuración**: mecanismo nuevo y reutilizable —
   `ConfiguracionController::RANGOS` (mapa clave → [min, max]) que se suma a las reglas
   del TIPO_INTEGER solo para las claves que lo declaran. **DASH-2 hereda el mecanismo**:
   sus cortes de antigüedad solo agregan sus entradas al mapa (la validación cruzada
   reciente < antiguo sí será lógica nueva).

## Candados (`ParametrosDashboardTest`, 4 — el molde del proyecto)

1. **Default idéntico**: BD virgen (sin ConfiguracionSeeder a propósito) → serie de 7
   puntos, `diasSerie/diasMerma = 7`, rótulos «Últimos 7 días» y «prom. 7 días 10%».
2. **Mover la serie mueve las barras y su rótulo, y NO toca la merma**: clave en 14 →
   14 puntos, «Últimos 14 días» (+ assertDontSee del rótulo viejo), «prom. 7 días»
   intacto.
3. **Mover la merma mueve su promedio y su rótulo, y NO toca la serie** — con dientes:
   un reporte con merma alta en el día **-9** (fuera de la ventana de 7, dentro de la
   de 10) → el promedio pasa de 10% a 30% **con la cifra calculada**, no solo el
   rótulo; la serie sigue en 7.
4. **La UI valida el rango por los dos lados**: 1, 0, -5, 32 y 'abc' rechazados; 2 y
   31 aceptados y persistidos.

**Mutación dictada, verificada**: default 7→9 en el código → **rojo exacto el candado
de default** (los otros 3 siguen verdes — siembran la clave, no dependen del fallback)
→ restaurar (`git checkout --` + grep del marcador) → 4/4 verde (32 aserciones).

## Batería y verificaciones

- Batería dirigida: **54 verdes** (ParametrosDashboard 4 + DashboardTest completo +
  DashboardColores + ConfiguracionSeedLongitud + ConfiguracionManagement 15). Los tests
  viejos del pulso (`serie count 7`, «prom. 7 días», y el `assertDontSee` del rótulo
  cuando no hay referencia) pasan SIN tocar — la prueba viva de la regla de oro.
- El `assertDontSee('prom. 7 días')` existente (DashboardTest:271) sigue discriminando
  con el rótulo derivado: cuando no hay datos previos el span entero no se pinta.

## Para el radar del Director

- QA del dueño: en Configuración aparece el grupo **dashboard** con las 2 claves y su
  ayuda en español; editar la serie a 14 → el Inicio muestra 14 barritas y «Últimos 14
  días»; intentar 0 o 40 → la UI lo rechaza; volver a 7 → idéntico a hoy.
- DASH-2 (espero v69): el mapa `RANGOS` ya está listo para recibir los cortes de
  antigüedad; queda pendiente ahí la validación cruzada reciente < antiguo y el
  desacople del `$d7` bicéfalo.

## Fuera de alcance (declarado)

DASH-2 y DASH-3 (por dictado) · el resto del mapa F0-DASH · territorio de Marcos y
Max-2.
