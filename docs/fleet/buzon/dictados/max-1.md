# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-19 (v77 — veredictos del dueño al mapa F0-OPERACIÓN: los 4 aprobados + 9 confirmados + higiene. GO OPE-1: las ventanas del panel y los informes). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Veredictos del dueño al mapa §5.3 (19-ago) — tercer mapa que sale entero

Aprobados los 3 nivel 1 (#1, #9, #13), el nivel 2 (#3) y la higiene; los 9 nivel 3
confirmados con tus porqués. Al pie del anexo. El titular del parte (M11 nació
parametrizado — D-003 pagándose sola) va derecho al acta del módulo y al Trello del
dueño en su lenguaje.

Fase B de Operación = 3 lotes: **OPE-1** (este dictado) → **OPE-2** (las dos listas) →
**OPE-3** (config de preforma + higiene).

## 🔨 GO — Lote OPE-1: las ventanas del panel del jefe y de los informes (M)

El primo del DASH-1 que el dueño ya aprobó — molde exacto:

1. **Tres claves nuevas** (grupo `produccion`, snake_case, seeder idempotente con ayuda
   en español):
   - `produccion_dias_panel` — default **7** (`ProduccionController:125`, `$ventana=6`
     +hoy: ojo al ±1 como DASH-1).
   - `produccion_dias_informe_maquina` — default **30** (`:306`, `rango($request, 29)`).
   - `produccion_dias_informe_tipo` — default **30** (`:351`, ídem).
   Claves SEPARADAS aunque los informes digan lo mismo hoy (doctrina DASH-1: ventanas
   distintas, perillas distintas). Si al abrir el código ves que los 2 informes
   comparten UN concepto real (mismo `rango()` con el mismo sentido de negocio),
   contra-evidencia en el parte ANTES de forjar distinto — una clave para ambos sería
   defendible; decláralo.
2. **`RANGOS`**: las 3 entradas (2-31 el panel; 7-90 los informes — o los rangos que el
   código pida con sentido, declarados).
3. **Rótulos derivados** donde existan textos gemelos en las vistas (tu mapa los marca)
   — la aritmética exacta, no números a mano (doctrina DASH-2).
4. **OJO al `rango()`**: si el helper acota o interpreta el request (el usuario puede
   pedir otro rango por URL), la clave es el DEFAULT del rango, no un tope — que el
   candado distinga default-configurable vs rango-pedido-por-el-usuario.
5. **Candados molde DASH** (default idéntico byte a byte · mover cada clave mueve SU
   pantalla y rótulo y NO las otras dos · rangos por ambos bordes · mutación 7→9 o
   30→45 con rojo exacto → restaurar → verde).
6. **Regla de oro**: cero tests existentes con cifra cambiada; BD virgen = pantallas
   idénticas.

### Verificación (invariante)
Rama `feature/param-ope-1-ventanas` desde main FRESCO (baseline: **2238 / 15.574** en
`5bf39df` + docs después — recuenta; Max-2 puede mergear MSG-3 antes que tu parte).
Suite COMPLETA antes. Batería: Produccion* completo + ParametrosDashboard (el
ConfiguracionController compartido) + ConfiguracionManagement/SeedLongitud. Parte al
buzón; espera doble llave. NO arranques OPE-2.

## 📡 Radar OPE-2 y OPE-3 (NO arranques)
- **OPE-2**: `produccion_motivos_parada` + `produccion_motivos_planificados`
  (LISTAS_SIMPLES ×2) con la validación del PAR planificados ⊆ motivos (4º hermano
  declarativo: par-subconjunto) + tu matiz verificado (la clase se persiste — OEE
  histórico intacto, candado explícito) + `produccion_procedencias_preforma`.
- **OPE-3**: `config/produccion.php` (o donde el idioma mande) con los patrones de
  preforma/dañada estilo categorias_equipo + higiene (max:100000 ×6 → constante, 92 ×2,
  POR_PAGINA ×2 adoptado).

## Estado
Max-2 forjando MSG-3 (poll del chat). Marcos activo. Trello espejando. Baseline:
2238/15.574 en `5bf39df`.

CIERRE: GO OPE-1. Tercer módulo en fase B — los moldes ya hacen el trabajo pesado.
