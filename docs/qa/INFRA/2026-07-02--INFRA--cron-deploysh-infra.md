# QA · Infra cPanel: cron del scheduler, deploy.sh, logs (P-S0-07) · 2026-07-02

## 1. Prompt enviado

> Plantilla `VERIFICACION-CPANEL.md`. Pasos originales: (1) inventario de crons, (2) [CAMBIO] eliminar
> cron duplicado `*/20` (esperando que quedara la línea `* * * * *`), (3) [CAMBIO] rotar contraseña BD
> + `.env` + `config:cache`, (4) [CAMBIO] `git update-index --no-skip-worktree deploy.sh` + `git status`,
> (5) `artisan schedule:list`, (6) `tail -20 laravel.log`.
>
> **Corrección en vuelo (2026-07-01):** el Paso 1 reveló que el estado real NO era el asumido — había
> DOS líneas `schedule:run` (`*/20` y `*/15`) y **ninguna** de `* * * * *`. La IA externa se detuvo y
> pidió confirmación (conducta correcta). Paso 2 corregido a: eliminar AMBAS y crear UNA de
> `* * * * *`. Se agregó Paso 2-bis: verificar en `bsale-sync.log` la primera corrida de stock a :50.
> **Paso 3 pospuesto** por decisión del dueño (2026-07-02) → pasa a ser P-S0-09.

## 2. Respuesta recibida

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | Anotar todas las líneas de cron existentes | OK | Se encontraron 2 líneas, ambas `schedule:run`: `*/20 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1` y `*/15 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1`. (No existía ninguna línea `* * * * *`, a diferencia de lo que asumía el runbook original) |
| 2 | [CAMBIO] Eliminar las 2 líneas schedule:run y crear una nueva `* * * * *` | OK | Eliminadas ambas (`*/20` y `*/15`) → "No Cron Jobs". Creada nueva línea: cPanel confirmó "successfully added the cron job". Lista final (1 sola línea): `* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1` |
| 3 | [CAMBIO] Cambiar contraseña BD + editar .env + config:cache | NO SE PUDO | Pospuesto por decisión del dueño; se hará más adelante. No se ejecutó config:cache (sin cambio de .env no hace falta) |
| 4 | [CAMBIO] git update-index --no-skip-worktree deploy.sh + git status | OK | `git update-index` ejecutado sin errores. `git status`: `On branch main` / `Your branch is ahead of 'origin/main' by 111 commits.` / `(use "git push" to publish your local commits)` / `nothing to commit, working tree clean` |
| 5 | artisan schedule:list | OK | `0 * * * * php artisan bsale:sync-catalog — Next Due: en 20 minutos` / `20 * * * * php artisan bsale:sync-clients — Next Due: en 40 minutos` / `40 * * * * php artisan bsale:sync-prices — Next Due: en 1 segundo` / `50 * * * * php artisan bsale:sync-stock — Next Due: en 10 minutos` |
| 6 | tail -20 laravel.log | OK | Últimas 20 líneas: todas `production.INFO` de sync bsale (catalog/clients/prices/stock), sin errores de aplicación. Ej: `[2026-07-02 03:54:43] production.INFO: bsale:sync-stock → 16 bodegas, 0 stock creados, 28350 actualizados, 0 eliminados, 0 omitidos, 0 errores.` y `[2026-07-02 04:28:04] production.INFO: bsale:sync-clients → 0 creados, 48304 actualizados, 0 adoptados, 44 omitidos, 44 errores.` |
| 2-bis | (Verificación) tail -30 bsale-sync.log pasado el :50 | OK | Se esperó hasta pasado :50 (hora servidor). El sync de STOCK arrancó a las :50 ("Sincronizando bodegas y stock…") y completó su tabla: `Bodegas 16 | Stock creados 0 | Actualizados 28350 | Eliminados 0 | Omitidos 0 | Errores 0`. Primera corrida por el cron nuevo, confirmada. |

RESUMEN

VEREDICTO: APROBADO CON OBSERVACIONES

PASOS_OK: 6 de 7 (Pasos 1, 2, 4, 5, 6 y 2-bis OK; Paso 3 pospuesto por decisión del dueño)

CAMBIOS_REALIZADOS:
- Eliminada la línea cron `*/20 * * * * ... artisan schedule:run >> /dev/null 2>&1`
- Eliminada la línea cron `*/15 * * * * ... artisan schedule:run >> /dev/null 2>&1`
- Creada la línea cron `* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1`
- Ejecutado `git update-index --no-skip-worktree deploy.sh` en /home4/impdali/daligo

FALLOS: ninguno

OBSERVACIONES:
- El estado inicial de cron NO coincidía con el runbook original: había DOS líneas `schedule:run` (`*/20` y `*/15`) y ninguna `* * * * *`. Lo confirmé contigo antes de actuar y procedí según tu instrucción corregida.
- Paso 3 (contraseña BD/.env/config:cache) NO ejecutado — pospuesto por decisión del dueño. Además, el manejo de la contraseña en sí debe hacerlo el dueño directamente por ser credencial sensible.
- La tarea de stock está programada al :50 pero tarda ~4 min: en el log aparece "Sincronizando…" a partir del :50 y la tabla de resultados se completa alrededor del :54 (consistente con el patrón visto en laravel.log a las :54).
- En laravel.log se observan corridas de `bsale:sync-clients` con "44 omitidos, 44 errores" de forma recurrente (no es un fallo de esta tarea, pero conviene revisarlo aparte).
- Usé el comando `date` (no listado) únicamente de lectura para calcular la espera del :50; no altera nada. La ventana del navegador se redimensionó durante la espera (efecto visual, sin impacto en el servidor).

## 3. Veredicto

**APROBADO CON OBSERVACIONES.** El objetivo principal (scheduler correcto) quedó resuelto y VERIFICADO:
cron único `* * * * *`, `schedule:list` muestra las 4 syncs, y `bsale:sync-stock` corrió por cron a las :50
**por primera vez** (28.350 stocks actualizados, 0 errores). `deploy.sh` des-congelado. La detención ante
el estado inesperado del Paso 1 fue conducta ejemplar del protocolo.

## 4. Acciones derivadas

- RUTA-MAESTRA: `P-S0-07` marcado `[x]` con esta evidencia.
- `P-S0-09` (nuevo, ya existía como pospuesto): rotación de contraseña BD — pospuesta por el dueño, idealmente antes de F3.
- `P-S0-10` (nuevo): investigar los **44 omitidos / 44 errores recurrentes** de `bsale:sync-clients` (hipótesis: colisiones de RUT duplicado en Bsale, comportamiento documentado en HANDOFF §8c — confirmar y reclasificar el conteo para que no parezca fallo).
- `P-S0-12` (nuevo): diagnosticar el `git status` del servidor — "ahead of 'origin/main' by 111 commits" sugiere que el remote `origin` del servidor apunta al repo viejo o que el deploy hace fetch por URL sin actualizar la tracking ref. Revisar `git remote -v` en la próxima delegación de infra (inofensivo para el deploy actual, pero confuso y propenso a errores).
- CLAUDE.md: entrada de bitácora del hallazgo del cron (2026-07-02).
- HANDOFF §8e/§9: ya corregidos con el hallazgo (mismo push que esta evidencia).
