# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-20 (v79 — OPE-2 EN PRODUCCIÓN: el 4º
> hermano quedó en la familia. GO OPE-3: config de preforma + higiene — el lote
> que CIERRA el módulo Operación). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ OPE-2 está EN PRODUCCIÓN (merge `576ad95`, doble llave 20-ago)

Suite del Director sobre el árbol combinado con MSG-5: **2291 / 16.002, CERO
rojos** (y re-suite del árbol final con el 4º drift de Marcos: 2299/16.029) —
tu +7/+44+24 clavado de nuevo. Rama borrada. Lo que quedó fino: el RECHAZO
bidireccional argumentado contra el auto-arreglo (una lista que gobierna el
OEE no se «corrige sola»); el candado OEE-histórico en DOS escenarios con el
matiz del Pareto declarado como cosmética; los chips por forma contigua tras
el rojo propio de los gemelos (MOTIVOS_DIFERENCIA/DEFECTO); y el `pwd` primero
de tu incidente de método — directo a la bitácora.

## 🔨 GO — Lote OPE-3: config de preforma + higiene (S/M) — CIERRA Operación

Hallazgo #3 (nivel 2 aprobado) + el mini-lote de higiene confirmado:

1. **`config/produccion.php`** (nivel 2 — deploy, no caliente: el criterio que
   decide qué inventario ES preforma no se mueve en producción sin pensarlo):
   los patrones `%preforma%` / `%dañada%` que hoy viven hardcodeados salen a
   config estilo `servicio_tecnico.categorias_equipo` (el molde nivel-2 de la
   casa). Defaults = los literales de hoy EXACTOS; los consumidores leen
   `config()`; comentario en el archivo con el porqué del nivel 2 (vara de
   daligo.php).
2. **Higiene** (nivel 3 confirmado, delta CERO de conducta):
   - `max:100000` ×6 → constante con nombre (donde el idioma de la casa mande).
   - El `92` ×2 de `rango()` → constante nombrada (el tope de render que OPE-1
     dejó comentado).
   - `POR_PAGINA` ×2 del módulo → adoptar la del `Controller` padre (molde
     COM-2: solo los del módulo Operación, los demás para sus auditorías).
3. **Regla de oro**: BD virgen y config default = conducta byte-idéntica;
   cero tests existentes movidos. Candados: patrón movido a config mueve la
   clasificación (test con config() sobreescrito en runtime) · constantes con
   el mismo valor (mutación de una → rojo exacto) · POR_PAGINA hereda.

### Verificación (invariante)
Rama `feature/param-ope-3-config` desde main FRESCO (baseline: **2299 / 16.029**
en `1a45026` — main lleva MSG-5 y el 4º lote directo de Marcos
[TrabajoRealizadoDosFormas, ST]; recuenta tú). Suite COMPLETA antes. Batería:
Produccion* + Inventario/Kardex si los patrones tocan ahí + Configuracion*.
Parte al buzón; espera doble llave.

## 📡 Después de OPE-3
QA del dueño del módulo Operación COMPLETO (3 ventanas + 3 listas + config
preforma) → card «Paramétricos · Operación» a Terminadas con el titular (M11
nació parametrizado — D-003) → **F0-LOGÍSTICA** (4º módulo del orden).

## Estado
Max-2: chat 5/5 EN PRODUCCIÓN (MSG-5 cerró el hallazgo de viveza del QA — el
caso de las capturas del dueño), en pausa hasta QA final. Marcos: 4 pushes
directos a main hoy (el dueño los acepta; los baselines los absorben — de ahí
tu «recuenta tú»). Trello espejando. Baseline: 2299/16.029 en `1a45026`.

CIERRE: GO OPE-3. Un lote corto y Operación queda saldada — el módulo más
grande, censado y con 3 lotes limpios.
