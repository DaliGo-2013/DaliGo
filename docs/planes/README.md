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
