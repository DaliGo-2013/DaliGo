# Parte de Max-1 — 2026-08-19 · Dictado v73, COM-1 HECHO: las dos listas del negocio en manos del dueño

> Forjador A, stream 1 · rama `feature/param-com-1-listas` (commit `85fbc406`) —
> **espera doble llave**. COM-2 (higiene) NO arrancado: llega como v74.
> ⚠️ Coordinación: este lote toca `ConfiguracionSeeder` igual que MSG-1 de Max-2 — el
> Director secuencia; el que llegue segundo se re-mergea sobre fresco (I-08).

## El número

| | |
|---|---|
| Parámetros nuevos | **2 claves TIPO_JSON** (grupo `comercial`): `clientes_segmentos` (default los 3 históricos) y `catalogo_categorias_sugeridas` (default «Repuestos industriales») |
| Regla de seguridad dictada | **hecha con dientes**: quitar un segmento con clientes asignados se rechaza nombrando segmento y cifra real («No puedes quitar “retail”: 3 cliente(s) lo tienen asignado») |
| Suite | baseline main `22ba70b3` (worktree aislado): **2208 verdes / 15.430, CERO rojos** (recontada como pedía el dictado — main solo se movió con docs) · rama: **2214 verdes / 15.470, CERO rojos** — **delta EXACTO +6 tests** (los candados nuevos) y cero tests existentes con cifra cambiada |
| Bundle | **byte-idéntico** (la vista nueva usa clases existentes) |

## La decisión de UX (el criterio que el dictado me delegó)

El textarea JSON crudo de la UI **no era digno del dueño** para una lista simple
(corchetes, comillas, sintaxis de programador). Quedó el modo **«una por línea»**:
las claves enumeradas en `ConfiguracionController::LISTAS_SIMPLES` se editan como
texto plano (una opción por línea, hint incluido) y el controller normaliza y guarda
el JSON por dentro. Es el tercer mecanismo declarativo por clave de la casa, hermano
de `RANGOS` y `PARES_ORDENADOS` — sumar una clave de lista futura es una línea
(`feriados_chile` es candidata natural, NO tocada: fuera de alcance). Las claves JSON
de OBJETO (plantillas) siguen con su textarea técnico.

## La forma

1. **`Configuracion::getLista()`** nace como el primo de `get()` para listas: clamp
   del consumidor (solo strings, trim, sin vacíos, dedup case-insensitive, lista
   vacía → default) — una clave rota por fuera de la UI no tumba ningún selector.
2. **`Cliente::segmentos()`** deriva de la clave; la constante `SEGMENTOS` queda como
   default histórico comentado. Los 3 consumidores del controller (filtro, validación,
   formularios) pasan al método — eran fuente única, el cambio fue solo el origen.
   `ClienteFactory` usa la constante: cero cambio (verificado).
3. **El duplicado del placeholder muere**: `formData()` entrega `categoriaEjemplo`
   (primera sugerida) y el «Ej. …» del corrector masivo deriva.
4. **`validarSegmentosEnUso()`** — la validación por clave CON LÓGICA (el hermano con
   código de RANGOS/PARES_ORDENADOS): al guardar `clientes_segmentos`, los segmentos
   removidos (diff case-insensitive contra la lista vigente) con clientes asignados
   rechazan el guardado con la cuenta real de `Cliente::where`. Agregar es libre;
   quitar sin clientes también.
5. **Normalización doble** (cinturón y tirantes): la UI limpia al guardar (trim,
   vacíos, duplicados, tope de 50 elementos —propuesto—, 191 por elemento) y
   `getLista()` limpia al leer.
6. **Seeder**: 2 claves con ayuda en español (la de segmentos explica la regla de
   quitar). Idempotente; el deploy las siembra solo.

## Candados (`ParametrosComercialTest`, 6 — molde por módulo)

1. **Default idéntico** con BD virgen: los 3 segmentos en el filtro, «horeca»
   rechazado, placeholder histórico.
2. **Agregar «horeca» mueve selector, filtro y validación SIN tocar código** — el
   candado estrella: el cliente «Casino Z» se crea con el segmento nuevo.
3. **Quitar con clientes → rechazo con nombre y cifra** (y quitar sin clientes pasa).
4. **El placeholder deriva** (primera sugerida cambiada → el «Ej. …» cambia).
5. **Lista rota por fuera de la UI** (duplicados con mayúsculas, espacios, vacíos, y
   lista vacía) → selector sano por el clamp.
6. **La UI una-por-línea** normaliza el ruido y guarda el JSON bien; vaciarla del todo
   se rechaza.

**Mutación dictada, verificada**: romper la derivación (volver a la constante) → **2
rojos exactos** (el candado estrella Y el de quitar-con-clientes, que también depende
de la lista viva) → restaurar (`git checkout --` + grep del marcador) → 6/6 verde.

## Regla de oro

Batería dirigida: **116 verdes / 871 aserciones** — ParametrosDashboard completo (el
ConfiguracionController compartido quedó sano), Cliente/Producto/CategoríaInterna/
ListaPrecio/Configuracion/SeedLongitud **sin tocar una línea**. Cero amoldes.

## Para el radar del Director

- QA del dueño: en Configuración, grupo **comercial** con las 2 claves editables una
  por línea; agregar «horeca» → aparece al tiro en la ficha del cliente; intentar
  quitar un segmento en uso → el mensaje con la cifra; el corrector del catálogo con
  su «Ej. …» siguiendo a la primera sugerida.
- COM-2 (espero v74): los 4 duplicados a constante única, delta 0 exacto.

## Fuera de alcance (declarado)

COM-2 (v74) · `feriados_chile` al modo lista (una línea futura) · los 7 nivel-3
confirmados · territorio de Max-2 (MSG-1) y Marcos.
