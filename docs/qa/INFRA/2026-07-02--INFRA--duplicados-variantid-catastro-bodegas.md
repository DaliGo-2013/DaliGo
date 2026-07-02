# QA · BD: duplicados de bsale_variant_id + catastro real de bodegas (P-S0-08) · 2026-07-02

## 1. Prompt enviado

> Solo lectura en phpMyAdmin (BD `impdali_daligo`): (1) query de duplicados de `bsale_variant_id` en
> `productos`, (2) `SELECT id, nombre, bsale_office_id FROM bodegas ORDER BY nombre` + export CSV o
> tabla pegada, (3) total de filas de `bodegas`.

## 2. Respuesta recibida

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | Consulta de duplicados de `bsale_variant_id` en `productos` | OK | `SELECT bsale_variant_id, COUNT(*) AS n FROM productos WHERE bsale_variant_id IS NOT NULL GROUP BY bsale_variant_id HAVING COUNT(*) > 1;` → **0 filas devueltas** ("MySQL ha devuelto un conjunto de valores vacío", 0.0010 s). Resultado esperado/bueno: no hay variantes duplicadas. |
| 2 | Consulta `SELECT id, nombre, bsale_office_id FROM bodegas ORDER BY nombre;` + export | OK (con observación sobre el CSV) | **16 filas devueltas.** Tabla completa (id \| nombre \| bsale_office_id) abajo. No se descargó el archivo CSV: la descarga requiere tu permiso explícito; pegué la tabla completa (alternativa que el propio paso permite). |
| 3 | Total de filas de `bodegas` | OK | **16 filas** (confirmado: "Mostrando filas 0 - 15, total de 16" y en el listado de tablas). |

Tabla completa del Paso 2 (formato `id | nombre | bsale_office_id`):

```
id | nombre                  | bsale_office_id
1  | ABATE MOLINA            | 5
2  | ABATE PRUEBA            | 2
3  | BODEGA MERMAS           | 16
4  | BODEGA SANTA ROSA       | 10
5  | BODEGA SERVICIO TECNICO | 14
6  | BUZETA                  | 7
7  | CERTIFICACIONES         | 13
8  | CONCEPCIÓN              | 8
9  | CONTENEDORES            | 15
10 | COQUIMBO                | 6
11 | COQUIMBO PRUEBA         | 3
12 | MIRADOR                 | 4
13 | RESERVA SUCURSALES      | 11
14 | SERAFIN ZAMORA          | 9
15 | SERVICIO TECNICO        | 1
16 | VIÑA DEL MAR            | 12
```

RESUMEN

VEREDICTO: APROBADO CON OBSERVACIONES

PASOS_OK: 3 de 3

FALLOS: ninguno

OBSERVACIONES:
- Ambas consultas fueron de solo lectura (SELECT); no se modificó ningún dato.
- Paso 2: en lugar de descargar el archivo CSV vía el botón Exportar (una descarga requiere tu confirmación explícita), pegué la tabla completa de las 16 filas, que es la alternativa que el propio paso contempla ("adjúntalo o pega la tabla completa"). Si prefieres el archivo CSV descargado, dímelo y te pido confirmación para la descarga.
- Nota técnica menor (sin impacto en los datos): el editor CodeMirror de phpMyAdmin tuvo problemas de renderizado/foco al pegar por vías programáticas; ejecuté las consultas escribiendo directamente en el editor y pulsando "Continuar". Los resultados mostrados son los reales devueltos por el servidor.
- Observación de datos: la tabla `bodegas` incluye entradas que parecen de prueba ("ABATE PRUEBA", "COQUIMBO PRUEBA"). Solo lo señalo; no modifiqué nada.

## 3. Veredicto

**APROBADO CON OBSERVACIONES.** Dos resultados de alto valor:
1. **0 duplicados** de `bsale_variant_id` → la migración `unique` de cinturón extra queda habilitada (P-M04-05).
2. **Catastro real de bodegas: 16** (la biblia estimaba ~25). Resuelve además el misterio "Santa Rosa":
   ES una bodega de Bsale (`BODEGA SANTA ROSA`, office 10). Hay 2 de prueba, 2 de servicio técnico
   (posible duplicidad), y varias de propósito a confirmar (CERTIFICACIONES, CONTENEDORES, RESERVA
   SUCURSALES, SERAFIN ZAMORA, CONCEPCIÓN, VIÑA DEL MAR).

## 4. Acciones derivadas

- RUTA-MAESTRA: `P-S0-08` marcado `[x]` con esta evidencia; nota en `P-M04-05` (0 duplicados confirmado).
- DECISIONES `D-003`: contexto actualizado con el catastro real y **tabla pre-llenada lista para enviar
  a Luis/Ricardo** (solo les falta corregir/confirmar).
- El paso pendiente P-S0-04 pasa de "obtener el CSV" a "enviar la tabla a Luis/Ricardo".
