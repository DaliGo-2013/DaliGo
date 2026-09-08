# ADR-004 · `public/build` versionado porque el servidor no compila

- **Estado:** vigente hasta la migración de hosting
- **Fecha:** junio 2026

## Contexto
El servidor (HostGator compartido) **no tiene Node**. Vite compila CSS/JS en tiempo de build y Tailwind v4 **purga** toda clase que no vea en las fuentes: una clase nueva que no se recompile no existe en producción. El deploy es `git reset --hard` más comandos de artisan; no puede instalar ni compilar nada.

## Decisión
**Los assets compilados viajan en git.** `public/build/` está versionado y se commitea **junto** con el Blade/CSS/JS que los cambió.
- Antes del build de producción: `php artisan view:clear && npm run build`.
- Después: verificar con `grep -F` que el bundle trae las clases críticas (las `lg:`/`max-lg:` de la sidebar) y las nuevas del cambio.
- Confirmar con `git show --stat HEAD` que `public/build` entró al commit (otra sesión puede haber recompilado en medio).

## Alternativas consideradas
- **Compilar en CI y copiar por SSH** — viable, y es la forma correcta después de migrar; hoy suma un paso al deploy inline que ya es frágil.
- **Node en el servidor** — no disponible en el plan.

## Consecuencias
Cada cambio de vista exige rebuild + commit; el bundle puede inflarse con fuentes espurias (motivó ADR-007) o dropear clases si el escaneo ve un estado raro (bitácora [2026-06-15]). Con varias sesiones, **no se commitea `public/build` si hay trabajo ajeno sin commitear** en el árbol.

## Evidencia
`CLAUDE.md` «Frontend / assets», bitácora [2026-06-04], [2026-06-15], [2026-07-24], [2026-07-30], [2026-08-10].
