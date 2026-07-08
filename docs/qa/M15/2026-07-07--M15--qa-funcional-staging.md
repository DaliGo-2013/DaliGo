# QA · M15 Notificaciones — funcional staging/producción (cierre P-M15-09) · 2026-07-07

> **Nota de procedencia (stream 2):** la respuesta cruda de la IA ejecutora del QA quedó en manos de
> Mauricio y del Director (no llegó textual a la sesión del stream 2, que lo solicitó dos veces).
> Lo archivado en §2 es el veredicto **tal como llegó al stream 2**: los dictados del Director,
> íntegros y sin editar, más la referencia a su transcripción verificada en el tablero de flota
> (`docs/fleet/TABLERO-3-DIAS.md`, commits `099623c` y `680d8fb`). Nada fue reconstruido ni parafraseado.

## 1. Prompt enviado

```
QA FUNCIONAL — Módulo M15 Notificaciones en staging.impdali.cl
Deploy verificado: commit cfae59a, migraciones notificaciones/preferencias_canal DONE,
seeders DONE. Tu tarea: verificar el flujo completo de una notificación de prueba,
de punta a punta, y reportar con el formato obligatorio del final.

CONTEXTO TÉCNICO (no lo alteres, solo obsérvalo):
- El worker de cola y el scheduler corren por cron en grilla */15 (minutos :00/:15/:30/:45).
  LATENCIA NORMAL de envío/reintento: hasta 15 minutos. NO es un error.
- El canal "database" (campanita) se procesa igual por la cola.
- El correo real puede fallar si el SMTP del server quedó a medio configurar (incidencia
  I-03 lateral). Si falla NO es un fracaso del QA: una notificación "fallida" con error
  registrado + reintento automático posterior es evidencia VÁLIDA del motor de reintentos.

CREDENCIALES: usuario admin de staging «PEGAR usuario» / «PEGAR contraseña».
(Si no existe un admin de prueba, créalo por tinker o usa el habitual de QA.)

PASOS (ejecuta en orden; para cada uno devuelve lo observado TEXTUAL):

1. LOGIN en https://staging.impdali.cl con el admin. Confirma que en la barra de
   navegación aparece el ícono de campana (campanita) junto al menú del usuario.

2. PANEL ADMIN: navega a /admin/notificaciones (menú Administración → Notificaciones).
   Reporta: ¿carga sin error? ¿cuántas filas muestra? (puede estar vacío — OK).

3. ENVIAR PRUEBA: pulsa el botón "Enviar prueba" del panel.
   Reporta el mensaje de confirmación que muestre la página.

4. FILA EN ADMIN: recarga /admin/notificaciones. Deben aparecer filas nuevas del evento
   "sistema.prueba" (una por canal habilitado; al menos canal "database", y "mail" si el
   admin tiene correo). Reporta por CADA fila: evento, canal, destinatario, estado
   (pendiente/enviada/fallida), y si hay error registrado, el texto del error.

5. PROCESAMIENTO POR CRON: espera al próximo slot de la grilla (:00/:15/:30/:45) + 2 min.
   Recarga el panel. Reporta el estado nuevo de cada fila:
   - database → debe pasar a "enviada".
   - mail → "enviada" (SMTP OK) o "fallida" con error + reintento programado (I-03).
   Ambos resultados son reportables; anota cuál ocurrió.

6. CAMPANITA: con la fila database "enviada", recarga cualquier página. La campana debe
   mostrar un contador (1). Ábrela: debe listar la notificación de prueba. Reporta el
   título mostrado.

7. PÁGINA PERSONAL: navega a /notificaciones ("Ver todas" del dropdown de la campana).
   Debe listar la notificación. Pulsa "Marcar leída" (o "Marcar todas"). Reporta:
   ¿el contador de la campana bajó a 0 tras recargar?

8. SOLO SI el paso 5 dio mail FALLIDA: espera 2 slots más de la grilla (~30 min) y
   reporta si la columna intentos aumentó (el reintentador la reclama y reintenta:
   backoff 5/15/60 min, máx 3 intentos). Esto valida el motor de reintentos en vivo.

9. LIMPIEZA: ninguna. Las notificaciones de prueba quedan como evidencia (no borres filas).

NO HAGAS: cambios de crontab, cambios de .env, config:cache, ni tocar tablas a mano.
Todo este QA es de solo lectura + clicks de la UI.

FORMATO DE RESPUESTA OBLIGATORIO:
- VEREDICTO: APROBADO / APROBADO CON OBSERVACIONES / RECHAZADO
- Por cada paso 1-9: número, qué hiciste, salida TEXTUAL observada (copia literal de
  estados, mensajes y errores; capturas si puedes).
- OBSERVACIONES: cualquier cosa rara aunque no bloquee.
Criterio de APROBADO: pasos 1-4 y 6-7 OK, y el paso 5 con database="enviada"
(el resultado del mail NO bloquea el APROBADO si la fallida queda registrada con error
y el motor la reintenta — eso se cierra en P-M15-10).
```

## 2. Respuesta recibida

**Dictado del Director n.º 1 (2026-07-07, vía Mauricio) — íntegro:**

```
DEL DIRECTOR — veredicto QA ACEPTADO (APROBADO CON OBSERVACIONES; el paso 8
quedó cubierto: intentos 1→2 + reprogramación observados en prod, tu claim
atómico y backoff funcionando en vivo — felicitaciones, ese mecanismo pasó
por auditoría, corrección y ahora evidencia de producción).
CIERRE: (1) archiva el veredicto ÍNTEGRO Y SIN EDITAR en docs/qa/M15/
(convención docs/qa/README.md; en TUS notas la falla SMTP se etiqueta
P-M15-10, no I-03); (2) P-M15-09 [x] en RUTA-MAESTRA con evidencia, regla
del mismo push; (3) anota el micro-backlog M15 donde corresponda (panel sin
correo de destino, error truncado en UI, endurecer test de humo de
campanita) — sin construirlo ahora. Con eso E1·M15 queda a UNA delegación
(P-M15-10) de cerrar completa.
PARTE: FLOTA §5 + /usage-credits
```

**Dictado del Director n.º 2 (2026-07-07, vía Mauricio, tras solicitud del texto crudo) — íntegro:**

```
DEL DIRECTOR — veredicto QA ACEPTADO (APROBADO CON OBSERVACIONES). El paso 8
quedó cubierto de sobra: intentos 1→2 + tercera reprogramación observados EN
PRODUCCIÓN, tu claim atómico y el backoff [5,15,60] funcionando en vivo. El
terminal-en-3 no se exige (lo cubre test_job_agota_reintentos_y_queda_
fallida_terminal en la suite).
CIERRE:
1. Archiva el veredicto ÍNTEGRO Y SIN EDITAR en docs/qa/M15/ (convención
   docs/qa/README.md). En TUS notas del archivo, la falla SMTP se etiqueta
   como alcance de P-M15-10 (NO como I-03 — esa era el token de Bsale, ya
   cerrada).
2. P-M15-09 [x] en RUTA-MAESTRA con evidencia, regla del mismo push.
3. Anota el micro-backlog M15 sin construirlo: (a) panel admin no muestra el
   correo de destino, solo el nombre; (b) el error SMTP sale truncado en la
   UI; (c) endurecer test_campanita_visible_en_el_nav (assertear el badge).
4. Tu prompt de P-M15-10 fue revisado: DESPACHAR CON CORRECCIONES — 2
   bloqueantes cazados y corregidos por el Director (quoting bash del $m en
   el tinker del paso 11; lógica circular del 5b: el reseteo de clave ahora
   es incondicional si la cuenta existe). La versión corregida ya está en
   manos de Mauricio para despacho. Anota el gotcha del $m para tu próxima
   redacción de prompts con tinker.
PARTE: FLOTA §5 + /usage. Con el archivo de (1) y la respuesta del despacho,
E1·M15 cierra completa.
```

**Transcripción verificada del Director en el tablero** (`docs/fleet/TABLERO-3-DIAS.md`,
fila Max-2 · P-M15-09, commits `099623c` "QA staging pasos 1-7 OK, paso 8 en espera" y
`680d8fb` "veredicto QA M15 aceptado"): pasos 1-7 OK (database→Enviada, campanita
contador+lectura, página personal, badge a 0; mail→Fallida con error SMTP registrado —
esperado, alcance de P-M15-10); paso 8 cumplido después (intentos 1→2 + tercera
reprogramación observados en producción, backoff `[5,15,60]` exacto al diseño).

## 3. Veredicto

**APROBADO CON OBSERVACIONES** — aceptado por el Director el 2026-07-07.

Lectura nuestra: el canal **database quedó verificado punta a punta en vivo** (enviar prueba →
fila en panel → cola por cron → Enviada → campanita con contador → página personal → marcar
leída → badge a 0). La fila **mail quedó Fallida con el error SMTP registrado**: es el resultado
esperado — la configuración de correo es **alcance de P-M15-10** (etiqueta corregida: NO es I-03;
I-03 era el token de Bsale, ya cerrada). El paso 8 resultó la mejor evidencia del QA: el motor de
reintentos (claim atómico + backoff `[5,15,60]`) quedó **probado en producción** con la fallida
real — intentos 1→2 y tercera reprogramación observados. El caso terminal (fallida definitiva al
agotar 3 intentos) no se exigió en vivo: lo cubre `test_job_agota_reintentos_y_queda_fallida_terminal`.

## 4. Acciones derivadas

- **P-M15-09 → [x]** en RUTA-MAESTRA (este push), con esta evidencia enlazada.
- **Micro-backlog M15** anotado en RUTA-MAESTRA (E1), sin construir: (a) el panel admin no
  muestra el correo de destino, solo el nombre; (b) el error SMTP sale truncado en la UI;
  (c) endurecer `test_campanita_visible_en_el_nav` (assertear el badge).
- **P-M15-10 DESPACHADO CON CORRECCIONES** del Director (2 bloqueantes: quoting bash del `$m`
  en el `tinker --execute` del paso 11; lógica circular del paso 5b → reseteo de clave
  incondicional si la cuenta existe). Gotcha del `$m` registrado en la bitácora de CLAUDE.md.
- Con la respuesta del despacho de P-M15-10, **E1·M15 cierra completa**.
