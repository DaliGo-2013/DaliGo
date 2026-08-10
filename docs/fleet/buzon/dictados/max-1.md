# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-08 (v41 — P-M11-10 EN PRODUCCIÓN; espera corta hasta que landee el stream B). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ P-M11-10 está EN PRODUCCIÓN (merge `3cc708f`, doble llave 08-ago)

Verificación del Director sobre el árbol unión: **suite 1731 verdes / 12.479 aserciones,
cero rojos** — cuadre exacto (tus 1729 + 2 del simulador de Marcos posteriores a tu
base). Bundle: superset 0 pérdidas, tu `lg:grid-cols-5` en uso. Spot-checks 7/7. Deploy
y Tests de CI verdes. Rama borrada tras ancestría.

**El pendiente nº1 histórico del tracker está construido y desplegado.** Tres cosas de
tu parte que quedan como estándar de la casa: el fallback que reproduce el comportamiento
histórico BYTE A BYTE (14 tests viejos verdes sin tocarlos = la prueba de que no hay
rama legacy paralela), la divergencia preexistente del preview cazada y fijada de pasada,
y la decisión asignación-gana-en-preforma con la alternativa nombrada. La frontera de
streams se respetó al milímetro — cero roce con Max-2.

## ⏸️ ESPERA CORTA — F2 (P-M11-11 OEE) necesita el stream B

Tu OEE consume las paradas-con-duración que Max-2 está forjando AHORA (P-M11-20). El GO
de P-M11-11 sale cuando su lote pase la doble llave — no antes, porque construirías
contra un esquema que aún puede moverse.

Si abres sesión y este dictado sigue en v41: revisa el buzón por si hay v42, y si no lo
hay, cierra sesión sin gastar ventana.

## Recordatorios
Baseline HOY: **1731 / 12.479** en main `3cc708f`. Las reglas de siempre siguen.

CIERRE: nada pendiente de tu lado. El kardex por fin sabe lo que consume la planta.
