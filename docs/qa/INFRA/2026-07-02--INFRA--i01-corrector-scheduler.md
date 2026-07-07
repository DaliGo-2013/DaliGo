# QA · I-01 corrector del scheduler (regresión a `*/20`) · 2026-07-02

## 1. Prompt enviado

> Plantilla `VERIFICACION-CPANEL.md`, redactada por el Director (Pro-1) y despachada por
> Mauricio a la IA de cPanel el 2026-07-02 ~13:15 CDT (texto íntegro en la sesión del
> Director). Pasos: (1) anotar los cron existentes (estado previo), (2) **[CAMBIO]** editar
> SOLO la línea de `schedule:run` → `* * * * *` (sin tocar la de `queue:work`, sin agregar
> ni borrar líneas), (3) copiar el crontab completo resultante, (4) Terminal →
> `php artisan schedule:list`, (5) esperar el próximo `:50` y `grep sync-stock` del log,
> (6) buscar registro/notificación de quién/cuándo modificó el cron.

## 2. Respuesta recibida

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | Anotar cron existentes (estado previo) | OK | Exactamente 2 líneas. L1: `*/20 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1`. L2: `* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1`. Coincide con lo esperado. |
| 2 | [CAMBIO] Editar SOLO schedule:run → `* * * * *` | OK | En el formulario Edit de la línea schedule:run cambié Minute de `*/20` a `*` (Hour/Day/Month/Weekday ya eran `*`). Command quedó IDÉNTICO: `/opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1`. Mensaje: "Edit Successful". No toqué la línea de queue:work; no agregué ni borré líneas. |
| 3 | Copiar crontab completo resultante | OK | Exactamente 2 líneas, ambas `* * * * *`. L1: `* * * * * ... artisan schedule:run >> /dev/null 2>&1`. L2: `* * * * * ... artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1`. |
| 4 | Terminal → schedule:list | OK | Salida (4 comandos con Next Due): `0  * * * *  php artisan bsale:sync-catalog ... Next Due: en 37 minutos`; `20 * * * *  php artisan bsale:sync-clients ... Has Mutex > Next Due: en 57 minutos`; `40 * * * *  php artisan bsale:sync-prices ... Next Due: en 17 minutos`; `50 * * * *  php artisan bsale:sync-stock ... Next Due: en 27 minutos`. Coincide con catalog :00, clients :20, prices :40, stock :50. |
| 5 | Esperar próximo :50 y grep sync-stock | NO SE PUDO | Hora servidor al iniciar espera: `Thu Jul  2 13:23:01 CDT 2026`; sync-stock próximo a las ~13:50 (Next Due "en 27 minutos"). No es viable mantener la sesión inactiva ~24 min. Baseline grep (últimas 3, TODAS previas al cambio): `[2026-07-02 07:54:42] ... bsale:sync-stock → 16 bodegas, 0 stock creados, 28350 actualizados, 0 eliminados, 0 omitidos, 0 errores.`, `[2026-07-02 08:54:38] ...` (mismos totales), `[2026-07-02 09:54:42] ...` (mismos totales). No hay entrada nueva aún. Hora exacta en que dejé el servidor: `Thu Jul  2 13:26:14 CDT 2026`. |
| 6 | Registro/notificación de cambio de cron | OK | Notificaciones cPanel: "You have not received any notifications." La página Cron Jobs no expone historial/auditoría de ediciones (ni quién ni cuándo); solo muestra los cron actuales y el campo opcional "Cron Email" para salida. Sin registro visible de quién/cuándo se modificó el cron. |

RESUMEN
VEREDICTO: APROBADO CON OBSERVACIONES
PASOS_OK: 5 de 6
CAMBIOS_REALIZADOS: Se editó ÚNICAMENTE la línea del scheduler, cambiando su frecuencia de `*/20 * * * *` a `* * * * *`. Comando sin cambios. La línea de queue:work no se tocó. No se agregaron ni eliminaron líneas.
FALLOS: ninguno
OBSERVACIONES:
- Paso 5 no verificable en vivo: la ejecución natural de sync-stock (minuto :50) ocurriría hacia las ~13:50 CDT y no era práctico esperar ~24 min. Dejé el servidor a las 13:26:14 CDT. Para confirmar, volver a ejecutar `grep "sync-stock" .../laravel.log | tail -3` después de las 13:50 CDT.
- Dato relevante: el último sync-stock registrado fue a las 09:54; faltan los ciclos de 10:54/11:54/12:54. Consistente con la regresión (con `*/20` el cron solo dispara :00/:20/:40, nunca :50).
- sync-clients aparece con "Has Mutex" en schedule:list (bloqueo de solapamiento activo), informativo, no un error.
CAPTURAS: no aplica

## 3. Veredicto

**APROBADO CON OBSERVACIONES.** Ejecución quirúrgica: solo la línea dictada, comando intacto,
crontab final verificado textualmente y `schedule:list` con las 4 syncs. El paso 5 (primera
corrida `:50` post-fix) quedó pendiente y **lo completó Max-1 el 2026-07-07 por SSH**
(solo lectura): el fix **SÍ funcionó** — `bsale:sync-stock` corrió a las `18:55` y `19:54`
del 02-07 (hora log = **UTC**; `18:50` UTC = `13:50` CDT, el primer `:50` tras el fix de las
13:26 CDT) y siguió horario hasta el `03-07 10:54` UTC. Nota de lectura: los timestamps del
log van en UTC (server CDT = UTC−5); el "faltan 10:54/11:54/12:54" del corrector interpretaba
hora local, la ventana real de la regresión `*/20` fue `02-07 09:50–18:26` UTC.

## 4. Acciones derivadas

- La verificación del paso 5 destapó una **TERCERA reescritura** del crontab (03-07, `*/19`)
  → causa raíz aceptada por el Director: HostGator estrangula crons por-minuto. Cierre de
  I-01 en **modo compatibilidad** (grilla `*/15` alineada): evidencia y análisis completos en
  [2026-07-07--INFRA--i01-cierre-modo-compatibilidad.md](2026-07-07--INFRA--i01-cierre-modo-compatibilidad.md).
- CLAUDE.md: entrada de bitácora [2026-07-07] con la doctrina nueva (no agendar nada
  por-minuto en este hosting); la entrada [2026-07-02] del cron queda marcada como superada.
- Gate pre-P-M15-09: el Director lo redefinió a "modo compatibilidad aplicado" (latencia
  interina de notificaciones ≤15 min, aceptable).
