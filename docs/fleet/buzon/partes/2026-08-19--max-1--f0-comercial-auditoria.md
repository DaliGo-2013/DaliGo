# Parte de Max-1 — 2026-08-19 · Dictado v72, F0-COMERCIAL HECHO: mapa de hardcodes del módulo Comercial (solo docs)

> Forjador A, stream 1 · commits de docs directo a main (sin rama, sin suite — lote
> solo-docs, precedente F0-DASH). **Cero código**: los veredictos son del dueño y la
> fase B llega por dictado.

## El mapa en una línea

**9 hallazgos** en el anexo §5.2: **2 propuestos nivel 1** (los segmentos de cliente y
las categorías internas sugeridas — las dos «listas que crecen» del módulo), **0 nivel
2**, **7 nivel 3** (4 con su duplicado marcado igual, regla del dictado) y **2
anotaciones cross** (el id de moneda de Bsale y una config de ST que el módulo solo
aloja). Las 3 semillas del Director quedaron resueltas.

## Las semillas, una por una

1. **`paginate(25)`: son 3, no 2** — la semilla decía ×2; el barrido sumó
   `ListaPrecioController:42`. Unidos en un solo hallazgo (#3) con veredicto 3 y
   criterio: es la convención de densidad de TODA la app — una perilla por módulo
   fragmentaría la UX; si el dueño la quiere mover, que sea UNA clave global, no tres.
   El duplicado se marca igual: unificar a constante compartida paga solo.
2. **`daligo.lista_precios_ventas`: CERO desvíos.** `precioVentaConIva()` es la fuente
   única, respeta la clave, y su fallback al criterio antiguo solo corre en entornos
   sin la clave (documentado en el método). La pantalla de edición muestra todas las
   listas a propósito: espejo informativo, no elección. Nadie elige lista por su cuenta.
3. **Comercial no tiene cotizaciones propias** (grep cero) — la vigencia es de ST, sin
   mezcla, como sospechaba el dictado.

## Lo que más me llamó la atención

1. **Las dos listas-que-crecen del módulo están a un deploy del negocio**:
   `Cliente::SEGMENTOS` (abrir un segmento «horeca» = programador) y
   `PRESETS_CATEGORIA_INTERNA` (la próxima categoría curada del dueño = programador).
   Son los #1 y #2 del mapa y los únicos nivel 1 — el resto del módulo está
   notablemente bien parametrizado ya.
2. **El preset «Repuestos industriales» vive DOS veces**: en la constante del
   controller y retipeado como placeholder en la vista (`productos/index:123`) — el
   duplicado clásico que driftea; si el lote de #2 llega, el placeholder deriva.
3. **El import CSV es la zona más densa en números** (5 MB, lotes de 500, topes del
   esquema ×2, 50 errores mostrados ×3, tokens booleanos) — y TODOS salieron nivel 3
   con porqués sólidos ya escritos en el propio código (límites de PHP del hosting,
   «Out of range» de MySQL, no desactivar productos por un typo). La deuda real ahí no
   es de parametrización sino de DUPLICADOS: 4 hallazgos piden unificar a constante.
4. **Por qué de nuevo cero nivel 2**: las decisiones de despliegue del módulo
   (`lista_precios_ventas`, `categorias_equipo`) YA están en config — este módulo fue
   parametrizado bien desde su construcción; la cacería encontró las esquinas, no
   vigas.

## Método (auditable)

Line-scan completo de los 7 PHP (1.204 líneas) + las 12 vistas, greps de red
(paginate/take/limit, literales de negocio en vistas, desvíos de lista, roles
repetidos), y cada `file:line` verificado sobre `1edbc8ec` (main fresco de hoy) antes
de escribirse. Los roles de cartera (`['vendedor','jefe_ventas']`) se distinguieron de
las `ROLES_AVISO_*` de ST/Agenda (otro concepto: avisos, no cartera) — no son
duplicado.

## Para el radar del Director

- Mapa listo para veredictos, fila por fila. Los 2 nivel-1 son esfuerzo S/M; los 4
  duplicados nivel-3 podrían ir juntos en un mini-lote de higiene si el dueño quiere
  (unificar constantes, cero cambio de conducta).
- Nota de flota: barrido 100% read-only — cero contacto con `Notificacion::EVENTOS` ni
  `ConfiguracionSeeder` (territorio MSG-1 de Max-2).
- Siguiente del orden: Operación (espero dictado, no lo arranco solo).

## Fuera de alcance (declarado)

Código (fase B) · módulos ajenos (cross anotados: infra Bsale y ST) · RUTA-MAESTRA y
Trello (del Director) · territorio de Max-2 (MSG-1) y Marcos.
