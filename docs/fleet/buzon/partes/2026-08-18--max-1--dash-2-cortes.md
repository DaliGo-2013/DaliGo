# Parte de Max-1 — 2026-08-18 · Dictado v69, DASH-2 HECHO: los cortes del taller son configurables y el $d7 bicéfalo está desacoplado

> Forjador A, stream 1 · rama `feature/param-dash-2-cortes` (commit `94ef797`) —
> **espera doble llave**. DASH-3 NO arrancado: llega como v70.

## El número

| | |
|---|---|
| Parámetros nuevos | **2 claves nivel 1**: `dashboard_corte_taller_reciente` (default 7, rango 2-60) y `dashboard_corte_taller_antiguo` (default 30, rango 7-180) — par ORDENADO con validación cruzada |
| El desacople (#4) | **hecho PRIMERO**, como dictaba: la «última semana» del flujo quedó con variable propia en 7 FIJO + comentario nivel 3 con el veredicto del dueño |
| Suite | baseline main `81f5cca` (worktree aislado): **2200 verdes / 15.369, CERO rojos** (la referencia era 15.358 en `bcbfb00` — el bloque E-PARAM nuevo en RUTA-MAESTRA suma iteraciones al parser de PlanProyectoTest; mismo conteo de tests, recontado) · rama: **2204 verdes / 15.421, CERO rojos** — **delta EXACTO +4 tests** (los candados nuevos) y cero tests existentes con cifra cambiada |
| Bundle | **byte-idéntico** (rótulos = interpolaciones, cero clases nuevas) |

## La forma

1. **El desacople primero**: `$dSemana` propio en el controller — con el `$d7`
   compartido de antes, mover el corte habría arrastrado el flujo semanal en silencio.
   El comentario nivel 3 cita el veredicto («una semana es una semana», dueño 18-ago,
   mapa F0-DASH #4).
2. **Buckets con el clamp de la casa, reforzado**: `max(2, …)` en el reciente y
   `max($corteReciente + 1, …)` en el antiguo — un par invertido que entre a la BD por
   fuera de la UI tampoco puede dejar tramos sin sentido (cinturón y tirantes, doctrina
   DASH-1).
3. **Rótulos derivados con la aritmética exacta**: «0-R días · (R+1)-A · A+» y «llevan
   A+ días» — el `+1` del tramo medio es la MISMA aritmética del bucket
   (`corteReciente + 1` en el Blade), no un número repetido a mano, como pedía el OJO
   del dictado.
4. **Decisión declarada — las keys `d0_7/d8_30/d30` se quedan**: son los nombres
   INTERNOS del array `aging` (el contrato con la vista y con los tests existentes);
   renombrarlas habría sido churn sin valor. El comentario del controller lo deja
   escrito: los números reales derivan de los cortes, las keys son históricas.
5. **La validación cruzada vive en `ConfiguracionController` como mecanismo
   reutilizable** (la decisión que el dictado me delegó): constante `PARES_ORDENADOS`
   (lista de pares menor/mayor) + `validarParOrdenado()` que corre al guardar
   CUALQUIERA de las dos puntas. El mensaje nombra a la otra clave con su valor vigente
   («Debe quedar por debajo de “Dashboard Corte Taller Antiguo” (hoy 30)»). Si la otra
   punta no está sembrada, no hay par que cruzar y rige el rango simple — el próximo
   par ordenado de cualquier módulo es UNA línea en la constante.

## Candados (`ParametrosDashboardTest` +4 → 8, molde DASH-1)

1. **Default idéntico**: BD virgen → tramos 0-7/8-30/30+, «llevan 30+ días», «Última
   semana», y los cortes en 7/30.
2. **Mover el reciente (10) mueve el bucket CON CIFRA y NO la última semana**: una
   orden de 9 días salta al tramo reciente (aging 2/0/0), rótulos «0-10 días · 11-30»,
   y `entradasSemana` sigue en 1 — **el candado del desacople que hoy no existía**: con
   el `$d7` compartido, la orden de 9 días habría entrado a la semana.
3. **Mover el antiguo (45) ídem por su lado**: 35 y 40 bajan al tramo medio, solo la de
   50 sigue antigua; «de 8-45 · 45+ · llevan 45+ días»; el reciente intacto.
4. **Validación cruzada por las dos puntas**: reciente=30 (igual) y 31 (invertido)
   rechazados con el mensaje que nombra al antiguo; antiguo=7 rechazado nombrando al
   reciente; el par sano (10/60) pasa y persiste.

**Mutación dictada, verificada**: `CORTE_TALLER_RECIENTE_DEFAULT` 7→9 → **rojo exacto
solo el candado de default** (7/8 — los demás siembran la clave) → restaurar
(`git checkout --` + grep del marcador) → 8/8 verde (68 aserciones).

## Regla de oro

Batería dirigida: **46 verdes / 672 aserciones** — `DashboardTest` completo **sin tocar
una línea** (el test viejo del taller, con BD virgen, sigue asertando 0-7/8-30/30+ y
pasa por el fallback: la prueba viva). Cero amoldes en todo el lote.

## Para el radar del Director

- QA del dueño: en Configuración, grupo dashboard con 4 claves; subir el corte reciente
  a 10 → la barra del taller re-tramifica y los rótulos dicen «0-10 · 11-30»; intentar
  reciente=30 → la UI lo rechaza nombrando al otro corte; la «Última semana» no se
  mueve nunca.
- DASH-3 (espero v70): la desc de la card Sucursales derivando de la tabla — el radar
  del v69 ya trae los detalles (composer vs render, fallback con tabla vacía, candado
  de la sucursal nueva).

## Fuera de alcance (declarado)

DASH-3 (por dictado) · los 4 nivel-3 confirmados del mapa · territorio de Marcos y
Max-2.
