# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-18 (v70 — DASH-2 EN PRODUCCIÓN. GO DASH-3: la card Sucursales deja de nombrar las sucursales a mano — cierre del módulo Dashboard). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ DASH-2 está EN PRODUCCIÓN (merge `0c2bcad`, doble llave 18-ago)

Suite del Director: **2204 verdes / 15.421, CERO rojos** — delta exacto +4, cero
amoldes. Rama borrada; `P-PAR-DASH-2` marcado en E-PARAM. Tres cosas de este lote ya
son doctrina del proyecto: el desacople ANTES de la perilla, el clamp reforzado del par
(`max($corteReciente + 1, …)`), y `PARES_ORDENADOS` como mecanismo (el próximo par de
cualquier módulo es una línea).

## 🔨 GO — Lote DASH-3: la card Sucursales deriva de la BD (#5) — cierre del módulo

El hallazgo estrella de tu mapa: `AccesosDashboard.php:43` dice «Mirador, Coquimbo,
Abate Molina, Buzeta» a mano. Sucursal nueva → el Inicio miente hasta que un
programador edite el archivo. Se acaba hoy.

### La forma (con las decisiones que el radar v69 te delegó, ahora dictadas)

1. **La desc de la card `sucursales` deriva de la tabla `sucursales`**: nombres de las
   sucursales ACTIVAS (el scope/columna que ya usa el módulo M04 — `en_operacion` o el
   idioma real de la tabla, verifícalo), orden estable (alfabético o el orden que la
   pantalla de Sucursales ya use — consistencia sobre gusto), unidos con «, ».
2. **Dónde vive el query — decisión dictada**: `AccesosDashboard` hoy es
   constante/estático; la derivación va en el punto donde el dashboard ARMA las cards
   (el composer o el método que ya inyecta datos vivos — mira cómo llegan los badges).
   NO conviertas toda la constante en query: SOLO la desc de esa card se resuelve al
   render. Si el código real te pide otra forma, contra-evidencia en el parte ANTES de
   forjar distinto.
3. **Fallback con tabla vacía** (entornos de test sin seeder, BD recién migrada): desc
   genérica **«Plazos y datos por sucursal»** — jamás un join de lista vacía ni un
   error. Candado propio.
4. **Sin clave de configuración**: este hallazgo es nivel 1 por «deriva de fuente
   viva», no por perilla — no hay número que mover. No agregues clave que nadie pidió.
5. **Candados** (molde del proyecto adaptado):
   - **Fuente viva**: con las 4 sucursales sembradas, la desc las nombra igual que hoy
     (byte a byte — la regla de oro de este lote).
   - **Sucursal nueva aparece sola**: crear una 5ª activa → la desc la incluye SIN
     tocar código.
   - **Sucursal desactivada desaparece**: apagar una → la desc la omite.
   - **Fallback**: tabla vacía → «Plazos y datos por sucursal», página 200.
   - **Mutación**: rompe la derivación (hardcodea de nuevo la lista en tu rama) → el
     candado de sucursal-nueva se pone rojo → restaurar → verde.
6. **Regla de oro**: con el seeder actual (las 4 de siempre), la card se ve IDÉNTICA a
   hoy. Cero tests existentes con cifra cambiada; el candado Dashboard de cards⊆menú ni
   se entera.

### Verificación (invariante)
Rama `feature/param-dash-3-card-sucursales` desde main FRESCO. Suite COMPLETA de main
fresco ANTES (baseline: **2204/15.421** en `0c2bcad`). Batería dirigida: Dashboard +
DashboardColores + ParametrosDashboard + Sucursal*. Parte al buzón; espera doble llave.

## 📡 Después de DASH-3
Módulo Dashboard COMPLETO (mapa F0-DASH saldado: 4 nivel 1 ejecutados, 4 nivel 3 con
porqué, 6 cross anotados) → **QA del dueño del módulo** (las 4 perillas en Configuración
+ la card viva + el Inicio idéntico sin tocar nada) → **v71 abre la auditoría de
Comercial** (fase A, solo docs, mismo formato del mapa).

## Estado
Max-2 en pausa (v24). Marcos activo. Baseline: **2204/15.421** en `0c2bcad`.

CIERRE: GO DASH-3. El hallazgo que define el proyecto — que la pantalla deje de mentir
sola. Fierro.
