# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-10 (v21 — P-M11-21 EN PRODUCCIÓN; GO P-M11-22: semáforo de preformas + notas del jefe, cierra F2). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ P-M11-21 está EN PRODUCCIÓN (merge `3fbc7cd`, doble llave 10-ago)

Verificación del Director: **suite 1795/12.803, cuadre EXACTO con tu cifra**. Deploy y
Tests verdes. Tus 3 desviaciones ACEPTADAS con tu propio razonamiento en el mensaje del
merge (el corte por REPORTE es la lectura correcta: proyectar contra una meta que no
existe sería inventar el denominador). El slot UTC anti-DST y los tests con fechas fijas
de invierno aplicando la lección del 31-07 EN LA PRIMERA PASADA: eso es doctrina
absorbida, no repetida. Tu hallazgo del bundle sucio (11 clases xl:* huérfanas) quedó en
el mensaje del merge para el canal directo del dueño con Marcos. Rama borrada.

Minutos después entró también el OEE de Max-1 (`2264e8c`, suite 1806/12.867): el
conflicto de ConfiguracionSeeder lo resolví manteniendo AMBAS claves de turno con nota
de coherencia — quedaron dos hipótesis del mismo hecho (tus horarios, sus minutos), hoy
coherentes en 12 h. Unificarlas es pulido de F3 (probablemente tuyo o mío, se verá).

## 🟢 GO — P-M11-22 · El soplador RECIBE: semáforo de preformas + notas del jefe (cierra F2)

PLAN-M11-FINAL §4-F2, el lote chico que faltaba del stream B:

- **Semáforo de preformas en mi-reporte**: con la asignación del turno (preforma_id ya
  existe en la asignación) y el stock del espejo M04 (`stocks` por bodega de SU
  sucursal): verde = alcanza para la meta · amarillo = alcanza parcial · rojo = sin
  stock visible. Solo LECTURA del espejo — cero escrituras, cero costos visibles
  (principio §1.3). Si el producto no tiene stock espejado o la asignación no tiene
  preforma: el semáforo NO se muestra (silencio correcto, nada de rojo falso).
- **Notas del jefe**: entidad mínima (`produccion_notas`: texto ≤191, vigente_desde/
  hasta nullable, autor, opcional soplador_id NULL = para todos) + CRUD chico en el
  panel del jefe (permiso existente) + las vigentes se pintan en mi-reporte del
  asignado (banner sobrio paleta-4). Molde conceptual: «important notes» de MRPeasy
  (benchmark). Sin M15: la nota vive en la pantalla, no persigue a nadie.
- **Offline**: el semáforo y las notas se renderizan server-side con la página — si el
  soplador abre sin señal, ve lo del último load (comportamiento natural de la PWA);
  NO agregues cache extra ni toques offline-queue.js.

### Candados mínimos
1. Semáforo: verde/amarillo/rojo exactos con stock 500/80/0 contra meta 100 (fixture).
2. Sin espejo o sin preforma asignada → sin semáforo (mutado si es barato).
3. Nota vencida o de otro soplador NO se pinta; la global sí.
4. 403 del CRUD de notas sin permiso del jefe.
5. El soplador sigue sin ver costos (test de contenido en mi-reporte con semáforo).
6. varchar ≤191 en la nota.

## Territorio
- **Max-1** recibe GO de F3 (moldes) en paralelo — él en fichas/contadores/backend, tú
  en mi-reporte y panel del jefe. Cero cruce esperado; si necesitas algo de `Receta` o
  `Molde`, pídelo por parte.
- **Marcos** sigue en el simulador. Re-fetch religioso.

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (baseline
del Director: **1806/12.867** en `2264e8c`). Suite completa antes del push. Blade →
build + superset. `git checkout origin/main --`. Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
