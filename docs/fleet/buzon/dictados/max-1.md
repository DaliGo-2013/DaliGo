# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-18 (v69 — DASH-1 EN PRODUCCIÓN: el molde de fase B quedó sellado. GO DASH-2: cortes de antigüedad + el desacople del $d7). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ DASH-1 está EN PRODUCCIÓN (merge `bcbfb00`, doble llave 18-ago)

Suite del Director sobre el árbol mergeado: **2200 verdes / 15.358, CERO rojos** —
delta exacto +4, cero cifras viejas cambiadas: la regla de oro verificada viva. Rama
borrada. **El bloque `E-PARAM` nació en RUTA-MAESTRA con este merge** — el proyecto ya
se ve en /plan con pasos de verdad.

Lo que quedó fino (ya es doctrina del proyecto):
1. **El snake_case sobre la letra del dictado** — el idioma de la casa manda; la
   desviación declarada con evidencia (las 15+ claves existentes) es exactamente cómo
   se pisa un dictado.
2. **El candado de independencia CON CIFRA CALCULADA** (el reporte en día -9 que mueve
   el promedio de 10% a 30%) — no solo el rótulo: el cálculo. Ese es el estándar.
3. **El mecanismo `RANGOS`** — reutilizable, DASH-2 solo agrega entradas.
4. **El clamp `max(2,…)`** — la UI valida, pero un valor roto por fuera de la UI
   tampoco puede romper la pantalla. Cinturón y tirantes.

## 🔨 GO — Lote DASH-2: cortes de antigüedad del taller (#3) + desacople del $d7 (#4)

El lote M del módulo — el que tiene la trampa que tú mismo cazaste en el mapa.

### La forma

1. **PRIMERO el desacople** (tu alerta del parte F0): la «última semana» del flujo del
   taller (`DashboardController` :201-202) deja de reusar `$d7` y recibe **variable
   propia con 7 FIJO** + comentario nivel 3 del porqué («una semana es una semana —
   veredicto del dueño 18-ago, mapa F0-DASH #4»). Sin este paso, mover el corte
   arrastraría el flujo semanal en silencio.
2. **Dos claves nuevas** (patrón DASH-1, snake_case):
   - `dashboard_corte_taller_reciente` — default **7**.
   - `dashboard_corte_taller_antiguo` — default **30**.
   Seeder idempotente con ayuda en español («Dónde termina el tramo reciente de los
   equipos activos del taller (días)», «Desde cuántos días un equipo activo cuenta como
   antiguo»).
3. **Buckets y rótulos derivados**: los tramos (:185-192) leen de las claves (con el
   clamp de la casa); los textos «0-7 días · 8-30 · 30+» y «llevan 30+ días»
   (`dashboard.blade.php` :113, :124) se construyen desde los valores. OJO al ±1 del
   tramo del medio: hoy es 8-30 porque parte en corte_reciente+1 — que el rótulo derive
   esa aritmética exacta, no la repita a mano.
4. **UI**: dos entradas nuevas en `RANGOS` (reciente [2,60], antiguo [7,180] — o los
   rangos que el código pida con sentido) + **la validación cruzada nueva:
   reciente < antiguo** — al guardar CUALQUIERA de las dos, si el par queda invertido
   o igual, se rechaza con mensaje en español que nombre a la otra clave. Esta lógica
   es nueva (RANGOS es por-clave); decide dónde vive y decláralo.
5. **Candados** (molde DASH-1 + los propios):
   - Default idéntico: BD virgen → tramos 0-7/8-30/30+, rótulos de hoy, y la «última
     semana» sigue en 7.
   - Mover reciente (p.ej. 10) → tramos 0-10/11-30/30+ CON CIFRA (un equipo de 9 días
     cambia de bucket) + rótulos derivados + **la última semana NO se mueve** (el
     candado del desacople — el que faltaba en el código de hoy).
   - Mover antiguo (p.ej. 45) → ídem por su lado.
   - Validación cruzada: reciente=30/antiguo=30 rechazado; reciente=31/antiguo=30
     rechazado; el mensaje nombra el conflicto.
   - Mutación: default 7→9 del corte → rojo exacto el candado de default → restaurar →
     verde.
6. **Regla de oro**: cero tests existentes con cifra cambiada. El `assertSee` viejo de
   los rótulos del aging (si existe en DashboardTest) debe seguir verde SIN tocarse con
   BD virgen — si hay que amoldar algo, se declara con el porqué.

### Verificación (invariante)
Rama `feature/param-dash-2-cortes` desde main FRESCO (`bcbfb00` o posterior). Suite
COMPLETA de main fresco ANTES (baseline: **2200/15.358**). Batería dirigida:
ParametrosDashboard + Dashboard completo + ConfiguracionManagement. Parte al buzón;
espera doble llave. NO arranques DASH-3.

## 📡 Radar DASH-3 (cierre del módulo, llega como v70)
#5: la desc de la card Sucursales (`AccesosDashboard:43`) deriva de la tabla
`sucursales` (activas, orden estable). Detalles que te esperan: `AccesosDashboard` es
estático/constante hoy — derivar de BD implica query en el composer del dashboard o en
el punto de render (decidir con el código a la vista, declarar); candado «sucursal
nueva activa aparece en la desc sin tocar código»; cuidado con entornos de test sin
seeder (fallback genérico «Plazos y datos por sucursal» si la tabla está vacía).
Tras DASH-3: QA del dueño del módulo Dashboard completo → auditoría de Comercial (v71).

## Estado
Max-2 en pausa (v24). Marcos activo. Baseline: **2200/15.358** en `bcbfb00`.

CIERRE: GO DASH-2. El desacople primero, después las perillas. Fierro.
