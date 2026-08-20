# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-20 (v77.1 — OPE-1 verificado: 99%
> impecable, UN gemelo sin derivar. REBOTE QUIRÚRGICO: el info-tip del panel).
> Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Verificación del Director sobre `feature/param-ope-1-ventanas` (434a31e)

Trial merge limpio; spot-checks TODOS verdes: seeder 7/30/30 TIPO_INTEGER ·
RANGOS 2-31/7-90 con el tope 92 comentado · el ±1 del `rango()` documentado en
el código · request-gana-a-la-clave · constantes con el porqué · rótulo grande
de las 3 vistas derivando · contra-evidencia de las claves separadas bien
argumentada (duplicación de VALOR, no concepto — aceptada) · delta 100%
atribuido con los +24 de los candados-iteradores explicados.

## 🔧 REBOTE (S, una línea + un assert) — el tip del panel miente al mover la perilla

`resources/views/admin/produccion/index.blade.php:97` — el `<x-info-tip>` de
«Producción por periodo» dice **«(por defecto, últimos 7)»** con el 7 EN PROSA.
El rótulo grande ya deriva (`'Últimos ' . $diasPanel . ' días'`) pero este
gemelo quedó a mano: perilla en 14 → la pantalla dice 14 y la ayudita jura 7.
Doctrina DASH-2 (la aritmética exacta, no números a mano).

1. **Derivar**: `(por defecto, últimos {{ $diasPanel }})` — mismo texto, cifra
   viva.
2. **Candado**: el test `mover_la_ventana_del_panel...` (o un assert nuevo en
   él) exige que el TIP también diga la cifra movida y deje de decir 7 — el
   mismo patrón tuyo de asserts-a-la-pantalla.
3. **Mismo lote**: commit encima en TU rama `feature/param-ope-1-ventanas`.
   Re-parte CORTO (delta del delta: +0 tests si extiendes el assert, o +1 si
   lo separas — decláralo).

## OJO — main se movió: MSG-3 de Max-2 mergeado (`1d7ad3e`, +6/+20)

Tu rama nace de `94934cf`; el temido conflicto del `ConfiguracionSeeder` NO
existe (MSG-3 no lo toca — tu advertencia era del MSG-1 ya mergeado). Verifiqué
tu rama sobre main+MSG-3: merge limpio. Para el re-parte: re-ancla a main
fresco (merge de main a tu rama o rebase, tu criterio declarado) y suite
COMPLETA sobre el árbol re-anclado — baseline nuevo: **2244 / 15.594** en
`1d7ad3e`. Esperado con tu lote: ~2251 / ~15.719+ (recuenta exacto tú).

## 📡 Radar OPE-2 y OPE-3 (NO arranques — sin cambios desde v77)
- **OPE-2**: `produccion_motivos_parada` + `produccion_motivos_planificados`
  (LISTAS_SIMPLES ×2) con el PAR planificados ⊆ motivos (4º hermano
  declarativo) + candado OEE-histórico-intacto + `produccion_procedencias_preforma`.
- **OPE-3**: `config/produccion.php` con los patrones %preforma%/%dañada% +
  higiene (max:100000 ×6, 92 ×2, POR_PAGINA ×2).

## Estado
Max-2: GO MSG-4 (menú 32→33 — NO toca tu territorio; MenuPrincipal es de él
este ciclo, tú no lo tocas en el rebote). Marcos activo. Trello espejando.

CIERRE: rebote de una línea — el molde funcionó, solo se escapó un gemelo de
prosa. Re-parte y va la doble llave.
