# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-20 (v78 — OPE-1 EN PRODUCCIÓN con el rebote
> incluido. GO OPE-2: las dos listas de motivos + procedencias — el 4º hermano
> declarativo). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ OPE-1 está EN PRODUCCIÓN (merge `47513bc`, doble llave 20-ago)

Suite del Director sobre el árbol combinado con MSG-4 de Max-2: **2253 / 15.764,
CERO rojos** — tu 2251 +2 de MSG-4, delta clavado por tercera vez consecutiva.
Rama borrada. Lo que quedó fino del ciclo: el rebote resuelto en una línea + 3
aserciones con la ASIMETRÍA de la mutación declarada (el porqué del assert
nuevo), y el criterio merge-no-rebase argumentado (rama publicada) — ambos
directo al acta.

## 🔨 GO — Lote OPE-2: las listas de motivos y las procedencias (M)

Hallazgos #9 y #13 del mapa §5.3, aprobados por el dueño. Molde COM-1
(LISTAS_SIMPLES) + UNA pieza nueva:

1. **`produccion_motivos_parada`** (grupo `produccion`, TIPO_JSON, LISTAS_SIMPLES
   — UI una-por-línea): default = la lista viva de hoy EXACTA (la que el código
   tenga — decláralo con la fuente). Regla del mapa: AGREGAR motivo es libre;
   QUITAR uno con paradas históricas NO rompe nada (ver candado 4).
2. **`produccion_motivos_planificados`** (ídem): subconjunto que cuenta como
   parada planificada para el OEE.
3. **El PAR planificados ⊆ motivos — el 4º hermano declarativo**: mecanismo
   `PARES_SUBCONJUNTO` (o el nombre que el idioma de la casa mande) en
   `ConfiguracionController`, declarativo como RANGOS/PARES_ORDENADOS/
   LISTAS_SIMPLES: guardar planificados con un motivo que no está en la lista
   madre → rechazo con mensaje en español; editar la madre quitando un motivo
   que vive en planificados → rechazo o ajuste guiado (propón con el código a
   la vista, decláralo). El mecanismo queda para el próximo par (ya hay
   candidatos en el radar de otros módulos).
4. **CANDADO OEE-HISTÓRICO-INTACTO (tu matiz verificado del F0, ahora en test)**:
   la clase de la parada SE PERSISTE en la fila — cambiar las listas HOY no
   reescribe el OEE de AYER. Test explícito: parada histórica con motivo
   retirado de la lista → OEE del período histórico byte-idéntico + la parada
   visible con su motivo legado (ítem retirado no es motivo para el comodín).
5. **`produccion_procedencias_preforma`** (#13): misma LISTAS_SIMPLES, default =
   {saco, caja} (o lo que el código diga — fuente declarada).
6. **Regla de oro + candados molde**: BD virgen = pantallas idénticas · mover
   cada lista mueve SU selector y NO el otro · par-subconjunto por ambos lados ·
   mutación tuya declarada con rojo exacto.

### Verificación (invariante)
Rama `feature/param-ope-2-listas` desde main FRESCO (baseline: **2253 / 15.764**
en `1c044df` — recuenta tú; los ±5 de aserciones docs-sensibles ya conocidos).
Suite COMPLETA antes. Batería: Produccion* + Configuracion* + ParametrosOperacion.
OJO ConfiguracionSeeder: MSG-4 NO lo tocó — sin drift esperado. Parte al buzón;
espera doble llave. NO arranques OPE-3.

## 📡 Radar OPE-3 (NO arranques)
`config/produccion.php` con los patrones %preforma%/%dañada% estilo
categorias_equipo (nivel 2 aprobado #3) + higiene (max:100000 ×6 → constante,
92 ×2, POR_PAGINA ×2 adoptado). Cierra el módulo → QA del dueño → Trello a
Terminadas → F0-LOGÍSTICA.

## Estado
Max-2: chat 4/4 CONSTRUIDO (MSG-4 mergeado en el mismo ciclo) — en pausa hasta
QA del dueño en celular. Marcos activo. Trello espejando. Baseline: 2253/15.764
en `1c044df`.

CIERRE: GO OPE-2. El 4º hermano declarativo — la familia crece con molde.
