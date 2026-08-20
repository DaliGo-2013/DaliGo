# Parte de cierre — docs del deploy alineados con el flujo real

```
CUENTA: claude-code (asiento del dueño; tarea encargada directa por Mauricio)
TAREA: fuera de tablero — solo-docs: HANDOFF.md y CLAUDE.md describían el deploy viejo (deploy.sh + composer install)
ESTADO: HECHA
EVIDENCIA: commit 2b68db8 (HANDOFF.md + CLAUDE.md, 2 archivos, solo docs)
TESTS: no aplica (cambio solo-docs; no se tocó código ni bundle)
/usage INICIO → FIN: no disponible en este asiento
SIGUIENTE: nada pendiente; si el Director quiere, replicar la advertencia en docs/delegacion/ donde se cite deploy.sh
```

## Qué se corrigió

HANDOFF.md §5 y CLAUDE.md sección "Deploy" decían que GitHub Actions corre
`deploy.sh` con `composer install`. El flujo real (`.github/workflows/deploy.yml`)
es una **cadena inline por SSH**: fetch → limpiar flags skip-worktree (menos
`.htaccess`) → `git reset --hard FETCH_HEAD` → **`composer dump-autoload -o`
(NO instala dependencias)** → `migrate` → `db:seed --force` → `storage:link` →
`optimize:clear` + `config:cache` + `route:cache` → `permission:cache-reset`.
El `deploy.sh` del servidor está congelado (skip-worktree, explicado en el
comentario de deploy.yml:28-34) y ya no participa.

## Advertencia operativa nueva (en ambos docs)

**Paquete nuevo o actualizado en `composer.json` = `composer install --no-dev`
MANUAL por SSH/Terminal de cPanel** en `/home4/impdali/daligo`
(`/opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/composer/bin/composer`).
El deploy NO lo hace; sin esto producción falla con "Class not found".

## Alcance

- Territorio: solo HANDOFF.md y CLAUDE.md (raíz) + este parte. La bitácora de
  CLAUDE.md no se tocó (es historia). Regla aplicada: «Docs a main = inocuo»
  (RELEVO-DIRECTOR §4).
- Trabajado en worktree aislado desde origin/main (2dbd16b).
