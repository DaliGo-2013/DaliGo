# Planes detallados por módulo — regla del sello de vigencia

> Carpeta para los planes de implementación de cada módulo/unidad (ej.: el plan de M15 antes de E1).
> Existe por una lección aprendida: `PLAN-M11-FASE2.md` divergió del código y estuvo semanas
> describiendo un diseño que nunca se construyó (ver `_archivo/` y la bitácora de `CLAUDE.md`, 2026-06-26).

## 1. Cuándo se escribe un plan

**Justo antes de arrancar el módulo, nunca antes.** Los checklists gruesos de módulos futuros viven
en `docs/RUTA-MAESTRA.md`; el plan fino se escribe al abrir la unidad (con el código actual a la vista).

## 2. El sello de vigencia (encabezado obligatorio, línea 2 del archivo)

```markdown
> **Estado: VIGENTE — verificado contra el código el AAAA-MM-DD (commit abc1234)**
```

## 3. Reglas

1. **Un plan sin sello vigente no se ejecuta.** Si el sello tiene **más de 7 días** o hay commits
   posteriores que tocan el área, el primer paso obligatorio es re-verificarlo contra el código y
   re-sellarlo (o declararlo obsoleto).
2. **Muerte de un plan:** banner `⚠️ SUPERADO/OBSOLETO (fecha) — documento histórico, NO refleja el código`
   en la línea 2 + mover a `_archivo/`. **Nunca borrar** (son parte de la historia del proyecto).
3. El plan describe el CÓMO de la unidad; el estado (pasos `[x]`) se marca en `docs/RUTA-MAESTRA.md`,
   no en el plan (regla de estado único).

## 4. Contenido en `_archivo/`

| Archivo | Por qué está aquí |
|---|---|
| `PLAN-M11-FASE2.md` | Superado el 2026-06-26: el código siguió el diseño máquinas+tipos+tandas con kardex local; el estado real de M11 quedó en `HANDOFF.md` §8d. |

## 5. ⚠️ Planes citados que NO existen en el repo

Detectado el 2026-07-26 al cruzar la biblia contra la app. Se anota acá —en vez de
inventar el documento— para que nadie más lo dé por norma.

| Plan citado | Dónde se cita como fuente | Estado |
|---|---|---|
| `PLAN-DESPACHOS-V1` | `docs/fleet/buzon/dictados/max-2.md:17` ("Según PLAN-DESPACHOS-V1 §2…") · `docs/fleet/buzon/anexo-p-dsp-00-shape-documents.md:100` ("Reconciliación del Director contra PLAN-DESPACHOS-V1 §1.2") · `docs/fleet/RELEVO-DIRECTOR.md:75` · `docs/BITACORA-SESIONES.md:249` · la fila «Próximo paso» de `RUTA-MAESTRA.md §0` | **No existe en el repo.** Esta carpeta solo tiene M14, M15, M16-V0, M16-V1 y TIMEZONE |

**Por qué importa:** el alcance de DESPACHOS-V1 —la unidad del stream 2, con rama
`feature/despachos-v1` viva— se referencia por sección (`§1.2`, `§2`) como si fuera
normativo, pero no hay dónde leerlo. Tampoco tiene pasos `P-DSP-nn` con ficha en
`RUTA-MAESTRA` (solo se lo menciona en «Próximo paso»). O el documento vive fuera
del repo y hay que traerlo, o el trabajo se está guiando por un plan que nadie
puede consultar. **Pendiente de aclarar con el dueño.**
