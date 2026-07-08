# QA · Entregabilidad de correo (SPF/DKIM/DMARC + SMTP) — P-M15-10, cierre de E1·M15 · 2026-07-08

> **Nota sobre el prompt:** lo archivado en §1 es la versión redactada por el stream 2. La versión
> DESPACHADA llevaba 2 correcciones del Director (revisión pre-despacho): (1) quoting bash del `$m`
> en el `tinker --execute` del paso 11 (dentro de comillas dobles el shell lo expande a vacío);
> (2) paso 5b sin lógica circular — el reseteo de clave pasó a ser incondicional si la cuenta
> existe. Ambas están registradas en la bitácora de `CLAUDE.md` [2026-07-07]. La respuesta de §2
> refleja la versión corregida (el paso 5 ejecutó el sub-caso (b) directamente).

## 1. Prompt enviado

```markdown
CONTEXTO
Administras el hosting de DaliGo (app Laravel) en HostGator compartido con cPanel. La app vive en
/home4/impdali/daligo y el sitio de pruebas es https://staging.impdali.cl. Hoy: dejar operativa y
confiable la entrega de correos del sistema (módulo de notificaciones M15 recién desplegado y los
correos del taller M12 que hoy están cayendo al log): autenticación del dominio impdali.cl
(SPF/DKIM/DMARC), cuenta SMTP del sistema, y prueba de entregabilidad real a Gmail y Outlook.

TU ROL
Eres el operador de cPanel/servidor. Ejecutas los pasos en orden, anotas VALORES EXACTOS (copia el
texto tal cual, no lo resumas) y no cambias nada que no esté marcado como [CAMBIO].
REGLA DE SEGURIDAD: la clave de la cuenta de correo JAMÁS aparece en tu respuesta — donde
corresponda escribe [CLAVE OCULTA]. Lo mismo con cualquier MAIL_PASSWORD del .env.

ACCESO
- cPanel: «PEGAR URL/USUARIO/CLAVE O INDICAR QUE YA TIENES SESIÓN»
- Terminal de cPanel (para artisan y lectura de .env/logs).
- Clave para la cuenta de correo del sistema (paso 5, si hay que crearla o resetearla):
  «PEGAR CLAVE — Mauricio la genera; no la escribas de vuelta en la respuesta»
- Casillas de prueba externas: Gmail «PEGAR correo Gmail de Mauricio» y
  Outlook/Hotmail «PEGAR correo Outlook de Mauricio».

TAREA
(a) Verificar/configurar SPF, DKIM y DMARC del dominio impdali.cl; (b) diagnosticar la cuenta SMTP
del sistema (hay un intento previo con servicio@staging.impdali.cl que falla autenticación —
determinar si esa cuenta existe o si la correcta es servicio@impdali.cl, y dejar UNA operativa);
(c) configurar el .env del server con los valores MAIL_* definitivos; (d) probar entregabilidad
real a Gmail y Outlook con veredicto inbox/spam por cada uno; (e) verificación final desde la app:
botón "enviar prueba" de /admin/notificaciones → la fila de canal mail debe quedar "Enviada".

PASOS EXACTOS

— Bloque 1 · Estado actual (solo lectura) —
1. Terminal → ejecuta: grep '^MAIL_' /home4/impdali/daligo/.env
   Copia la salida TEXTUAL, reemplazando el valor de MAIL_PASSWORD por [CLAVE OCULTA].
2. Terminal → ejecuta:
   /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan tinker --execute="echo config('mail.default');"
   Anota la salida exacta (es el mailer CACHEADO en uso; puede diferir del .env).
3. cPanel → Email Accounts. Lista TODAS las cuentas existentes de impdali.cl y de
   staging.impdali.cl (nombre textual de cada una). En particular: ¿existe
   servicio@impdali.cl? ¿existe servicio@staging.impdali.cl?
4. cPanel → Email Deliverability → dominio impdali.cl. Anota el estado que reporta cPanel
   para SPF y DKIM (VALID / PROBLEMS DETECTED / texto exacto) y copia los registros
   sugeridos/actuales textuales. Repite para el subdominio staging.impdali.cl si aparece listado.

— Bloque 2 · Cuenta SMTP del sistema —
5. [CAMBIO] Según el paso 3:
   a. Si servicio@impdali.cl NO existe → créala en Email Accounts con la clave «PEGAR»
      (cuota 250 MB basta).
   b. Si existe pero la autenticación falla (se valida en el paso 9) → resetéale la clave
      a la misma «PEGAR».
   c. La cuenta servicio@staging.impdali.cl NO se usa más: NO la borres (solo anota que
      queda obsoleta).
   Anota qué sub-caso aplicaste (a/b/ninguno) — sin escribir la clave.

— Bloque 3 · Autenticación del dominio —
6. [CAMBIO] En Email Deliverability → impdali.cl: si SPF o DKIM aparecen con problemas,
   usa el botón "Repair" / instala los registros sugeridos por cPanel. Copia los registros
   finales textuales (el TXT de SPF completo y el nombre del selector DKIM).
   Esperado: ambos en estado VALID.
7. cPanel → Zone Editor → impdali.cl → busca un registro TXT llamado _dmarc. Anota si existe
   y su valor textual.
8. [CAMBIO] Si _dmarc NO existe, créalo en Zone Editor:
   Tipo TXT · Nombre: _dmarc · TTL 14400 · Valor EXACTO:
   v=DMARC1; p=none; rua=mailto:servicio@impdali.cl
   (política de solo-monitoreo, deliberada: primero observar, endurecer después).
   Si YA existe con otra política, NO lo toques — solo anótalo en OBSERVACIONES.

— Bloque 4 · Configuración de la app —
9. [CAMBIO] Edita /home4/impdali/daligo/.env dejando EXACTAMENTE estas claves (crea las que
   falten, corrige las que difieran; NO toques ninguna otra línea del archivo):
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps
   MAIL_HOST=mail.impdali.cl
   MAIL_PORT=465
   MAIL_USERNAME=servicio@impdali.cl
   MAIL_PASSWORD=«PEGAR (la misma del paso 5)»
   MAIL_FROM_ADDRESS=servicio@impdali.cl
   MAIL_FROM_NAME="DaliGo"
10. [CAMBIO] Terminal → ejecuta:
    /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan config:cache
    Copia la salida. Luego repite el comando del paso 2 y confirma que ahora imprime: smtp

— Bloque 5 · Entregabilidad real —
11. Terminal → ejecuta (envío de prueba nativo de Laravel, a las DOS casillas):
    /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan tinker --execute="Mail::raw('Prueba de entregabilidad DaliGo P-M15-10 '.now(), fn($m) => $m->to(['«GMAIL»','«OUTLOOK»'])->subject('Prueba DaliGo P-M15-10'));"
    Anota: ¿terminó sin error? Copia cualquier excepción textual completa.
    [NOTA: la versión despachada corrigió el quoting del $m — ver nota del encabezado]
12. Pide a Mauricio que revise AMBAS casillas (dale 5 min) y anota POR CADA UNA el veredicto:
    RECIBIDOS / SPAM / NO LLEGÓ. Si llegó, que abra "Mostrar original" (Gmail) y anota los
    resultados de autenticación: SPF=?, DKIM=?, DMARC=? (pass/fail textual).
13. Abre /home4/impdali/daligo/storage/logs/laravel.log → copia las últimas 20 líneas
    (buscamos errores de mailer posteriores al cambio).

— Bloque 6 · Verificación desde la app (M15 + bonus M12) —
14. Entra a https://staging.impdali.cl con el admin «PEGAR usuario/clave si no tienes sesión»
    → /admin/notificaciones → botón "Enviar prueba". Anota el mensaje de confirmación.
15. El worker de cola corre por cron en la grilla */15 (minutos :00/:15/:30/:45). Espera el
    próximo slot + 2 minutos, recarga /admin/notificaciones y anota el estado de la fila
    más reciente de canal "mail" (Esperado: Enviada). Si dice "Fallida", copia el texto
    completo de su error.
16. BONUS M12 (mismo arreglo, solo verificación): confirma en el paso 2/10 que el mailer
    efectivo dejó de ser "log". Anota además si en el laravel.log del paso 13 aparecen
    envíos del taller volcados al log DESPUÉS del config:cache (no debería haber ninguno
    nuevo; los correos de órdenes nuevas del taller ya saldrán por SMTP).

[FORMATO DE RESPUESTA OBLIGATORIO: tabla por paso + bloque RESUMEN con VEREDICTO/PASOS_OK/
CAMBIOS_REALIZADOS/FALLOS/OBSERVACIONES/CAPTURAS/ENTREGABILIDAD — según plantilla
VERIFICACION-CPANEL. Reglas anti-alucinación y de seguridad de la clave incluidas.]
```

## 2. Respuesta recibida

| # | Paso | Resultado | Detalle |
|---|------|-----------|---------|
| 1 | Leer MAIL_* del .env (estado inicial) | OK | `MAIL_MAILER=smtp` · `MAIL_HOST=staging.impdali.cl` · `MAIL_PORT=465` · `MAIL_SCHEME=smtps` · `MAIL_USERNAME=servicio@staging.impdali.cl` · `MAIL_PASSWORD=[CLAVE OCULTA]` · `MAIL_FROM_ADDRESS=servicio@staging.impdali.cl` · `MAIL_FROM_NAME="DaliGo Servicio Tecnico"` |
| 2 | Mailer cacheado en uso | OK | Salida: `smtp` |
| 3 | Listar cuentas servicio@ | OK | Existen: `servicio@impdali.cl` (SÍ) · `servicio@staging.impdali.cl` (SÍ) · `serviciotecnico@impdali.cl`. → aplica sub-caso (b) del paso 5 |
| 4 | Email Deliverability (estado) | OK | **impdali.cl**: DKIM=VALID, SPF=PROBLEMS EXIST (falta `ip4:108.167.161.119`), PTR=VALID, HELO=gator4228.hostgator.com. **staging.impdali.cl**: Valid |
| 5 | [CAMBIO] Cuenta SMTP del sistema | OK | Sub-caso **(b)**: `servicio@impdali.cl` ya existía → clave reseteada (banner "Success… changes saved"). `servicio@staging.impdali.cl` NO tocada (obsoleta). Clave = [CLAVE OCULTA] |
| 6 | [CAMBIO] Reparar SPF/DKIM | OK | Tras "Install The Suggested Record": DKIM=VALID, **SPF=VALID**. SPF final: `v=spf1 +a +mx +ip4:108.167.161.119 +include:websitewelcome.com ~all` · selector DKIM: `default._domainkey.impdali.cl` |
| 7 | Buscar TXT _dmarc | OK | No existía (`No records found` filtrando por dmarc) |
| 8 | [CAMBIO] Crear _dmarc | OK | Creado TXT `_dmarc.impdali.cl.` TTL 14400 = `v=DMARC1; p=none; rua=mailto:servicio@impdali.cl` (banner "Success… saved"). 29→30 registros |
| 9 | [CAMBIO] Editar .env | OK | Backup previo `.env.bak_*`. Final: `MAIL_MAILER=smtp` · `MAIL_SCHEME=smtps` · `MAIL_HOST=mail.impdali.cl` · `MAIL_PORT=465` · `MAIL_USERNAME=servicio@impdali.cl` · `MAIL_PASSWORD=[CLAVE OCULTA]` · `MAIL_FROM_ADDRESS=servicio@impdali.cl` · `MAIL_FROM_NAME="DaliGo"` |
| 10 | [CAMBIO] config:cache + re-verificar | OK | `INFO Configuration cached successfully.` · re-verificación imprime `smtp` |
| 11 | Envío de prueba (tinker) | OK | Enviado a `maat.alvarez.t@gmail.com` **sin error/excepción** (prompt volvió limpio). Outlook: no aplica (sin casilla) |
| 12 | Verificación humana en casilla | NO SE PUDO | Pendiente de que revises tu Gmail (ver nota abajo). No puedo abrir tu bandeja ni "Mostrar original" por ti |
| 13 | tail -20 laravel.log | OK | Solo entradas `bsale:sync-*` (última 13:49:54). Sin errores de mailer ni correo volcado a log |
| 14 | /admin/notificaciones → Enviar prueba | OK | Login OK (`mauricio.alvarez@impdali.cl`, clave=[CLAVE OCULTA]). Mensaje: "Notificación de prueba encolada. Revisa la campanita y tu correo." |
| 15 | Esperar cron y verificar fila Mail | OK | Fila más reciente canal **Mail** = **Enviada** (08-07-2026 14:00). Database también Enviada |
| 16 | Verificación final del mailer | OK | Paso 10 = `smtp` (no `log`). Sin bloques de correo volcados al log tras el config:cache (última aparición: ninguna) |

RESUMEN

**VEREDICTO:** APROBADO CON OBSERVACIONES

**PASOS_OK:** 15 de 16 (paso 12 queda NO SE PUDO — requiere tu verificación manual en Gmail)

**CAMBIOS_REALIZADOS:**
- Reseteo de clave de `servicio@impdali.cl` (cuenta preexistente).
- Reparación SPF de impdali.cl vía "Install The Suggested Record" (SPF ahora VALID).
- Creación del registro TXT `_dmarc.impdali.cl.` (TTL 14400, `v=DMARC1; p=none; rua=mailto:servicio@impdali.cl`).
- Edición del `.env` (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME) + backup `.env.bak_*`.
- `artisan config:cache`.

**FALLOS:** ninguno (todos los cambios se aplicaron con confirmación).

**OBSERVACIONES:**
- `servicio@impdali.cl` **preexistía** (uso previo desconocido); su clave anterior no se conocía, por eso se reseteó.
- `servicio@staging.impdali.cl` queda **obsoleta** (no se usa más, no se borró).
- El historial de `/admin/notificaciones` conserva el intento fallido previo con `servicio@staging.impdali.cl` ("Failed to authenticate on SMTP server…"), que era el problema de origen — ya resuelto: la nueva prueba (14:00) quedó **Enviada**.
- Nota de seguridad: las claves de correo se compartieron en el chat; recomiendo rotarlas cuando quieras cerrar el ciclo.

**CAPTURAS:** no aplica (no adjunto archivos).

**ENTREGABILIDAD:** Gmail=PENDIENTE DE TU REVISIÓN · Outlook=NO SE PUDO/no aplica (sin casilla) · SPF=? DKIM=? DMARC=? (pendientes del "Mostrar original" en Gmail)

---

Para cerrar el **paso 12** necesito tu ayuda: revisa tu Gmail (`maat.alvarez.t@gmail.com`) buscando el asunto **"Prueba DaliGo P-M15-10"** y dime si llegó a **RECIBIDOS** o **SPAM**. Si llegó, abre "Mostrar original" y pásame los resultados de **SPF / DKIM / DMARC** (pass/fail). Con eso completo el veredicto de entregabilidad. Nota: envié tanto el correo directo del paso 11 como el de la notificación del sistema (paso 14, ya marcada "Enviada"), así que podrías ver dos mensajes.

### Cierre del paso 12 (verificación humana, 2026-07-08)

El dueño (Mauricio) verificó su Gmail y aportó captura de pantalla: correo **"Prueba DaliGo
P-M15-10"** con etiqueta **Recibidos** (NO spam), remitente "DaliGo", recibido 9:56 a.m. hora
local, cuerpo "Prueba de entregabilidad DaliGo P-M15-10 2026-07-08 13:56:20" — coincide con el
envío del paso 11. **Gmail = RECIBIDOS.** El detalle "Mostrar original" (SPF/DKIM/DMARC pass/fail)
quedó **pendiente-opcional** — ver Observaciones de §3.

## 3. Veredicto

**APROBADO CON OBSERVACIONES** — aceptado por el Director el 2026-07-08.

Lectura nuestra: entregabilidad operativa de punta a punta. La causa raíz del correo roto era la
cuenta equivocada (`servicio@staging.impdali.cl`, autenticación fallida); quedó `servicio@impdali.cl`
con SPF **VALID** (reparado: faltaba la IP del server), DKIM **VALID**, DMARC creado en `p=none`
(monitoreo primero, endurecer después). Prueba real: **Gmail = RECIBIDOS, no spam** (captura del
dueño, 08-07). La fila mail de `/admin/notificaciones` pasó a **Enviada** (14:00) — el mismo panel
que en el QA de P-M15-09 mostraba la Fallida de origen. Bonus M12 verificado: mailer efectivo
`smtp`, cero correos volcados al log post-cambio.

Observaciones abiertas (ninguna bloquea):
1. **"Mostrar original" pendiente-opcional**: los pass/fail de SPF/DKIM/DMARC en cabeceras no se
   leyeron (la llegada a Recibidos es el indicador operativo; los registros están VALID en cPanel).
2. **Outlook sin probar** (no había casilla disponible).
3. **Rotación de claves** (se compartieron por chat durante el setup) → derivada por el Director
   como **R-04** en el tablero de flota; misma política que P-S0-09: la clave nueva jamás pasa
   por un chat.

## 4. Acciones derivadas

- **P-M15-10 → [x]** en RUTA-MAESTRA (este push), con esta evidencia enlazada → **E1·M15 COMPLETA**
  (10/10 pasos, módulo con QA funcional APROBADO en `docs/qa/M15/` + esta verificación de infra).
- Unidad E1 marcada CERRADA en RUTA-MAESTRA (panel §0 y encabezado de la unidad).
- R-04 (rotación de claves de correo) queda en el tablero del Director — no es paso de M15.
- Sin cambios de código derivados (la config vive en el server; el repo no se toca).
