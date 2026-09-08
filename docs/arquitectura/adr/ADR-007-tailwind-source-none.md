# ADR-007 · Tailwind v4 `source(none)`: el bundle solo lee `resources/**`

- **Estado:** vigente
- **Fecha:** 2026-07-30

## Contexto
La autodetección de fuentes de Tailwind v4 barre el proyecto entero. Entraban clases desde **la bitácora y `docs/`** (documentar un defecto lo causaba), desde **el regex de un test**, desde **worktrees viejos** en `.claude/worktrees/` (copias antiguas de los mismos Blades) y desde **el bundle anterior** si no se borraba `public/build`. Resultado: 29 reglas que ninguna plantilla usaba, un bundle no reproducible, y `grep` del CSS que mentía.

## Decisión
**`@import 'tailwindcss' source(none)` + `@source` explícitos:** `resources/**/*.blade.php`, `resources/**/*.js` y las vistas de paginación. Nada más.
- Se retiró el `@source` de `storage/framework/views` (compilar un Blade no inventa clases) → el gotcha de `view:clear` queda cerrado por construcción.
- Verificación de que no se cayó nada en uso: extraer los tokens de `class="…"` de los Blades y comprobar, por cada uno que el bundle viejo generaba, que el nuevo también — con **control positivo** (cuántos matchean) y **negativo** (quitar una regla a mano y ver que se detecta).

## Alternativas consideradas
- **Autodetección + `view:clear` antes del build** — frágil: depende de acordarse y no cubre docs, tests ni worktrees.
- **Excluir carpetas al estilo `.gitignore`** — el escáner no razona en términos de git.

## Consecuencias
Una clase escrita en `app/` (por ejemplo en `AppLayout.php`) **no existe** en el bundle: los literales van en el Blade. Al buscar una clase en el CSS, exigir límites de token (`px-8` matchea dentro de `lg:px-8`) y usar `grep -F`.

## Evidencia
`CLAUDE.md` bitácora [2026-07-30], [2026-07-24], [2026-07-01] · `resources/css/app.css`.
