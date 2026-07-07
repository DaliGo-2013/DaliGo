# QA · I-01 cierre: modo compatibilidad con el reescritor de crons (grilla `*/15`) · 2026-07-07

> No es una delegación a IA externa: es el diagnóstico por SSH (solo lectura) de Max-1 al
> completar el paso 5 del corrector, más el **[CAMBIO]** de crontab dictado por el Director.
> Se conserva el formato de 4 bloques para uniformidad del archivo.

## 1. Comandos ejecutados (SSH `impdali@impdali.cl`, 2026-07-07 09:35–10:38 CDT)

Solo lectura: `grep 'bsale:sync-stock' laravel.log` (por día y `tail`), `crontab -l`,
`tail laravel.log`, última entrada por cada sync, `ls storage/logs/`, `tail bsale-sync.log`,
`grep 'failed with exit code'` (primera/última/conteo), `grep MAIL_*` de `.env` (sin
secretos), lectura de `bootstrap/cache/config.php` vía `php -r`, `date`.
**[CAMBIO]** (dictado del Director, aplicado 10:38 CDT): `crontab -` con la grilla nueva.

## 2. Salidas íntegras (lo relevante)

**Crontab encontrado (BEFORE, 10:38:06 CDT)** — tercera reescritura no registrada:

```
MAILTO=""
*/19 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1

*/15 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

**Crontab aplicado (AFTER, 10:38:12 CDT)** — `crontab: installing new crontab`:

```
MAILTO=""
SHELL="/usr/local/cpanel/bin/jailshell"
*/15 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1

*/15 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=840 >> /dev/null 2>&1
```

(la línea `SHELL=jailshell` la inyecta cPanel, no nosotros)

**Última corrida de cada sync en `laravel.log`** (timestamps del log en **UTC**; server CDT=UTC−5):

```
[2026-07-07 14:00:04] production.ERROR: Scheduled command [... bsale:sync-catalog] failed with exit code [1].
[2026-07-03 11:28:00] production.INFO: bsale:sync-clients → 0 creados, 48322 actualizados, ...
[2026-07-03 11:41:02] production.INFO: bsale:sync-prices → 15 listas, ..., 14234 actualizados, ...
[2026-07-03 10:54:42] production.INFO: bsale:sync-stock → 16 bodegas, ..., 28350 actualizados, ...
```

**Fallos del scheduler**: 18 de `bsale:sync-catalog`, horarios en `:00`, desde `2026-07-06
21:00` UTC hasta `2026-07-07 14:00` UTC (los 4 de `sync-clients` son de un episodio viejo,
2026-06-14). Última catalog exitosa: `[2026-07-06 20:00:20] ... 2848 actualizados`.

**`bsale-sync.log` (tail)** — causa de los fallos de catalog:

```
Sincronizando catálogo desde Bsale (solo lectura en Bsale; escribe solo BD local)…
Sync abortada: Bsale HTTP 401 en product_types.json: {"error":"Sorry, this request can not be authenticated"}
```

## 3. Análisis y veredicto

**Timeline de las TRES reescrituras del crontab (ninguna registrada; cPanel no tiene
historial de ediciones de cron — verificado por el corrector, paso 6):**

| # | Cuándo (UTC del log) | Estado que dejó | Efecto |
|---|---|---|---|
| Estado sano (P-S0-07, 02-07 madrugada) | — | `schedule:run` `* * * * *` | 4 syncs OK (stock corrió 07:54/08:54/09:54 UTC) |
| Reescritura 2ª | 02-07 entre 09:50 y 10:50 | `schedule:run` → `*/20` (queue:work quedó `* * * * *`) | stock (:50) muerto; resto sobrevive de casualidad |
| Corrector (IA-cPanel) | 02-07 18:26 (13:26 CDT) | `schedule:run` → `* * * * *` | TODO OK de nuevo: stock corrió 18:55, 19:54, … horario hasta 03-07 10:54 |
| Reescritura 3ª | 03-07 entre 11:41 y 11:50 | `schedule:run` → `*/19`, `queue:work` → `*/15`, `MAILTO=""` | clients (:20), prices (:40) y stock (:50) muertos — `*/19` dispara :00/:19/:38/:57; catalog (:00) sobrevive |
| **Modo compatibilidad (este cambio)** | 07-07 15:38 (10:38 CDT) | ambas líneas `*/15`, `queue:work --max-time=840` | grilla :00/:15/:30/:45 + syncs re-agendadas a esa grilla (`routes/console.php`, mismo push) |

**Causa raíz (aceptada por el Director):** las 3 reescrituras dejaron SIEMPRE intervalos
≥15 min → automatización de HostGator que estrangula crons por-minuto en plan compartido.
Reponer `* * * * *` es churn: se pelea contra la plataforma. Doctrina nueva: **no agendar
nada por-minuto; grilla `*/15` alineada** (cron y `hourlyAt` deben coincidir EXACTO).

**VEREDICTO: I-01 CERRADA** (causa raíz aceptada + modo compatibilidad aplicado en servidor
y código). Nota: la propia falla 401 de catalog sirve de latido para verificar que la grilla
dispara (ver I-03).

**Hallazgos laterales (de paso, solo lectura):**

1. **I-03 — token Bsale muerto (401):** desde `2026-07-06 21:00` UTC (≈16:00 CDT) TODA
   llamada a Bsale devuelve `401 can not be authenticated`. Espejo congelado: stock/clients/
   prices al 03-07, catálogo al 06-07 20:00 UTC. Lo destraba solo Mauricio en el panel de
   Bsale (¿token regenerado/expirado?). El token nuevo va DIRECTO al `.env` del server
   (File Manager), jamás por chat; luego `config:cache` + una sync manual de verificación.
2. **Correos del taller (M12) volcados al log:** el tail de `laravel.log` termina en HTML
   porque los mails de "Recibimos tu equipo" (órdenes #000005 y #000006, últimas 07-07
   12:44 y 14:32 UTC) se escriben al log en vez de enviarse: la **config cacheada** tiene
   `mail.default = log` mientras el `.env` ya dice `MAIL_MAILER=smtp` (se editó sin
   `config:cache`). Además el `.env` quedó a medio experimento: `MAIL_HOST=staging.impdali.cl`
   (subdominio web, no servidor de correo; el cacheado anterior era `mail.impdali.cl`) y
   `MAIL_FROM_ADDRESS=servicio@staging.impdali.cl`. El próximo deploy re-cachea → los envíos
   pasarán de "falso éxito al log" a fallo visible pero **elegante** (el controlador de M12
   ya envuelve el send en try/catch con aviso "revisa la configuración de correo"; comentario
   en código: SMTP pendiente = P-M15-10). Los clientes de #000005/#000006 NUNCA recibieron
   su correo.

## 4. Acciones derivadas

- `routes/console.php`: syncs re-agendadas a la grilla (catalog :00, clients :15, prices :30,
  stock :45) + test `ScheduleBsaleTest::test_las_syncs_van_en_la_grilla_de_15` (mismo push).
- CLAUDE.md: entrada de bitácora [2026-07-07] con la doctrina (gotcha de infra del mes) +
  §Deploy actualizado + marca «superada» en el "Evitar a futuro" del [2026-07-02].
- **VIGILANCIA 24h:** verificar ~08-07 que la grilla `*/15` sobrevive al reescritor
  (`crontab -l` textual + latido en `laravel.log` a :00/:15/:30/:45). Si también la
  reescribe → escalar a soporte HostGator con esta evidencia.
- I-03 (token Bsale) queda abierta, en manos de Mauricio (pasos arriba).
- Correo del taller: cierre real = P-M15-10 (configurar SMTP verdadero); mientras tanto los
  "avisada al cliente" del taller no son confiables — avisado al Director en el parte.
- Borrador de pregunta a soporte HostGator (para revisión del Director ANTES de despachar):

> Hola — en la cuenta `impdali` (plan compartido) el crontab del usuario ha sido modificado
> automáticamente al menos 3 veces sin acción nuestra (la primera en fecha desconocida —
> estado `*/20`+`*/15` encontrado el 02-07 —, la segunda el 02-07 entre ~04:50 y 05:50 CDT,
> la tercera el 03-07 entre ~06:41 y 06:50 CDT): líneas que configuramos con frecuencia
> `* * * * *` amanecen reescritas a `*/19` o `*/20`, y se agregó `MAILTO=""`. ¿Existe una política/automatización que limite
> la frecuencia mínima de cron jobs en nuestro plan? ¿Cuál es el mínimo permitido? ¿Qué plan
> o configuración permite un cron por-minuto (lo requiere el scheduler de Laravel)? ¿Pueden
> confirmar qué proceso hizo esas modificaciones y notificarnos cuando ocurran?
