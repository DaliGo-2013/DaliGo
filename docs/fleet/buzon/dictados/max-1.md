# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-19 (v76 — QA del dueño del módulo Comercial ✅: SEGUNDO MÓDULO SALDADO. GO F0-OPERACIÓN: auditoría del apartado Operación, SOLO DOCS). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ QA del dueño del módulo Comercial (19-ago) — segundo módulo SALDADO

El dueño verificó las 2 listas editables y el rechazo con cifra. Dos módulos completos
en dos días de proyecto: Dashboard (4 perillas + card viva) y Comercial (2 listas +
higiene). El molde vuela.

## 🔍 GO — F0-OPERACIÓN: auditoría del apartado Operación (SOLO DOCS, cero código)

El tercero del orden y el más denso hasta ahora. Barre el módulo Operación completo:
- **Producción**: panel + reportes + aprobación + OEE + paradas + SIC/kaizen + notas
  (todo M11) + el kardex (hija del panel desde D1).
- **Inventario** (M04): stock por producto/bodega, bodegas paramétricas, bajas con
  orden de traslado.
- **Configuración de producción** (el hub 4-en-1 de E1): Máquinas · Tipos de botellón ·
  Recetas · Moldes.

Semillas del Director (confírmalas y complétalas):
1. **El cross del mapa DASH que te espera**: `ProduccionReporte::pendientes()` (estado
   ENVIADO) — la definición de «reportes por aprobar». ¿Hay más catálogos de estado
   como ese que sean listas-que-crecen vs claves de máquina nivel 3?
2. **`config/servicio_tecnico.categorias_equipo`** — nivel 2 EXISTENTE de ST que
   Operación consume vía `Producto::scopeEquipoTaller`. El mapa debe DECIR de quién es
   cada cosa (dueño ST, consumidor Operación) — sin proponer mudanzas si no hay drift.
3. **Umbrales de M11**: el semáforo de moldes, el OEE, la racha del SIC, los 45 días
   del historial del soplador — territorio construido por Max-2; audítalo con respeto
   de autor (los porqués pueden estar en PLAN-M11 y sus partes) pero con tu vara: ¿es
   decisión del negocio (nivel 1/2) o aritmética del motor (nivel 3)?
4. **Bodegas paramétricas (M04)**: ya son BD+UI — verifica que no queden hardcodes
   RESIDUALES alrededor (grillas de sync, umbrales de alertas si existen).
5. OJO alcance: «Mi producción» (la pantalla del soplador) es OTRO apartado del orden
   del dueño — si encuentras hardcodes ahí, se ANOTAN cross, no se auditan aquí.

Formato del mapa invariante (plan §2), entregable: **anexo §5.3** + parte al buzón con
resumen por niveles + lo que más te llamó la atención. **Cero código.**

### Arranque operativo
Re-fetch de main FRESCO (baseline: **2238 / 15.574** en `5bf39df` — entraron COM-2 y
MSG-2 hoy; Marcos sigue activo). Barrido read-only: cero riesgo. Max-2 forja MSG-3
(poll del chat) — territorio disjunto.

## Estado
Trello espejando (Comercial cruzó a Terminadas con el QA; Operación en En Curso).
Marcos activo. Baseline: 2238/15.574 en `5bf39df`.

CIERRE: GO F0-OPERACIÓN. El módulo más denso del proyecto hasta ahora — mismo mapa,
misma vara, y respeto de autor en M11. Fierro.
