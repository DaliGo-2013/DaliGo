# QA · Cron de la cola (queue:work) para M15 · 2026-07-04

## 1. Prompt enviado

```markdown
CONTEXTO
Administras el hosting de DaliGo (app Laravel) en HostGator compartido con cPanel. La app vive en
/home4/impdali/daligo y el sitio de pruebas es https://staging.impdali.cl. Hoy: habilitar el
procesador de la cola de trabajos (queue:work) con un segundo cron por-minuto, sin tocar el cron
del scheduler que ya existe.

TU ROL
Eres el operador de cPanel/servidor. Ejecutas los pasos en orden, anotas VALORES EXACTOS (copia el
texto tal cual, no lo resumas) y no cambias nada que no esté marcado como [CAMBIO].

ACCESO
- cPanel: «PEGAR URL/USUARIO/CLAVE O INDICAR QUE YA TIENES SESIÓN»

TAREA
Agregar UN cron nuevo que procese la cola de la app cada minuto (sale solo cuando la cola queda
vacía) y verificar que el cron del scheduler existente quedó intacto.

PASOS EXACTOS
1. cPanel → Cron Jobs. Anota TODAS las líneas de cron existentes, textuales (estado previo).
   Esperado: una línea "* * * * *" que contiene "schedule:run".
2. [CAMBIO] En Cron Jobs, agrega UNA línea nueva exactamente así (frecuencia: * * * * *):
   /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
   No borres ni edites ninguna línea existente.
3. Vuelve a Cron Jobs y copia el crontab completo resultante, textual, línea por línea.
   Esperado: las líneas previas del paso 1 intactas (incluida la de schedule:run) + la nueva de queue:work.
4. cPanel → Terminal → ejecuta y copia la salida completa:
   /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55
   Esperado: termina solo en pocos segundos (la cola está vacía hoy); sin errores de conexión a BD.
5. Abre /home4/impdali/daligo/storage/logs/laravel.log → copia las últimas 20 líneas.

(FORMATO DE RESPUESTA: tabla por paso + bloque RESUMEN, según plantilla VERIFICACION-CPANEL.)
```

## 2. Respuesta recibida

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | Anotar cron existentes (estado previo) | OK (con observación) | Una única línea previa: `*/20 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1`. Contiene "schedule:run" como se esperaba, PERO la frecuencia es `*/20 * * * *` (cada 20 min), no `* * * * *` como indicaba el esperado. |
| 2 | [CAMBIO] Agregar cron nuevo queue:work (`* * * * *`) | OK | Formulario completado: Minute/Hour/Day/Month/Weekday = `*`; Command = `/opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1`. Mensaje de cPanel: "cPanel successfully added the cron job." No se editó ni borró ninguna línea existente. |
| 3 | Copiar crontab completo resultante | OK | Línea 1: `*/20 * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan schedule:run >> /dev/null 2>&1` (intacta). Línea 2: `* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1` (nueva). |
| 4 | Ejecutar queue:work en Terminal | OK | Salida completa: `INFO  Processing jobs from the [default] queue.` y retorno inmediato al prompt. Terminó solo en pocos segundos, sin errores de conexión a BD. |
| 5 | Últimas 20 líneas de laravel.log | OK | Ver bloque abajo. |

Últimas líneas de `/home4/impdali/daligo/storage/logs/laravel.log` (textual):

```
[2026-07-02 13:00:19] production.INFO: bsale:sync-catalog → 0 creados, 2848 actualizados, 0 adoptados, 0 desactivados, 0 omitidos, 0 errores.
[2026-07-02 13:28:10] production.INFO: bsale:sync-clients → 0 creados, 48304 actualizados, 0 adoptados, 44 omitidos, 44 errores.
[2026-07-02 13:41:01] production.INFO: bsale:sync-prices → 15 listas, 0 precios creados, 14234 actualizados, 0 eliminados, 0 omitidos, 0 errores.
[2026-07-02 14:00:20] production.INFO: bsale:sync-catalog → 0 creados, 2848 actualizados, 0 adoptados, 0 desactivados, 0 omitidos, 0 errores.
[2026-07-02 14:28:15] production.INFO: bsale:sync-clients → 1 creados, 48304 actualizados, 0 adoptados, 44 omitidos, 44 errores.
[2026-07-02 14:41:02] production.INFO: bsale:sync-prices → 15 listas, 0 precios creados, 14234 actualizados, 0 eliminados, 0 omitidos, 0 errores.
[2026-07-02 15:00:21] production.INFO: bsale:sync-catalog → 0 creados, 2848 actualizados, 0 adoptados, 0 desactivados, 0 omitidos, 0 errores.
[2026-07-02 15:28:42] production.INFO: bsale:sync-clients → 1 creados, 48305 actualizados, 0 adoptados, 44 omitidos, 44 errores.
[2026-07-02 15:41:03] production.INFO: bsale:sync-prices → 15 listas, 0 precios creados, 14234 actualizados, 0 eliminados, 0 omitidos, 0 errores.
[2026-07-02 16:00:20] production.INFO: bsale:sync-catalog → ...
[2026-07-02 16:28:29] production.INFO: bsale:sync-clients → 1 creados, 48306 actualizados, 0 adoptados, 44 omitidos, 44 errores.
[2026-07-02 16:41:05] production.INFO: bsale:sync-prices → 15 listas, ...
[2026-07-02 17:00:21] production.INFO: bsale:sync-catalog → ...
```
(La IA anotó que algunas de las 20 líneas físicas venían con envoltura por ancho de pantalla.)

RESUMEN
VEREDICTO: APROBADO CON OBSERVACIONES
PASOS_OK: 5 de 5
CAMBIOS_REALIZADOS: Se agregó UNA línea de cron nueva (`* * * * * … queue:work --stop-when-empty --max-time=55`). No se editó ni borró ninguna línea existente.
FALLOS: ninguno
OBSERVACIONES:
- El cron del scheduler existente tiene frecuencia `*/20 * * * *` (cada 20 minutos), NO `* * * * *` como indicaba el "Esperado" del paso 1. Contiene "schedule:run" y quedó intacto tras el cambio.
- El log muestra "44 omitidos, 44 errores" recurrentes en `bsale:sync-clients` en cada corrida.
- `queue:work` no generó entradas nuevas en el log (cola vacía hoy).
CAPTURAS: no aplica.

## 3. Veredicto

**APROBADO CON OBSERVACIONES.** El objetivo de P-M15-03 se cumplió: el cron de la cola quedó creado
(`* * * * * … queue:work --stop-when-empty --max-time=55`) y el worker corre, procesa y sale sin
errores. La cola de M15 quedará operativa apenas se despachen notificaciones (latencia ≤ 1 min).

**HALLAZGO CRÍTICO (fuera del alcance de M15 — escalado al Director):** el cron del scheduler está en
`*/20 * * * *`, que **contradice la evidencia archivada de P-S0-07** (`2026-07-02--INFRA--cron-deploysh-infra.md`),
donde se dejó en `* * * * *` y se verificó `bsale:sync-stock` corriendo a :50. Consecuencias que confirma
esta misma respuesta: (a) el log NO tiene ninguna línea de `bsale:sync-stock` — con `*/20` el scheduler
dispara solo a :00/:20/:40 y **nunca el :50**, así que el espejo de stock volvió a no actualizarse por cron
(el bug original que P-S0-07 cerró); (b) el estado actual (`*/20` solo, sin el `*/15`) no es ni el pre- ni el
post-P-S0-07, así que hubo un cambio posterior no documentado (o la doc quedó adelantada al server —
lección recurrente: pedir el crontab textual, nunca fiarse de la memoria). Impacto en M15: el futuro
`notificaciones:reintentar` (P-M15-05) se agenda vía `schedule:run`; con `*/20` correría cada 20 min en vez
de cada 5 → reintentos con granularidad degradada (no roto: reclama por `programada_para <= now()`).

## 4. Acciones derivadas

- **P-M15-03 → `[x]`**: cron de la cola creado y verificado (esta evidencia).
- **Escalado al Director (NO es territorio de stream 2):** re-verificar/corregir el cron del scheduler a
  `* * * * *` (mismo fix de P-S0-07) — reactiva `bsale:sync-stock` (:50) y devuelve la granularidad de 1 min
  al futuro reintentador de M15. Propuesta de prompt corrector lista si el Director lo pide (misma plantilla
  VERIFICACION-CPANEL, patrón de P-S0-07). Probable nuevo paso P-S0-xx en territorio infra/stream 1.
- **Nota P-M15-05:** diseñar el reintentador robusto a un scheduler no-por-minuto (reclamar por fecha vencida,
  no asumir cadencia exacta). Ya contemplado en el diseño atómico del visto bueno.
- Observación ya conocida y con paso propio: `bsale:sync-clients` "44 omitidos/44 errores" → P-S0-10 (abierto).
