# Evidencia QA — convenciones

> Aquí se archivan, íntegras y sin editar, las respuestas de la IA externa de cPanel/QA.
> Junto con `docs/BITACORA-SESIONES.md` y la bitácora de errores de `CLAUDE.md`, esto forma la
> documentación oficial del proceso de creación de la app. Protocolo: `docs/delegacion/PROTOCOLO-DELEGACION.md`.

## 1. Estructura

- Una carpeta por módulo: `docs/qa/M01/ … M16/` (se crea con el primer QA del módulo).
- `docs/qa/INFRA/` para verificaciones de cPanel/servidor no ligadas a un módulo.

## 2. Nombre de archivo

```
AAAA-MM-DD--<Mxx|INFRA>--tema-corto.md
```

Ejemplos: `2026-07-02--INFRA--cron-password-deploy.md`, `2026-07-20--M15--qa-staging-notificaciones.md`.

## 3. Contenido obligatorio (4 bloques)

```markdown
# QA · {{tema}} · {{fecha}}

## 1. Prompt enviado
(copia ÍNTEGRA del prompt, con los huecos «PEGAR...» tal cual — sin credenciales reales)

## 2. Respuesta recibida
(pegada tal cual, SIN editar — aunque tenga errores de formato)

## 3. Veredicto
APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO | INVÁLIDA (reenvío)
(1–3 líneas de lectura nuestra de la respuesta)

## 4. Acciones derivadas
- Pasos marcados/creados/reabiertos en RUTA-MAESTRA (con sus IDs P-xxx)
```

> ⚠️ **Nunca** archivar credenciales reales: si la respuesta pegada las contiene, reemplazarlas por
> `«REDACTADO»` antes de commitear.

## 4. Índice de evidencias

| Fecha | Área | Tema | Veredicto | Archivo |
|---|---|---|---|---|
| — | — | (se llena con la primera evidencia) | — | — |
