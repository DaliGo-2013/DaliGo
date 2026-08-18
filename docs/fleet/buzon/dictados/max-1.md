# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-18 (v68 — veredictos del dueño al mapa F0-DASH: los 4 nivel 1 APROBADOS, los 4 nivel 3 confirmados. GO DASH-1: las dos ventanas simples). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Veredictos del dueño al mapa F0-DASH (18-ago) — mapa de primera

El dueño aprobó **los 4 nivel 1** (#1, #2, #3, #5) y confirmó **los 4 nivel 3** tal
como los propusiste. Quedaron escritos al pie del anexo §5.1. Tu mapa salió entero:
cero hallazgos devueltos, el hallazgo #5 (la card Sucursales) fue el que más le gustó
al Director, y tus dos alertas (el `$d7` bicéfalo y los rótulos gemelos) definieron la
partición de los lotes.

Fase B del Dashboard en 3 lotes: **DASH-1** (#1+#2, este dictado) → **DASH-2** (#3 +
desacople de #4) → **DASH-3** (#5). Uno por dictado, como siempre.

## 🔨 GO — Lote DASH-1: las dos ventanas simples (#1 serie de producción, #2 referencia de merma)

Primer lote de código del proyecto — **fija el molde** que DASH-2/3 y todos los módulos
siguientes van a heredar. Hazlo de manual.

### La forma

1. **Dos claves nuevas** en la tabla `configuracion` (sigue el patrón de las claves
   existentes — mira cómo las declaran/siembran notificaciones/auditoría):
   - `dashboard.dias_serie_produccion` — default **7** (el valor de hoy).
   - `dashboard.dias_referencia_merma` — default **7**.
   Claves SEPARADAS aunque ambas digan 7 — son ventanas distintas (tu propia fila #2).
2. **`DashboardController`**: `:144` (`subDays(6)` → `subDays($dias - 1)`, la serie
   incluye hoy — ojo con el ±1) y `:163-164` leen de `Configuracion::get()` con el
   default como fallback. El default en UNA constante o en la clave, no repetido.
3. **Los rótulos DERIVAN del parámetro** (tu alerta #3 del parte): «Últimos 7 días»
   (`dashboard.blade.php:100`) y «prom. 7 días» (`:89`) se construyen desde el valor
   («Últimos {N} días», «prom. {N} días»). Singular/plural si N=1... N mínimo es 2
   (rango), así que plural fijo está bien — decláralo.
4. **UI de Configuración** (`Admin/ConfiguracionController` + su vista): las dos claves
   con **label y ayuda en español del negocio** («Días de producción en las mini-barras
   del Inicio», «Contra cuántos días previos se compara la merma de hoy») y
   **validación de rango 2-31** (regla del plan: un 0 o un negativo no puede romper la
   operación; 31 = un mes de mirada, tope sano).
5. **Candados** (el molde del proyecto — mínimo dos por parámetro):
   - **Default idéntico**: con la clave ausente en BD, el dashboard rinde EXACTO como
     hoy (serie de 7, referencia de 7, rótulos «7 días») — este candado protege la
     regla de oro.
   - **Mover-el-parámetro-mueve-la-pantalla**: sembrar p.ej. 14 → la serie trae 14
     puntos y el rótulo dice «14» (y la merma NO se mueve si solo moviste la serie —
     independencia de las claves).
   - **Validación de la UI**: 1, 0, -5, 32 y no-numérico se rechazan; 2 y 31 pasan.
   - **Mutación de siempre**: rompe el default en el código (7→9) → el candado de
     default se pone rojo → restaurar → verde. Declárala en el parte.
6. **Cero cambio de comportamiento con BD virgen** — la suite entera debe dar delta 0
   salvo tus tests nuevos. Si CUALQUIER test existente cambia de cifra, algo está mal.

### Verificación (invariante)
Rama `feature/param-dash-1-ventanas` desde main FRESCO. Suite COMPLETA de main fresco
ANTES (baseline Director: 2196/15.292 en `8fb0c5c`; main se movió con docs — recuenta
igual). Batería dirigida: Dashboard completo + Configuracion + lo que toque la UI.
Parte al buzón; espera doble llave. NO arranques DASH-2.

## 📡 Radar DASH-2 (NO arranques — llega como v69)
#3 cortes de antigüedad (claves `dashboard.corte_taller_reciente`/`_antiguo` o similar,
7 y 30) + el **desacople del `$d7`** (#4 se queda en 7 FIJO con su propia variable y
comentario nivel-3 del porqué). Los rótulos «0-7 · 8-30 · 30+» y «llevan 30+ días»
derivan. Validación cruzada: corte reciente < corte antiguo.

## Estado
Max-2 en pausa (v24). Marcos activo. Producción: menú 32, CI verde.

CIERRE: GO DASH-1. El primer fierro del proyecto nuevo — que el molde quede de manual.
