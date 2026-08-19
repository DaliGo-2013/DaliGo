# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-19 (v75 — COM-2 EN PRODUCCIÓN: módulo Comercial COMPLETO en código. EN PAUSA hasta el QA del dueño → luego F0-OPERACIÓN). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ COM-2 está EN PRODUCCIÓN (merge `c594182`, doble llave 19-ago)

Suite del Director: **2227 / 15.510 — cifras IDÉNTICAS a la baseline**: el delta cero
perfecto que pedía el dictado, entregado al pie. Greps del corte verificados por mi
cuenta. Rama borrada. La decisión del `Controller::POR_PAGINA` (el padre vacío, sin
desparramarse a los otros 12) — exactamente la vara.

## 🏁 MÓDULO COMERCIAL COMPLETO EN CÓDIGO — segundo módulo del proyecto

F0 (9 hallazgos) → veredictos → COM-1 (2 listas editables) → COM-2 (higiene). En dos
módulos: 6 perillas + 1 card viva + 2 listas del negocio en manos del dueño, cero
cambios de conducta sin perilla movida, cero amoldes.

## ⏸️ EN PAUSA — Operación espera el QA del dueño del módulo Comercial

QA (celular): las 2 listas en Configuración (grupo comercial, una opción por línea) —
agregar un segmento de prueba y verlo en la ficha del cliente al tiro; intentar quitar
uno con clientes y ver el rechazo con la cifra; el resto del módulo idéntico.

Con el visto bueno llega el **v76: F0-OPERACIÓN** (el tercero del orden). Radar para
que lo tengas (NO arranques): Producción + kardex + inventario (M04) + Configuración de
producción (el hub 4-en-1 de E1); el cross del mapa DASH esperándote
(`ProduccionReporte::pendientes()`); y ojo con `config/servicio_tecnico.categorias_equipo`
(nivel 2 existente que Operación consume — el mapa debe DECIR de quién es cada cosa).

## Estado
Max-2: MSG-2 en producción (el chat ya se usa por URL); forja MSG-3 (poll — recuerda
que su ruta `conteo` va antes de `{conversacion}`; territorio disjunto del tuyo).
Marcos activo. Baseline: **2238 / 15.574** en `5bf39df`.

CIERRE: sin acción. Dos módulos completos en dos días — el molde vuela. Espera el QA.
