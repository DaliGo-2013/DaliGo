# Bitácora de sesiones — crónica de la creación de DaliGo

> **Append-only:** nunca se edita una entrada pasada; la más reciente va ARRIBA. Junto con la bitácora
> de errores de `CLAUDE.md` y las evidencias de `docs/qa/`, esto constituye la documentación oficial
> del proceso de creación de la app.

## Formato de entrada

```markdown
### [AAAA-MM-DD] Título de la sesión
- **Quién:** Mauricio + Claude (u otra IA/persona)
- **Objetivo declarado:** paso(s) P-xxx
- **Qué se hizo:** resumen con hashes de commit
- **Pasos marcados:** P-xxx [x], …
- **Decisiones:** D-xxx tomada/creada (o "ninguna")
- **Delegaciones:** enviadas/recibidas → archivo en docs/qa/ (o "ninguna")
- **Próximo paso:** UN paso concreto
```

---

## Sesiones

### [2026-07-08 · tarde] Stream 1 · P-M14-04/05 hechos en rama: escalamiento + `ajustar()` cableado — el motor ya opera E2E
- **Quién:** Mauricio + Claude (Fable 5, Max-1/stream 1) — GO del Director (P-M14-03 verificado [x] por él; I-05 aclarada: malware 2022 real → remediación con Víctor, llaves limpias)
- **Objetivo declarado:** P-M14-04 (escalamiento por scheduler) + P-M14-05 (cablear `ajustar()`).
- **Qué se hizo:** commit `7a01da2` en `feature/m14-aprobaciones`:
  - **P-M14-04:** `Aprobaciones::escalarVencidas()` (pendientes nivel 0 más viejas que `aprobacion_escala_minutos` con regla escalable: lock+re-check por fila — idioma de `notificaciones:reintentar` — y re-notificación `aprobacion.escalada` al rol NUEVO); comando `aprobaciones:escalar` agendado `everyFifteenMinutes()`+`withoutOverlapping(15)` con test que fija la expresión `*/15` (doctrina I-01). Escalada NO es estado: nivel+timestamp+rol reescrito; badge ya estaba en la bandeja.
  - **P-M14-05:** `ProduccionController::ajustar()` pasa por el motor — validación INTACTA, la transacción migró al handler. Magnitud = Σ|Δ| de las 5 cantidades: bajo umbral se auto-aprueba y aplica en el mismo request (UX de siempre + fila histórica); sobre umbral queda pendiente para admin con el reporte INTACTO y flash honesto; el propio admin fluye sin fricción. **Los tests históricos de `ajustar` siguieron verdes SIN cambios** (no siembran la regla → auto-aprueban → cubren la semántica original por construcción).
  - 10 tests nuevos (5+5, incluye el conflicto real: solicitud vieja vs ajuste interino → rechazo automático y el estado interino sobrevive). **Suite 479 verdes.** Gotcha PHP para la posteridad: `*/15` dentro de un docblock CIERRA el comentario — en docblocks se escribe "grilla de 15 min".
- **Pasos marcados:** ninguno en RUTA (rama; al merge de P-M14-07). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** **P-M14-07** (suite + gate `/pre-merge` R-31 + merge a main + QA staging desde celular) — E2·M14 completa 6 de 7 pasos; P-M14-06 (historial admin) puede ir antes o después del merge según dicte el Director.

### [2026-07-08] Servicio Técnico: N° de serie obligatorio solo para dispensador/lavadora (opcional para bombas/herramientas)
- **Quién:** Marco + Claude (Opus 4.8)
- **Qué se hizo:** (rama `feature/serie-condicional-por-tipo`, merge pendiente en este push)
  - Nueva regla: el **N° de serie es obligatorio solo para `dispensador` y `lavadora`** (serie única e importante) y **opcional para `herramienta`/`otro`** (bombas de agua/herramientas no tienen serie única por equipo). Fuente de verdad: constante `OrdenServicio::SERIE_OBLIGATORIA_TIPOS = ['dispensador','lavadora']`.
  - **Validación condicional** (`Rule::requiredIf`) en el form interno (`validateData`) y en el público del QR; `numero_serie` ya era `nullable` en BD, así que se guarda `null` cuando no aplica.
  - **UI en vivo (Alpine):** el asterisco `*` y el atributo `required` aparecen/desaparecen automáticamente al cambiar el "Tipo de equipo" (lee `#tipo_equipo`, mismo patrón que `<x-ayuda-serie>`); cuando es opcional muestra un hint "Opcional para este tipo…". En ambos formularios (interno + QR del cliente).
  - 4 tests nuevos + ajustados 2 de "obligatorios" (numero_serie ya no es incondicional). **Suite 455 verdes.** Build sin cambios de bundle (solo atributos Alpine).
- **Pasos marcados:** ninguno. · **Decisiones:** ninguna formal (regla operativa por el dueño). · **Delegaciones:** ninguna.

### [2026-07-08] Servicio Técnico: Estado editable por el staff al registrar + foto real del N° de serie
- **Quién:** Marco + Claude (Opus 4.8)
- **Qué se hizo:** (rama `feature/estado-editable-staff-y-foto-serie`, merge `f1bfe01`)
  - **Estado editable al registrar (pedido del dueño):** el campo Estado del form de ingreso ST (`_form`) pasó de display bloqueado «Recibido» a un desplegable editable para técnico/admin, para que el staff informe el paso a paso desde el registro (antes solo al editar). `store()` respeta el estado enviado (default `recibido` si no llega); la fecha de entrega la sigue calculando el servidor. **⚠️ REVIERTE la regla previa "el mostrador no decide estado al registrar" — fue pedido explícito del dueño; NO revertir.** El cliente NO cambia: el ingreso por QR no tiene el campo Estado y su store fija `recibido` de forma fija → **fijo para el cliente, editable para el staff**. Test renombrado `test_store_respeta_estado_del_staff_y_calcula_fecha_del_servidor` (antes `..._fuerza_estado_recibido_...`).
  - **Foto real del N° de serie:** reemplazada la ilustración SVG de respaldo por la foto real (etiqueta trasera DALI LB-07D, serial `EST20260100251`), comprimida con GD a 560×420 = **9.9 KB** (<10 KB, pedido del dueño), en `public/img/ejemplo-serie.jpg`; el `<x-ayuda-serie>` ya la detecta con `file_exists` y la muestra en el modal «Ver ejemplo». Suite **451 verdes**; deploy `28971016293` OK.
- **Pasos marcados:** ninguno. · **Decisiones:** ninguna formal (cambio de regla operativa por el dueño, anotado arriba con ⚠️). · **Delegaciones:** ninguna.

### [2026-07-08] Stream 1 · Vigilancia I-01 POSITIVA + P-M14-03 hecho en rama: bandeja móvil de aprobaciones
- **Quién:** Mauricio + Claude (Fable 5, Max-1/stream 1) — orden del día del Director (P-M14-02 verificado [x] por él)
- **Objetivo declarado:** 1º vigilancia crontab (reportada ANTES de codear) · 2º P-M14-03 bandeja móvil.
- **Qué se hizo:**
  - **Vigilancia I-01 (24h): la grilla `*/15` SOBREVIVIÓ** — crontab textual idéntico, latido pleno (las 4 syncs corrieron hoy en sus slots). Correlación I-05 (solo listado de `~/.ssh`): los PHP sospechosos (`blog.php`/`lufi.php`) son de **Nov 2022** (residuo pre-DaliGo, no actividad nueva); PERO hay llave `daligo_github_deploy` + `config` + `known_hosts` tocados HOY 10:55–11:42 — el Director debe confirmar que fue la flota.
  - **P-M14-03 hecho** (`7a14927` en `feature/m14-aprobaciones`): bandeja `/aprobaciones` (pendientes del rol vigente; admin ve todas), Aprobar en 1 tap (h-12 ancho completo; el flash dice la verdad si el handler rechazó por conflicto), Rechazar con motivo OBLIGATORIO vía `x-reason-chips` (3 frecuentes + "Otro", centinela normalizado antes de validar), doble-tap absorbido con flash; «Mis solicitudes» (auth, patrón `/notificaciones`) con `x-aprobaciones.estado-badge` (paleta de 4 por relleno); permisos `aprobar solicitudes` (admin/jefe_bodega/jefe_ventas) + `view aprobaciones` (admin) con labels y matriz de `RoleMatrixSeedTest` al día; nav desktop+móvil con `@can`. 8 tests HTTP nuevos → **suite 469 verdes**. Build commiteado (`app-C0q6yXQH.css`; `npm install` previo por la dep `qrcode` — gotcha documentado) + grep del bundle OK (lg\:flex, lg\:hidden, .h-12, min-w-\[1\.5rem\], sm\:grid-cols-2). **Verificado en preview a 375/768/1280**: sin scroll horizontal, botones 48px, aprobar 1 tap aplicó de verdad (reporte 450→500 en BD), panel de rechazo colapsado, nav horizontal en desktop. Gotcha de preview: el login ahora exige dominio `@impdali.cl` (validación del stream M12) — usuarios de prueba locales deben usar ese dominio.
- **Pasos marcados:** ninguno en RUTA (rama; al merge de P-M14-07). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** P-M14-04 (escalamiento por scheduler) + P-M14-05 (cablear `ajustar()`) en la misma rama, según dicte el Director.

### [2026-07-08] Servicio Técnico: ayuda "¿dónde está el N° de serie?" (ilustración SVG, solo dispensadores)
- **Quién:** Marco + Claude (Opus 4.8)
- **Qué se hizo:** (rama `feature/st-ayuda-serie`) componente reutilizable `<x-ayuda-serie>`: un enlace "¿Dónde encuentro el N° de serie? Ver ejemplo" bajo el campo N° de serie que despliega una **ilustración SVG en línea** (liviana, sin foto ni archivo) de la etiqueta trasera del dispensador con la serie resaltada (ej. `EST20260100251`, empieza con "EST"). **Solo aparece para dispensadores** (lee el select `#tipo_equipo` por id y reacciona a su cambio; bombas/herramientas no tienen serie única). Se usa en el formulario **interno** (`_form`) y en el **público del QR** (`publico/taller/create`). Nota: se eligió ilustración SVG en vez de la foto real porque no era posible comprimir la foto pegada en el chat (no había archivo) — el SVG pesa <2 KB y es escalable. 2 tests (render en interno + público) → 451 verdes.
- **Pasos marcados:** ninguno. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.

### [2026-07-08] Servicio Técnico: el auto-refresco (recarga sola cada 25s) → aviso suave sin recargar
- **Quién:** Marco + Claude (Opus 4.8)
- **Qué se hizo:** (rama `feature/st-aviso-suave-nuevos`) el listado de ST recargaba la página entera cada 25s (para quien tiene `confirmar servicio tecnico`), lo que daba "saltitos" molestos. Se **eliminó el `window.location.reload()`** y se reemplazó por un **aviso suave**: endpoint liviano `servicio-tecnico/por-confirmar/conteo` (JSON, permiso `confirmar servicio tecnico`) que el listado consulta cada 25s en segundo plano (pausado si la pestaña no está visible); si el total de "por confirmar" **subió** respecto a la carga, aparece un banner discreto "Llegaron N ingresos nuevos por QR — Actualizar" (el encargado actualiza cuando quiere, sin saltos). 2 tests nuevos (conteo + permiso). 450 verdes.
- **Pasos marcados:** ninguno. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.

### [2026-07-08] Servicio Técnico: flecha "volver al inicio" en el listado + R de Reparación a naranjo (brand)
- **Quién:** Marco + Claude (Opus 4.8)
- **Qué se hizo:** (rama `feature/st-volver-inicio-r-naranjo`) (1) botón "Volver al inicio" (icon-button `arrow-left` → `route('dashboard')`) en el encabezado del listado de Servicio Técnico, visible para TODOS los que ven el listado (fuera del `@can('manage')`). (2) El badge compacto **R (Reparación) pasó de rojo a `bg-brand-600` (naranjo)** para quedar en paleta. **Actualiza la excepción de paleta:** ahora **solo la G (Garantía) usa verde** (`bg-green-600`) como excepción intencional autorizada; la R ya NO es excepción. 448 verdes.
- **Pasos marcados:** ninguno. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.

### [2026-07-08] Badge de condición compacto (R rojo / G verde) en el listado de ST — excepción de paleta autorizada
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** compactar el badge de condición del listado de Servicio Técnico a una letra para ahorrar espacio.
- **Qué se hizo:** (rama `feature/st-badge-condicion-compacto`) en `admin/servicio-tecnico/index.blade`, el badge de condición pasa de la palabra completa a un **cuadrito de 1 letra**: **R** con `bg-red-600` (Reparación) y **G** con `bg-green-600` (Garantía), letra blanca + `title` (tooltip con la palabra). El detalle (`show`) mantiene la palabra completa. 446 verdes.
- **⚠️ Excepción de paleta (autorizada por el dueño — NO revertir en pre-merge):** la paleta estricta de DaliGo prohíbe el verde y reserva el rojo para lo destructivo. El dueño pidió y **aprobó explícitamente** R rojo / G verde para distinción visual rápida en el mostrador. Es una excepción consciente y acotada a ESTE badge; se documenta aquí para que la auditoría R-31 no la marque como violación.
- **Pasos marcados:** ninguno. · **Decisiones:** ninguna (preferencia visual del dueño). · **Delegaciones:** ninguna.
- **Próximo paso:** el dueño reprueba el correo del QR (la flota ya cerró la entregabilidad en P-M15-10 [x], incluido el mailer de M12).

### [2026-07-08] Stream 2 · E1·M15 CERRADA — primera unidad completa de la flota (kickoff→producción en 6 días)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8 · high**) + IA-cPanel — dictado de cierre final del Director
- **Objetivo declarado:** archivar la respuesta de P-M15-10 + [x] + cerrar la unidad E1 (mismo push)
- **Qué se hizo:** respuesta de la IA-cPanel archivada ÍNTEGRA en `docs/qa/INFRA/2026-07-08--INFRA--entregabilidad-correo-p-m15-10.md` (**APROBADO CON OBSERVACIONES**, 15/16 pasos; el paso 12 cerrado con captura del dueño: **Gmail=RECIBIDOS, no spam**; "Mostrar original" con SPF/DKIM/DMARC en cabeceras = pendiente-opcional, anotado como observación). Resultado técnico: causa raíz del correo roto era `servicio@staging.impdali.cl` con auth fallida → queda `servicio@impdali.cl`; **SPF reparado a VALID** (faltaba `ip4:108.167.161.119`), DKIM VALID, **DMARC creado** (`p=none`, monitoreo); `.env` MAIL_* definitivo + `config:cache`; fila mail del panel = **Enviada** (14:00) — el mismo panel que mostraba la Fallida de origen; bonus M12 verificado (mailer `smtp`, cero correos al log). Rotación de claves derivada como **R-04** (tablero del Director). **P-M15-10 [x]** → **E1·M15 CERRADA**: 10/10 pasos, QA funcional APROBADO en `docs/qa/M15/` + verificación de infra en `docs/qa/INFRA/`; encabezado de unidad y panel §0 marcados.
- **Pasos marcados:** **P-M15-10 [x] → E1 CERRADA** (M15 agregado a "Hecho" del panel §0).
- **Decisiones:** ninguna (R-04 es del tablero del Director).
- **Delegaciones:** P-M15-10 recibida y archivada (APROBADO CON OBSERVACIONES).
- **Próximo paso:** asignación del Director para el stream 2 (recomendación en el parte FLOTA §5: micro-backlog M15 como S inmediato + nueva unidad como plato fuerte).

### [2026-07-08] Stream 2 · P-M15-09 [x] CERRADO: veredicto QA archivado — M15 vivo en producción con QA aprobado
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8 · high**) — dictado de cierre del Director
- **Objetivo declarado:** archivo del veredicto QA + P-M15-09 [x] + micro-backlog + gotcha tinker (mismo push)
- **Qué se hizo:** veredicto **APROBADO CON OBSERVACIONES aceptado** archivado en `docs/qa/M15/2026-07-07--M15--qa-funcional-staging.md` (4 bloques según convención; el texto crudo de la IA ejecutora no llegó al stream — se archivaron los dictados del Director íntegros + referencia al tablero, con **nota de procedencia** explícita; la falla SMTP etiquetada **alcance P-M15-10**, no I-03). Lo notable del QA: el motor de reintentos quedó **probado en producción** (mail fallida real → intentos 1→2, tercera reprogramación, backoff `[5,15,60]` exacto; el caso terminal lo cubre `test_job_agota_reintentos_y_queda_fallida_terminal`). **P-M15-09 [x]** en RUTA con evidencia enlazada. Micro-backlog M15 anotado sin construir (correo de destino ausente en panel; `ultimo_error` truncado en UI; endurecer `test_campanita_visible_en_el_nav`). P-M15-10 **despachado con correcciones** del Director (quoting bash del `$m` en tinker + lógica circular del 5b → reseteo incondicional) — gotcha registrado en CLAUDE.md. Merges docs-only de main plegados sin conflicto (P-M14-01/02 de stream 1, fix portada).
- **Pasos marcados:** **P-M15-09 [x]**. E1·M15 queda a UNA delegación de cerrar (P-M15-10).
- **Decisiones:** ninguna.
- **Delegaciones:** QA-FUNCIONAL recibida (vía dictados del Director) y archivada; P-M15-10 despachada por Mauricio (versión corregida del Director).
- **Próximo paso:** respuesta del despacho P-M15-10 → archivo en `docs/qa/INFRA/` (o M15) + cierre → **E1·M15 COMPLETA**.

### [2026-07-08] Portada: quitada la entrada pública a servicio técnico (reducir exposición)
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** sacar de la home el bloque «¿Vas a ingresar un producto a servicio técnico?» por riesgo de exposición pública, SIN romper el flujo (los QR del mostrador lo siguen usando).
- **Qué se hizo:** (rama `fix/portada-sin-entrada-st`) removido el selector Alpine de la portada (`welcome.blade`) y revertida la ruta `/` a `view('welcome')` (ya no consulta sucursales). Se **MANTIENE intacto** todo el flujo: ruta pública `ingreso-taller.*` (link firmado + throttle + honeypot), formulario, confirmación del encargado, y la página admin **«Códigos QR»** para imprimir. El ingreso se alcanza ahora **solo escaneando el QR físico** del mostrador, no desde la home. El test de portada pasó a `assertDontSee`. **444 verdes.** `view:clear` + build.
- **Pasos marcados:** ninguno (P-M12-01 sigue [EN CURSO]). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** imprimir los QR desde la página admin y pegarlos en el mostrador; verificar entrega del correo (SMTP desde `staging.impdali.cl`).

### [2026-07-07 · tarde] Stream 1 · P-M14-02 hecho en rama: el corazón del motor de aprobaciones
- **Quién:** Mauricio + Claude (Fable 5, Max-1/stream 1) — GO del Director (P-M14-01 verificado [x] por él)
- **Objetivo declarado:** P-M14-02 — servicio `Aprobaciones` + contrato `AccionAprobable` + handler + excepciones, con la batería de tests dictada.
- **Qué se hizo:** commit `5470f31` en `feature/m14-aprobaciones`: `Aprobaciones::solicitar()` (auto-aprueba sin regla activa / bajo umbral / solicitante-aprobador, aplicando el handler INLINE en la misma transacción; pendiente sobre umbral o con `monto=null` bajo regla con umbral — contrato conservador), `aprobar()`/`rechazar()` con lock+re-check (doble-tap → `AprobacionYaResueltaException`; rol vigente o admin), handler `AjusteReporteProduccion` con re-check de `updated_at` (conflicto → rechazo automático en la MISMA transacción, objetivo intacto). Eventos `aprobacion.solicitada/escalada/resuelta` registrados en `Notificacion::EVENTOS` (sin guard `class_exists` — aceptado por el Director; anotar al re-sellar el plan) y notificación real vía dispatcher M15 (post-transacción; auto-aprobadas NO notifican). 10 tests nuevos (batería completa del dictado). Ajuste a un test de M15: `PreferenciasCanalTest` hardcodeaba 2 filas esperadas — ahora deriva de `count(EVENTOS)*2` (el catálogo está DISEÑADO para crecer; con cada módulo integrándose el hardcode rompía). **Suite 461 verdes.**
- **Pasos marcados:** ninguno en RUTA (rama; se marcan al merge de P-M14-07 — P-M14-01 ya verificado [x] por el Director en tablero). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** 08-07 al abrir: `/model claude-opus-4-8` PRIMERO + vigilancia crontab (reportar al Director ANTES de seguir) → P-M14-03 (bandeja móvil).

### [2026-07-07 · tarde] Stream 1 · P-M14-01 hecho en rama: esquema del motor de aprobaciones
- **Quién:** Mauricio + Claude (Fable 5, Max-1/stream 1) — visto bueno de Mauricio a PLAN-M14 dado
- **Objetivo declarado:** P-M14-01 (esquema del motor, PLAN-M14 §1.2) en la rama `feature/m14-aprobaciones`.
- **Qué se hizo:** commit `5d9286d` en la rama (nacida DESPUÉS del merge de M15 → el dispatcher ya está disponible; el guard `class_exists` de P-M14-02 quedó innecesario): migración `aprobaciones`+`reglas_aprobacion` (MySQL 5.7-safe, aditiva), modelos `Aprobacion`/`ReglaAprobacion` auditables (consts, scope `paraRol`, `umbral()` vía Configuracion), `ReglasAprobacionSeeder` (1 regla: ajuste de producción → admin, umbral en unidades) + claves `umbral_ajuste_produccion_unidades`=50 y `aprobacion_escala_minutos`=30, registro en `AuditController::MODELOS`, y `AprobacionesSchemaTest` (6 tests: idempotencia ×2, defaults, umbral vía config, morph+payload, scope, auditables). `migrate:fresh --seed` + segunda pasada verdes; **suite completa 451 verdes** (CI no corre en ramas → local obligatoria).
- **Pasos marcados:** ninguno en RUTA (precedente M15: los `[x]` de trabajo en rama se marcan al aterrizar en main, en el merge de P-M14-07; avance fino en el tablero del Director). · **Decisiones:** ninguna (diseño ya aprobado en PLAN-M14). · **Delegaciones:** ninguna.
- **Próximo paso:** P-M14-02 (servicio `Aprobaciones` + handler + tests) en la misma rama; antes, al abrir el 08-07: vigilancia crontab (¿sobrevive `*/15`?) y `/model` Opus 4.8.

### [2026-07-07 · tarde] Stream 2 · P-M15-09 fase 2: M15 mergeado a main = DEPLOY (doble llave Director+Mauricio)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8 · high**) — OK definitivo con doble llave (verificación del Director en tablero `5fd638a`)
- **Objetivo declarado:** P-M15-09 micro-secuencia final — plegar el avance docs-only de main, push a main, deploy
- **Qué se hizo:** fetch trajo `dff13c7..8fb6763` (3 commits docs-only: fleet + PLAN-M14 — dentro de lo anunciado, sin freno); 1 conflicto solo-docs (esta bitácora, interleave). **Corrección dictada en main aplicada:** `notificaciones:reintentar` de `everyFiveMinutes()`→`everyFifteenMinutes()` (grilla `*/15` de I-01; con `*/5` disparaba en la grilla pero mentía la cadencia) + test nuevo `test_reintentar_agendado_en_la_grilla_de_15` que fija expresión `*/15 * * * *` y `withoutOverlapping` (misma doctrina que `ScheduleBsaleTest`). Gotcha del merge anterior registrado en CLAUDE.md (dep npm `qrcode` → `npm install` antes del build post-merge; package-lock `name` reescrito por el nombre de la carpeta local). Suite verde → push a main = deploy.
- **Pasos marcados:** P-M15-09 [~] → fase deploy hecha; **cierra SOLO con veredicto APROBADO del QA staging archivado en docs/qa/**.
- **Decisiones:** ninguna (la corrección de cadencia ya estaba dictada por el Director vía RUTA de main).
- **Delegaciones:** prompt QA-FUNCIONAL-STAGING redactado y entregado vía Mauricio (correo real cuando aplique + campanita + página personal + fila admin + botón prueba + latencia ≤15 min).
- **Próximo paso:** despacho del QA a IA-cPanel → veredicto APROBADO cierra P-M15-09 → P-M15-10 (SPF/DKIM/DMARC).

### [2026-07-07 · tarde] Arranca E2: PLAN-M14 sellado (motor de aprobaciones) + I-03 verificada cerrada
- **Quién:** Mauricio + Claude (Fable 5, Max-1/stream 1) — dictado del Director (arranque E2 · M14)
- **Objetivo declarado:** primer entregable de E2 = `docs/planes/PLAN-M14.md` con sello de vigencia (patrón PLAN-M15), SIN código; visto bueno de Mauricio antes de la primera migración.
- **Qué se hizo:**
  - **`docs/planes/PLAN-M14.md` sellado** (verificado contra `bf7ae27`): motor polimórfico `aprobaciones`+`reglas_aprobacion`; ejecución de la acción aprobada por payload+handlers (`AccionAprobable`) con re-check de `updated_at` bajo lock (conflicto → rechazo automático, jamás payload obsoleto); auto-aprobación con fila histórica (Héctor 5→1-2); escalamiento `everyFifteenMinutes()` alineado a la grilla `*/15` de I-01 (granularidad 15 min documentada como límite); eventos M15 post-merge con guard `class_exists` para construir 5 de 7 pasos ANTES del merge; permisos `aprobar solicitudes`/`view aprobaciones`; 1 regla semilla (`produccion.ajuste_reporte` → admin, umbral 50 unidades Σ|Δ|). Exploración con 2 agentes + 1 arquitecto; verificación del documento con workflow de 3 lentes antes del push.
  - **Riesgo del merge M15 elevado al Director:** el `notificaciones:reintentar` de la rama usa `everyFiveMinutes()` (dispara en la grilla pero degrada en silencio a 15 min; corregir a `everyFifteenMinutes()` al mergear). El riesgo de la grilla vieja en su `console.php` ya NO existe: Max-2 mergeó main (`dff13c7`) en la rama hoy 13:15 (`00297d5`) — verificado por el workflow de 3 lentes.
  - **I-03 VERIFICADA CERRADA:** token renovado por Mauricio 07-07; el deploy de `aa10d2b` re-cacheó config y las 4 syncs corrieron OK en sus slots nuevos (prices 16:31, stock 16:49, catalog 17:00, clients 17:23 UTC) — espejo descongelado. De paso, la grilla `*/15` quedó verificada con syncs REALES en los 4 slots.
- **Pasos marcados:** ninguno (el plan no es un P-xxx; P-M14-01..07 arrancan tras el visto bueno). · **Decisiones:** diseño M14 propuesto en el plan — las 3 que requieren visto bueno humano están en PLAN-M14 §4. · **Delegaciones:** ninguna (PLAN-M14 §5: el cron ya existe por I-01).
- **Próximo paso:** Mauricio da el visto bueno a PLAN-M14 (o corrige) → P-M14-01 en `feature/m14-aprobaciones`; Max-1 mañana 08-07: vigilancia crontab (¿sobrevive `*/15`?).

### [2026-07-07] Stream 2 · P-M15-09 fase 1: merge de main a la rama (444 verdes, sin push a main)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8 · high**) — GO definitivo del Director tras verificar precondiciones (P-SPK-03 en main `d1db5ef`, I-01 cerrada)
- **Objetivo declarado:** P-M15-09 secuencia §5.6 — plegar `origin/main` (`dff13c7`) a `feature/m15-notificaciones`
- **Qué se hizo:** freno inicial correcto (precondiciones □ verificadas en main antes de tocar, no asumidas); recon del merge-base `35e72db` endosado por el Director (2 correcciones a su brief: `navigation.blade.php` SÍ chocaba por el `@canany` de `7e2f552`; `CLAUDE.md` entraba limpio). Merge con 6 conflictos, todos resueltos por unión de ambos lados: `config/permissions.php` + `RolesAndPermissionsSeeder` (permisos `confirmar servicio tecnico` de M12 **y** `view notificaciones` de M15), `RoleMatrixSeedTest` (admin con ambos; jefe_bodega/tecnico solo M12), RUTA (mis `[x]` + dato I-01 de main plegado en P-M15-03), BITACORA (interleave newest-first vía awk), `public/build/manifest.json` (regenerado). Nav y rutas **auto-mergearon** conservando ambos (verificado). Build: `npm install` primero (main trajo dep `qrcode` — Rollup fallaba sin ella) → `view:clear` + `npm run build` → grep literal 4/4 (`lg\:flex`, `lg\:hidden`, `.w-80` campanita, `bg-white\/60` PWA). **Suite 444 verdes** (sobre ~430 esperada; cero cruce entre streams). Push SOLO a la rama; main intacto.
- **Pasos marcados:** P-M15-09 [~] (fase merge lista; deploy+QA esperan OK).
- **Decisiones:** ninguna (doctrina I-01 `*/15` ya dictada; QA staging la respetará: latencia reintentos ≤15 min).
- **Delegaciones:** ninguna en esta fase (QA-FUNCIONAL-STAGING y P-M15-10 SPF/DKIM salen post-deploy).
- **Próximo paso:** OK explícito Director+Mauricio → merge a main = deploy → prompt QA-FUNCIONAL-STAGING a IA-cPanel; P-M15-09 cierra SOLO con APROBADO.

### [2026-07-07] I-01 cerrada en modo compatibilidad + P-SPK-03 cerrado (spike PWA completo) + I-03 abierta
- **Quién:** Mauricio + Claude (Fable 5, Max-1/stream 1) — dictados del Director
- **Objetivo declarado:** cerrar P-SPK-03 (Tarea 2: archivar I-01) + F-01 (hecha antes, `07dbe92`: recetario como apoyo automático — regla en CLAUDE.md + descriptions auto-trigger de las 3 skills).
- **Qué se hizo:**
  - Al completar el paso 5 del corrector (verificación `:50` por SSH) se destapó la **TERCERA reescritura** del crontab (`*/19` + `*/15`, 03-07): syncs clients/prices/stock muertas desde el 03-07. Causa raíz aceptada por el Director: **HostGator estrangula crons por-minuto** → **I-01 CERRADA en modo compatibilidad**: crontab `*/15` en ambas líneas (aplicado por SSH 10:38 CDT, before/after archivado) + syncs re-agendadas a la grilla :00/:15/:30/:45 en `routes/console.php` + test que fija la grilla. Evidencias: `docs/qa/INFRA/2026-07-02--INFRA--i01-corrector-scheduler.md` (respuesta íntegra del corrector) y `docs/qa/INFRA/2026-07-07--INFRA--i01-cierre-modo-compatibilidad.md` (timeline completa). CLAUDE.md §Deploy + bitácora [2026-07-07] + HANDOFF actualizados (doctrina vieja marcada superada).
  - **I-03 detectada y abierta:** token Bsale muerto (401) desde el 06-07 ~16:00 CDT — espejo congelado; la destraba SOLO Mauricio (panel Bsale → token directo al `.env` del server, jamás por chat). Lateral 2: correos del taller (M12) volcados al log — config cacheada `mail.default=log` vs `.env` `smtp` a medio experimentar (`MAIL_HOST=staging.impdali.cl`); el deploy de este push re-cachea → fallos visibles pero elegantes (try/catch de M12); cierre real = P-M15-10. Órdenes #000005/#000006: el cliente nunca recibió su correo.
  - **P-SPK-03 CERRADO:** memo `docs/SPIKE-PWA.md` sellado (7 secciones, verificado contra el código, guardarraíl golden-hash de `offline.blade.php`↔`CACHE` en `PwaTest`); la prueba de campo la ejecutó el dueño el 06-07 y el Director la verificó con capturas (tablero día 3, `50f8878`): A OK, B OK, 4/4 tandas sin duplicados, motivo por tanda intacto → §6 del memo completada con esos resultados. Con esto el **spike PWA queda completo** (P-SPK-01/02/03) y gobierna M08.
  - De la verificación pre-push (workflow de 3 lentes, 15 hallazgos): se corrigieron en este mismo push los restos PRESCRIPTIVOS de la doctrina vieja del cron en `GUIA-DALIGO.md`, `PROYECTO_DALIGO.md`, `RUTA-MAESTRA` (P-M15-03 y base E3), `KICKOFF-E1-M15.md`, plantilla `VERIFICACION-CPANEL.md` y `BSALE_API.md`, más el §8f de HANDOFF stale (M12 ya estaba LIVE) y el sello del memo. El tablero de flota (territorio del Director) quedó con 2 ajustes pendientes, reportados en el parte.
- **Pasos marcados:** P-SPK-01 hash `ee01204`, P-SPK-02 hash `793bfcc` (estampados), P-SPK-03 `[x]`. · **Decisiones:** doctrina de infra I-01 (grilla `*/15`) dictada por el Director, registrada en CLAUDE.md. · **Delegaciones:** corrector I-01 recibida y archivada; borrador de pregunta a soporte HostGator entregado al Director (pendiente de despacho).
- **Próximo paso:** Mauricio — reponer token Bsale (I-03, panel Bsale → `.env` directo); Max-1 — vigilancia 24h de que la grilla `*/15` sobrevive al reescritor.

### [2026-07-06] Permiso «confirmar servicio técnico»: el jefe de bodega autoriza la recepción del QR
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** que el **jefe de bodega** autorice la recepción de lo que llega por QR (revisa que los datos estén bien) y luego el técnico repare; y dejar el rol del técnico de ST (Fernando St) con todos los permisos.
- **Qué se hizo:** (rama `feature/st-permiso-confirmar`)
  - **Permiso nuevo `confirmar servicio tecnico`** (seeder idempotente) → asignado a `jefe_bodega` (autoriza, **sin** `manage` → no ingresa/edita) y a `tecnico` (ST completo). `admin` lo tiene por defecto.
  - La ruta `confirmar`, el bloque **«Por confirmar»**, el botón «Confirmar recepción» y el auto-refresco pasan a gatearse con ese permiso (antes `manage`). «Revisar» apunta al **detalle** (show) para que el jefe (solo lectura) lo abra.
  - El menú muestra «Servicio Técnico» con `view|manage` (antes solo `manage`) para que el jefe de bodega llegue al listado.
  - 2 tests nuevos (jefe_bodega confirma + correo; vendedor solo-lectura → 403) → **401 verdes**.
  - **Usuario Fernando St:** el rol `tecnico` YA trae todos los permisos de ST (view + manage + confirmar). El usuario se crea por la UI de admin (Usuarios → Crear, rol `tecnico`) o por la IA de cPanel — **no se seedea con contraseña** en el repo.
- **Pasos marcados:** ninguno (P-M12-01 [EN CURSO]). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** crear el usuario Fernando St (UI) + QA del correo (confirmar orden → Gmail/Spam).

### [2026-07-06] Formulario QR: paso previo «¿Cómo desea ingresar?» (código de barras «Pronto» | manual)
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** dejar lista para presentar al jefe la opción FUTURA de ingreso por código de barras (con pistola lectora), sin construir el backend todavía.
- **Qué se hizo:** (rama `feature/qr-modo-ingreso`) pantalla de elección previa al formulario público (Alpine `modo`):
  - **«Con código de barras»** (badge **Pronto**) → vista de preview que explica el flujo futuro (al escanear se autocompleta modelo/factura/garantía/dónde-se-compró; el cliente solo ingresa nombre/correo/teléfono/RUT) + botón «por ahora, ingresar manualmente». **NO envía aún** (falta la pistola y el enlace a las compras) — es demo/placeholder.
  - **«Ingresar manualmente»** → el formulario actual completo (equipos antiguos sin código).
  - Si hay errores de validación, abre directo el modo manual (el cliente ve sus errores). 1 test nuevo → **399 verdes**. `view:clear` + build.
- **Pasos marcados:** ninguno (P-M12-01 [EN CURSO]). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** QA del correo (confirmar orden como encargado → ver Gmail/Spam); a futuro, hacer funcional el ingreso por código de barras cuando llegue la pistola lectora.

### [2026-07-06] Portada: entrada pública a servicio técnico (pregunta → sucursal → QR)
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** que desde la portada (sin cuenta) se pueda entrar a servicio técnico eligiendo sucursal y viendo su QR.
- **Qué se hizo:** (rama `feature/portada-ingreso-qr`)
  - `welcome.blade.php`: selector Alpine de 3 pasos — «¿Vas a ingresar un producto a servicio técnico?» → botones de sucursal → QR firmado de esa sucursal (dibujado en el cliente con el mismo `canvas[data-qr]` + import dinámico de `qrcode`) + link «continúa aquí en este dispositivo». La ruta `/` pasa las sucursales con try/catch (la home nunca revienta si la BD no está lista; ExampleTest sigue verde).
  - **Sucursales de ST configurables:** `config/servicio_tecnico.php` → `sucursales_recepcion` (MIRADOR, COQUIMBO, ABATE-MOLINA) + scope `Sucursal::recepcionServicioTecnico()`. **Buzeta excluida** (no recibe ST) tanto en la portada como en la página de QR admin (antes mostraba las 4 activas).
  - Verificado `route:cache` OK (el closure de `/` es cacheable → sin riesgo de deploy). 3 tests nuevos/ajustados → **398 verdes**. `view:clear` + build.
- **Pasos marcados:** ninguno (P-M12-01 sigue [EN CURSO]). · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** QA real del correo (confirmar la orden como encargado → ver si llega a Gmail/Spam) + rotar clave SMTP.

### [2026-07-06] SMTP en prod + formulario del QR: código del producto (buscador de catálogo) y fecha de hoy
- **Quién:** Marco + Claude (Opus 4.8) + IA de cPanel
- **Objetivo declarado:** dejar operativo el correo del piloto P-M12-01 e iterar el formulario con feedback de prueba en vivo.
- **Qué se hizo:**
  - **SMTP configurado en producción** (delegación a IA-cPanel, veredicto APROBADO CON OBSERVACIONES): cuenta `servicio@impdali.cl`, `.env` con `MAIL_MAILER=smtp` / `mail.impdali.cl:465` / `smtps` / from `servicio@impdali.cl`; `config:cache`; prueba de envío `ENVIADO_SIN_ERROR`. El correo del QR ya se entrega de verdad. **Pendiente:** rotar la clave (quedó en `~/.bash_history` durante el setup) + prueba de entregabilidad a un dominio externo.
  - **Formulario público del QR** (rama `feature/qr-form-producto-fecha`): campo **"Código del equipo (producto Dali)"** con autocompletado del catálogo (SKU/nombre) reusando `<x-buscador-remoto>`; endpoint público nuevo `ingreso-taller/buscar-producto` (sin auth, `throttle:30,1` por ser autocompletado; solo lee SKU/nombre). `producto_id` validado (`exists`) y guardado, y ahora sale en el correo. Campo **"Fecha de ingreso" = hoy** (solo lectura; el servidor sigue forzando la fecha).
  - 5 tests nuevos (búsqueda pública, mínimo 2 caracteres, guarda `producto_id`, rechaza producto inexistente, render de código+fecha) → **396 verdes**. `view:clear` + build (CSS 43 kB).
- **Pasos marcados:** ninguno (P-M12-01 sigue [EN CURSO]). · **Decisiones:** ninguna. · **Delegaciones:** SMTP (IA-cPanel) — reporte APROBADO CON OBSERVACIONES.
- **Próximo paso:** rotar la clave del correo (prompt entregado) + QA real end-to-end (QR → enviar con un Gmail → confirmar → recibir el correo, ver si cae en Recibidos o Spam).

### [2026-07-06] Ingreso a servicio técnico por QR (piloto de P-M12-01) + historial compartido con separación por sucursal
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** adelantar **P-M12-01** como piloto — que un cliente ingrese su máquina al taller escaneando un QR del mostrador, **sin crearse usuario**, y reciba el folio por correo.
- **Qué se hizo:** (rama `feature/m12-ingreso-qr-piloto`, **sin mergear aún** — no se puede marcar `[x]` sin QA staging)
  - **Flujo público por QR:** ruta sin auth `ingreso-taller` con link **firmado** (`URL::signedRoute`, sucursal embebida) + `throttle:6,1` + honeypot; `App\Http\Controllers\Publico\IngresoTallerPublicoController` (create/store/gracias); vistas `publico/taller/*` sobre `<x-guest-layout>` (mobile-first). El envío crea una orden **real** (`fuente='qr'`, `estado='recibido'`, `confirmada_at=null`) — NO un pre-ingreso aparte.
  - **Confirmación del encargado:** `ServicioTecnicoController::confirmar` (permiso `manage`, `lockForUpdate` anti doble-envío) setea `confirmada_at` y AHÍ dispara el correo. Bloque "Por confirmar (QR)" + auto-refresco liviano en el índice; botón en el detalle.
  - **Correo piloto standalone:** `App\Mail\IngresoTallerRecibido` + `emails/taller/recibido` (mailer nativo `config/mail.php`). Migrable al motor **M15** (evento `taller.recibido`) cuando llegue a main.
  - **QR imprimible:** página admin `servicio-tecnico/qr` (un QR firmado por sucursal), dibujado en el cliente con `qrcode` (npm) vía **import dinámico** → chunk aparte, no engorda el bundle global; assets `public/build` commiteados.
  - **Historial compartido + separación por sucursal de recepción** (pedido del dueño): el listado ya era compartido por las 3 sucursales; se agregó **filtro "Sucursal (recepción)"** + badge "Recibido en X" por fila; en el detalle se rotula **"Se repara en Mirador (casa matriz)"** cuando la recepción NO fue central (Coquimbo/Abate reciben pero no reparan). Derivado de `es_central`, **sin campo nuevo**.
  - **Migración** `…140000_add_email_y_confirmacion_a_ordenes_servicio` (idempotente): `cliente_email`, `confirmada_at`.
  - **Tests:** 14 del flujo público (`IngresoTallerPublicoTest`) + 3 del módulo (filtro por sucursal; detalle repara-en-Mirador) → **391 verdes**.
- **Pasos marcados:** ninguno `[x]`; **P-M12-01 [EN CURSO]** (piloto en rama; falta QA staging + merge por el gate). · **Decisiones:** ninguna (regla Mirador-casa-matriz la confirmó el dueño; correo standalone-vs-M15 = decisión de implementación del piloto). · **Delegaciones:** ninguna.
- **Próximo paso:** QA en staging del flujo QR (escanear → enviar → confirmar → correo real con SMTP) + gate `/pre-merge` antes de mergear a main; luego migrar el correo a M15.

### [2026-07-04] Stream 2 · día 4: 4 correcciones de auditoría + preferencias + tests (P-M15-07/08)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8**)
- **Objetivo declarado:** 4 correcciones del Director (gate del merge) + P-M15-07 + P-M15-08
- **Qué se hizo:** **Correcciones:** (1) página personal `/notificaciones` mobile-first (lista in-app propia + marcar leída; el link móvil y el "Ver todas" del dropdown apuntan ahí) + panel admin agregado al dropdown Administración; (2) `leer` exige `canal=database` (404 si no — antes sacaba una mail/whatsapp fallida del reintentador); (3) `withoutOverlapping(10)` en el reintentador (TTL, convención); (4) **barrido self-healing** de pendientes huérfanas (crash post-claim): el mismo comando reclama `pendiente` con `updated_at <= now()-10min`, las toca y re-despacha (idempotente por el guard del job). **Menores:** claim limpia `programada_para`; campanita computa sus 2 queries una vez en el nav (antes 3); test de humo endurecido (assertea el badge del conteo). **P-M15-07:** preferencias en el perfil (`prefs[evento][canal]` con `updateOrCreate`); el dispatcher las respeta (test form→dispatch). **P-M15-08:** cobertura del checklist. **Suite 405 verdes.** Bug atrapado por test: `data_get($m, "sistema.prueba.mail")` interpreta el punto del evento como anidación → habría ignorado el opt-in; corregido a acceso directo `$m[$evento][$canal]`.
- **Pasos marcados:** P-M15-07 [x], P-M15-08 [x]; 4 correcciones + menores aplicadas.
- **Decisiones:** ninguna.
- **Delegaciones:** ninguna nueva (P-M15-10 SPF/DKIM pendiente).
- **Próximo paso:** **P-M15-09** — merge coordinado con Mauricio (fetch → merge origin/main → resolver docs → `view:clear`+`npm run build`+grep → suite verde → autoriza merge a main) + QA staging (plantilla QA-FUNCIONAL, IA-cPanel).

### [2026-07-04] Stream 2 · día 3: reintentador atómico + panel + campanita (P-M15-05/06)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8**) + 3 exploradores paralelos
- **Objetivo declarado:** P-M15-05 + P-M15-06, dictado del Director
- **Qué se hizo:** (1) **P-M15-05**: comando `notificaciones:reintentar` ATÓMICO (`DB::transaction` + `lockForUpdate` sobre fallidas vencidas con `intentos < max` → claim por UPDATE a pendiente → dispatch solo lo reclamado; robusto a scheduler degradado: reclama por `programada_para <= now()`, no por cadencia — vale si I-01 reincide) agendado `everyFiveMinutes()->withoutOverlapping()`; panel `/admin/notificaciones` read-only (patrón AuditController: filtros validados `Rule::in`, paginado, `x-list-card`/`x-badge`) + botón "enviar prueba"; permiso `view notificaciones` aditivo + label; `PreferenciaCanal` en `AuditController::MODELOS`. (2) **P-M15-06**: icono `bell`, partial `campanita` (contador + dropdown 5 últimas + marcar leída/todas), bandeja personal en rutas `auth` (NO admin; `abort_unless` dueño, 403 ajeno), cambio mínimo en `navigation.blade.php` (desktop + móvil), `npm run build`. (3) `RoleMatrixSeedTest` (existente) rojo por mi permiso nuevo → agregado `view notificaciones` al esperado del admin. **Suite 397 verdes.** Gotcha: el grep del bundle marcó `lg:flex` MISSING = falso negativo (el CSS escapa `.lg\:flex`); con match literal, presente. Colisión con main (P-SPK-02): cero en código.
- **Pasos marcados:** P-M15-05 [x], P-M15-06 [x].
- **Decisiones:** ninguna. · **Delegaciones:** ninguna nueva.
- **Próximo paso:** P-M15-07 (preferencias/opt-out por usuario) + P-M15-08 (tests integrales) → P-M15-09 (merge coordinado + QA staging).

### [2026-07-04] Stream 2 · día 2: seeds de configuración M15 + prompt del cron de cola
- **Quién:** Mauricio + Claude (Max-2 · Forjador B) — nota consumo: sesión aún en Fable 5 (dictado pedía Opus 4.8; el switch de modelo es de Mauricio)
- **Objetivo declarado:** P-M15-03 (delegación) + P-M15-04 (seeds), dictado del Director día 2
- **Qué se hizo:** (1) **P-M15-04**: 4 claves `notif_*` en `ConfiguracionSeeder` (plantilla json del evento de prueba, reintentos max, backoff, nombre de remitente placeholder D-001) + 4 tests (idempotencia 2×, casteo por tipo, re-seed no pisa valor editado, dispatcher renderiza con la plantilla sembrada) — verificado además con doble `db:seed` manual (4 claves, 0 duplicados); **suite 382 verdes local**. (2) **P-M15-03**: prompt de delegación del cron `queue:work --stop-when-empty --max-time=55` redactado sección por sección con la plantilla VERIFICACION-CPANEL (estado previo textual → [CAMBIO] único → crontab completo de vuelta con verificación de que el `schedule:run` por-minuto sigue → corrida manual del worker → log), entregado vía Mauricio para revisión del Director ANTES del despacho. Main avanzó (spike PWA + partes fleet): no se mergea ahora — no toca M15; merge en P-M15-09.
- **Pasos marcados:** P-M15-04 [x]; P-M15-03 [EN CURSO] (prompt entregado, falta despacho + evidencia). *(P-M15-03 cerrado [x] en la actualización de más abajo, mismo día.)*
- **Decisiones:** ninguna.
- **Delegaciones:** 1 redactada y entregada (cron de cola); pendiente de despacho por Mauricio.
- **Próximo paso:** al volver la evidencia del cron → cerrar P-M15-03 y archivarla en `docs/qa/INFRA/`; en paralelo **P-M15-05** (reintentador atómico + vista `/admin/notificaciones`) según tablero día 3.

### [2026-07-04] Stream 2 · cierre P-M15-03 + HALLAZGO: scheduler volvió a `*/20`
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, ahora sí en **Opus 4.8**) + IA externa de cPanel
- **Objetivo declarado:** procesar la respuesta de la delegación del cron de cola
- **Qué se hizo:** la IA-cPanel agregó el cron `* * * * * … queue:work --stop-when-empty --max-time=55` y lo verificó (worker corre, cola vacía, sale sin errores) → **P-M15-03 [x]**, evidencia íntegra en `docs/qa/INFRA/2026-07-04--INFRA--cron-queue-work-m15.md`. **HALLAZGO CRÍTICO al leer el paso 1:** el cron del **scheduler** está en `*/20 * * * *`, NO en `* * * * *`; el log de la propia respuesta no muestra ninguna corrida de `bsale:sync-stock` (con `*/20` el :50 nunca se dispara). Esto **contradice la evidencia archivada de P-S0-07** (que lo dejó en `* * * * *` con stock corriendo a :50) → o hubo una reversión posterior no documentada, o el server nunca persistió el fix. Es el bug original de vuelta. Escalado al Director (no es territorio de stream 2; toca las syncs de otro stream). Impacto en M15: el reintentador P-M15-05 (vía `schedule:run`) correría cada 20 min en vez de 5 → granularidad degradada, no roto (reclama por `programada_para <= now()`).
- **Pasos marcados:** P-M15-03 [x].
- **Decisiones:** ninguna.
- **Delegaciones:** 1 recibida y archivada (cron de cola, APROBADO CON OBSERVACIONES).
- **Próximo paso:** Director decide re-despacho del fix del scheduler (`*/20 → * * * * *`); stream 2 sigue con **P-M15-05** (reintentador atómico robusto a scheduler no-por-minuto + vista `/admin/notificaciones`).

### [2026-07-02] Stream 2: visto bueno de PLAN-M15 + P-M15-01/02 construidos (motor de notificaciones)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B) — **nota consumo: sesión corrió en Fable 5, no Opus 4.8 del roster**
- **Objetivo declarado:** incorporar ajustes del visto bueno → P-M15-01 y P-M15-02
- **Qué se hizo:** (1) merge de `origin/main` a la rama (trae `docs/fleet/`; conflicto solo en esta bitácora, resuelto conservando ambas entradas — `41dc503`); (2) PLAN-M15 actualizado con los 2 ajustes obligatorios (Notificacion SIN audit / PreferenciaCanal SÍ; reintentador atómico `withoutOverlapping` + claim por UPDATE) y las 3 notas (transiciones por canal, `{{ }}` siempre, campanita v1 sin polling); (3) **P-M15-01**: migraciones `notificaciones` + `preferencias_canal` MySQL 5.7-safe (unique de preferencias con evento a 100 chars por el prefijo utf8mb4) — `ff27f40`; (4) **P-M15-02**: `NotificacionDispatcher` + contrato `Canal` + `CanalMail`/`CanalDatabase`/`CanalWhatsApp`(stub) + job `EnviarNotificacion` (tries=1, reintento propio con backoff de `Configuracion`) + Mailable con vista escapada — `771f278`; (5) 14 tests nuevos → **suite 378 verdes local** (CI no corre en la rama). Gotcha de test: columna datetime trunca microsegundos → comparar backoff con `toDateTimeString()`, no `equalTo`.
- **Pasos marcados:** P-M15-01 [x], P-M15-02 [x] (en la rama).
- **Decisiones:** ninguna nueva (ajustes = dictado del visto bueno).
- **Delegaciones:** ninguna enviada; la del cron de cola (P-M15-03) es el siguiente paso y su prompt sale de PLAN-M15 §5.
- **Próximo paso:** **P-M15-03** — redactar la delegación del cron `queue:work` (plantilla VERIFICACION-CPANEL) y entregarla a Mauricio; en paralelo P-M15-04 (plantillas + seeds, no depende del cron).
### [2026-07-02] Recetario de prompts oficial adaptado + 3 skills de flota (P-S0-18)
- **Quién:** Mauricio + Claude (cuenta original/casa)
- **Objetivo declarado:** evaluar la [biblioteca oficial de prompts de Claude Code](https://code.claude.com/docs/en/prompt-library) y sincronizar el repo local (18 commits detrás).
- **Qué se hizo:**
  - Pull fast-forward limpio de los 18 commits de la flota; entorno local migrado y suite verificada (**372 verdes**).
  - Análisis de la biblioteca oficial (48 prompts, 5 fases SDLC): veredicto SÍ adaptable — ~22 llenan vacíos reales del flujo; el resto duplica el sistema documental o no aplica a HostGator.
  - **`docs/delegacion/RECETARIO-PROMPTS.md`** creado: 24 fichas R-01…R-71 en español, organizadas por momento del flujo y por rol de la flota, con slots, ejemplos DaliGo reales, gotchas caros horneados (R-31) y veredictos unificados con PROTOCOLO-DELEGACION.
  - **3 skills delgadas** en `.claude/skills/` que viajan a las 6 cuentas vía git pull: `/arranque` (PROTOCOLO-SESION §1), `/cierre` (checklist §3), `/pre-merge` (auditoría R-31). Regla anti-drift: la skill solo referencia el doc canónico. Regla de graduación en R-71: un prompt se vuelve skill cuando 2+ cuentas lo usan semanalmente.
  - Integraciones: fila en el mapa del README, nota en PROTOCOLO-DELEGACION §4 (plantillas=IA externa vs recetario=flota), referencia en ambos KICKOFF-*.
- **Pasos marcados:** P-S0-18 [x]. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** los del panel §0 (streams de la flota siguen su tablero; Mauricio: P-S0-03/04 briefs).

### [2026-07-02] P-SPK-02 hecho: cola offline de tandas (el soplador registra sin señal)
- **Quién:** Mauricio + Claude (Opus 4.8, Max-1/stream 1) — dictado del Director (Opus 4.8·xhigh)
- **Objetivo declarado:** P-SPK-02 (día 2 del tablero) — cola IndexedDB con idempotencia.
- **Qué se hizo:** migración `cliente_uuid` + unique compuesto `[reporte_id, cliente_uuid]`; `registroStore` idempotente dentro del lock + respuesta JSON (ValidationException para 422 reales); `resources/js/offline-queue.js` (encolar/drenar con CSRF fresco del meta, clasificación transitorio/permanente, guard de reentrada, reload al reconciliar); integración en el form del soplador (encola si offline, feedback optimista, contador de pendientes). Diseño validado adversarialmente ANTES de codear (3 bloqueantes: unique compuesto, CSRF sin serializar, clasificación de errores). 4 tests de idempotencia → 372 verdes. Verificado E2E en preview: 2 tandas offline → IndexedDB (sin `_token`) → online → drenan → 2 registros reales sin duplicar. Las decisiones quedaron en la bitácora de CLAUDE.md.
- **Pasos marcados:** P-SPK-02 [x]. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso (Max-1):** P-SPK-03 — prueba de campo real (modo avión, matar app a mitad de cola) + memo `docs/SPIKE-PWA.md` con la arquitectura elegida para M08.

### [2026-07-02] P-SPK-01 hecho: mi-reporte es instalable (spike PWA, día 1 del tablero de flota)
- **Quién:** Mauricio + Claude (Opus, Max-1/stream 1)
- **Objetivo declarado:** P-SPK-01 (tarea Día 1 del tablero — mayor riesgo técnico del proyecto, adelantado de W27 a W12)
- **Qué se hizo:** manifest.json (scope `/`, start_url mi-reporte, iconos 192/512+maskable generados con GD), `public/sw.js` conservador (assets `/build/*` cache-first inmutables; navegaciones network-first con fallback `/offline` SOLO en catch; passthrough sin respondWith para el resto; jamás HTML autenticado), ruta+vista `/offline` standalone, `Alpine.store('red')` con confirmación vía `/up` y registro del SW con guard de localhost, componente `<x-produccion.indicador-red>` en las 2 vistas del soplador. Diseño validado adversarialmente ANTES de implementar (3 bloqueantes corregidos en papel: opaqueredirect, scope, passthrough). 4 tests nuevos → 368 verdes. Verificado en preview: SW activo, caches pobladas, indicador reactivo, login intacto; navegador de dev limpiado (unregister). Las 5 reglas del SW quedaron en la bitácora de CLAUDE.md como contrato para P-SPK-02/M08.
- **Pasos marcados:** P-SPK-01 [x]. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** Mauricio instala en su celular desde staging (criterio "celular real"); mañana P-SPK-02 (cola IndexedDB offline con idempotencia UUID — sin Background Sync por iOS: vaciar en `online`).

### [2026-07-02] Se constituye la FLOTA: 6 cuentas Claude orquestadas + tablero de 3 días
- **Quién:** Mauricio + Claude (Opus, stream 1)
- **Objetivo declarado:** P-S0-17 — organizar 2 cuentas Max + 4 Pro con roles, dictado de modelo/esfuerzo y control de consumo.
- **Qué se hizo:** investigación del estado Anthropic a hoy (modelos Fable 5/Opus 4.8/Sonnet 5/Haiku 4.5; esfuerzo low→max; `/fast`; límites por plan NO públicos → medición empírica vía `/usage`). Diseño: Max-1 forjador stream 1 (spike PWA), Max-2 forjador stream 2 (E1·M15, ya operando), Pro-1 DIRECTOR (tablero/verificación/ledger, escribe solo `docs/fleet/`), Pro-2 AUDITOR/QA (read-only), Pro-3 INVESTIGADOR (decisiones D-0xx), Pro-4 ESCRIBA (docs aislados). Bus = Mauricio + repo; territorio exclusivo por cuenta; tareas L/XL solo a Max. Archivos: `docs/fleet/{FLOTA,TABLERO-3-DIAS,CONSUMO}.md` + `docs/delegacion/KICKOFF-DIRECTOR.md`.
- **Pasos marcados:** P-S0-17 [x]. · **Decisiones:** ninguna. · **Delegaciones:** 4 prompts de arranque entregados a Mauricio (Director, QA, Investigador, Escriba).
- **Próximo paso:** día 1 del tablero (2026-07-03): Max-1 → P-SPK-01; el Director se constituye y toma el mando del tablero.

### [2026-07-02] Stream 2 arranca: setup del entorno + PLAN-M15 con sello (pendiente visto bueno)
- **Quién:** Mauricio + Claude (stream 2, primera sesión)
- **Objetivo declarado:** kickoff §2/§3 + primer entregable (PLAN-M15 antes de P-M15-01)
- **Qué se hizo:** (1) toolchain local desde cero — esta PC no tenía PHP: instalado PHP **8.3.31** (misma versión fijada en `composer.json`) + Composer 2.10.2, extensiones sqlite/mbstring/etc. y `cacert.pem` (lección bitácora 2026-06-08); (2) clon propio `DaliGo-M15` (jamás el working tree del stream 1) + rama `feature/m15-notificaciones` desde `origin/main` (`4da5de2`); (3) setup HANDOFF §10 completo — gotcha: el `.env.example` trae `DB_DATABASE=` vacío y Laravel lo trata como seteado → apuntarlo explícito al sqlite; (4) **gate del kickoff cumplido: suite 364 verdes local**; (5) lectura obligatoria §3 completa (protocolo, RUTA, biblia M15, HANDOFF, CLAUDE.md íntegro, GUIA, delegación+plantillas, DECISIONES con D-011 nueva); (6) **`docs/planes/PLAN-M15.md`** escrito con sello de vigencia (verificado contra el código: cola/mail/scheduler/permisos/config/servicios/audit/nav) — diseño: dispatcher + contrato Canal (mail/database/whatsapp-stub), 2 tablas MySQL 5.7-safe, plantillas como claves `Configuracion`, reintentos propios con backoff, campanita, mapa P-M15-01…10 y 2 delegaciones listas para redactar.
- **Pasos marcados:** ninguno todavía (el plan es pre-P-M15-01; nota agregada en §4/E1)
- **Decisiones:** ninguna nueva. D-007 (stub) y D-011 (staging=QA) incorporadas al plan.
- **Delegaciones:** ninguna enviada aún; las 2 de E1 (cron cola, SPF/DKIM) quedaron especificadas en el plan §5.
- **Próximo paso:** **visto bueno de Mauricio a PLAN-M15.md** → recién ahí P-M15-01 (migraciones).

### [2026-07-02] Nace el stream 2: kickoff de E1 · M15 Notificaciones en rama paralela
- **Quién:** Mauricio + Claude (Opus, stream 1)
- **Objetivo declarado:** P-S0-16 — dar una tarea grande a la segunda cuenta de Claude de Mauricio sin chocar con el trabajo de M11.
- **Qué se hizo:** brief completo en `docs/delegacion/KICKOFF-E1-M15.md` para que el stream 2 construya la unidad E1 (la siguiente del plan) en la rama `feature/m15-notificaciones`: lectura obligatoria de toda la doc, presentación de la IA de cPanel/QA, reglas anti-colisión (territorio prohibido de producción, archivos compartidos con cambio mínimo, `public/build` se regenera post-merge y jamás se resuelve a mano, merge final coordinado con Mauricio), estándares innegociables y primer entregable = `docs/planes/PLAN-M15.md` con sello de vigencia. Verificado que `deploy.yml`/`tests.yml` disparan SOLO en `main` (pushear la rama no despliega; CI no corre en ramas → suite local obligatoria).
- **Pasos marcados:** P-S0-16 [x]; E1 pasa a [EN CURSO · stream 2] en §0/§4.
- **Decisiones:** ninguna. · **Delegaciones:** ninguna (el kickoff ES la delegación, a un dev-stream).
- **Próximo paso:** stream 2 → PLAN-M15 + visto bueno → P-M15-01. Stream 1/Mauricio → P-S0-03/04 (briefs).

### [2026-07-02] Aislamiento de pruebas: comando de limpieza + D-011 (URL oficial y entornos)
- **Quién:** Mauricio + Claude (Opus)
- **Objetivo declarado:** P-S0-15 (duda del dueño: ¿los datos de prueba quedan para siempre? ¿se escribe algo en Bsale?)
- **Qué se hizo:**
  - **Verificado con evidencia:** Bsale es SOLO-LECTURA por construcción (`BsaleClient` únicamente tiene `get()`/`each()`; los `->delete()` de las syncs son sobre tablas espejo locales; el kardex es local y el push está sin construir hasta D-005).
  - **Aclarado:** hoy hay UNA sola instancia/BD (staging = futura prod) — los datos de prueba SÍ persisten. Nuevo comando **`produccion:limpiar-pruebas`** (confirmación + `--force`; borra asignaciones/reportes/tandas/kardex + audits de reportes en transacción; catálogo/usuarios/Bsale intactos) + 2 tests.
  - **D-011 TOMADA** (Mauricio): oficial futura = `daligo.impdali.cl`, staging queda de pruebas, separación real de entornos se ejecuta en F3 (P-F3-06 actualizado). HANDOFF §9 reconciliado (cierra la deuda "dominio de staging").
  - Incidente de entorno local: Windows bloqueó `php.exe` (Control de aplicaciones) a mitad de sesión; el dueño lo destrabó y se continuó (no se pusheó nada sin tests verdes).
- **Pasos marcados:** P-S0-15 [x]. · **Decisiones:** D-011 tomada. · **Delegaciones:** ninguna.
- **Próximo paso:** sin cambio — P-S0-03/04 → E1 · M15 Notificaciones.

### [2026-07-02] Fix panel del jefe: "Pendientes de otros días" (alerta sin destino)
- **Quién:** Mauricio + Claude (Opus)
- **Objetivo declarado:** P-S0-14 (hallazgo del dueño en staging: "Por aprobar: 1" con "Cola · hoy: 0")
- **Qué se hizo:** las alertas por-aprobar/devueltos son globales pero la cola era solo de hoy → sección nueva "Pendientes de otros días" (patrón de los devueltos-de-otros-días del soplador), fila de reporte extraída al partial `_fila-reporte.blade.php` compartido, info-tips aclarados. Test nuevo → 362 verdes. Verificado en preview: enviado de ayer visible con fecha, aprobado desde ahí, panel vuelve a "Todo al día".
- **Pasos marcados:** P-S0-14 [x].
- **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** sin cambio — P-S0-03/04 → E1 · M15. (En staging queda 1 enviado del 01/07 —"Romcom Enjoyer"— que ahora será visible para aprobar/devolver.)

### [2026-07-02] Auditoría E2E de M11 + hardening pre-demo (reunión del jefe)
- **Quién:** Mauricio + Claude (Opus), en una segunda máquina (contexto reconstruido desde la doc)
- **Objetivo declarado:** fuera de plan (pedido del jefe: funcionalidad lista lo antes posible) → P-S0-13 creado. Auditar la calidad de TODO el flujo de producción end-to-end y dejar la demo lista.
- **Qué se hizo:**
  - **Auditoría E2E de M11** (3 agentes exploradores en paralelo + verificación propia línea a línea + comprobaciones empíricas con tinker): veredicto general SÓLIDO (owner-checks, máquina de estados, kardex desde tandas, sin N+1, 73 tests).
  - **5 hallazgos confirmados y corregidos:** `whereBetween` reincidente en `sopladorHistorial` (vacío en SQLite con rango de 1 día), locks faltantes en `devolver`/`ajustar`/`destroyReporte` (carrera aprobar∥devolver podía desincronizar el kardex), `max:100000` anti-dedazo en cantidades, hint stale en `asignar.blade.php`. **8 falsas alarmas descartadas** por verificación (detalle en la bitácora de CLAUDE.md).
  - **3 tests de regresión nuevos** (el de F1 verificado que falla con el código viejo) → **361 verdes**.
  - **Demo E2E local verificada** en preview: asignar (jefe) → 2 tandas + motivos (soplador a 375px, intercepts client-side funcionando) → enviar → aprobar → kardex consistente (470 = 450+10+10, 6 movimientos) → drill-downs e historial con rango de 1 día OK. Cero errores de consola/red.
  - **Drifts de doc cerrados:** línea duplicada P-S0-08 en RUTA-MAESTRA, HANDOFF §8e/§9 (cron ya resuelto, decía "en curso").
- **Pasos marcados:** P-S0-13 [x] (creado y cerrado en esta sesión).
- **Decisiones:** ninguna.
- **Delegaciones:** ninguna (QA staging post-deploy queda opcional para después de la reunión).
- **Próximo paso:** sin cambio — P-S0-03/04 (despachar briefs, tabla de bodegas D-003) → arrancar E1 · M15 Notificaciones. La reunión del jefe puede re-priorizar; registrar lo que salga como R-002 si cambia el plan.

### [2026-07-02] Cierre de E0 — delegaciones de infra ejecutadas, hallazgo del cron y catastro de bodegas
- **Quién:** Mauricio + Claude + IA externa de cPanel/QA (primeras delegaciones reales del protocolo)
- **Objetivo declarado:** P-S0-07 y P-S0-08
- **Qué se hizo:**
  - **P-S0-07 (infra):** la IA externa detectó que el crontab real difería del runbook (dos `schedule:run` en `*/20` y `*/15`, ninguno por-minuto) y SE DETUVO a confirmar — el protocolo funcionó. Corrección aplicada: cron único `* * * * *`. **Hallazgo mayor:** `bsale:sync-stock` (:50) jamás había corrido por cron en producción; primera corrida verificada (28.350 stocks al día, 0 errores). `deploy.sh` des-congelado (`no-skip-worktree`). Rotación de contraseña BD pospuesta por decisión del dueño → P-S0-09.
  - **P-S0-08 (BD):** 0 duplicados de `bsale_variant_id` (migración unique habilitada para E3) + **catastro real de bodegas: 16** (la biblia estimaba ~25) — "Santa Rosa" ES una bodega Bsale; 2 de prueba; posible duplicidad en las de servicio técnico. Tabla pre-llenada para Luis/Ricardo lista en D-003.
  - Evidencias archivadas en `docs/qa/INFRA/` (las 2 primeras del proyecto); bitácora de CLAUDE.md += entrada del cron; HANDOFF §8e/§9 corregidos con el estado real.
- **Pasos marcados:** P-S0-07 [x], P-S0-08 [x]; creados P-S0-09 (password pospuesto), P-S0-10 (44 errores sync-clients), P-S0-11 (seeders en log de deploy), P-S0-12 (git "ahead by 111" en el servidor).
- **Decisiones:** ninguna tomada; D-003 avanzó (catastro obtenido, falta respuesta de Luis/Ricardo).
- **Delegaciones:** 2 ejecutadas y archivadas (ver `docs/qa/README.md` índice).
- **Próximo paso:** P-S0-03/04 (Mauricio despacha los briefs, en especial la tabla de bodegas de D-003) → arrancar E1 · M15 Notificaciones.

### [2026-07-01] Sesión E0 — consolidación documental y nacimiento del sistema de gestión
- **Quién:** Mauricio + Claude (Opus)
- **Objetivo declarado:** unidad E0 completa — análisis integral del proyecto + documentación oficial paso a paso.
- **Qué se hizo:**
  - **Análisis cruzado en profundidad** (3 agentes en paralelo): documentación del repo (biblia/HANDOFF/CLAUDE/docs), los 9 documentos de negocio de la carpeta "contexto dali" (Gantt, hoja de ruta 2025, correcciones, flujos, procesos operativos por sucursal, 991 órdenes ML pendientes) y auditoría del código real (rutas/modelos/migraciones/seeders/tests).
  - **Hallazgos clave:** el código va ADELANTADO al Gantt (M01/M02/M03/M11-F1 + taller ST + espejo de inventario ya construidos, ~358 tests verdes) pero las decisiones de F0 van atrasadas; el HANDOFF tenía "código fantasma" (espejo de inventario y taller sin documentar) y una redacción que se prestaba a creer M13 implementado (NO lo está); `docs/PLAN-M11-FASE2.md` obsoleto (drift doc↔código).
  - **Transcripción del escaneo** `Correcciones luis.pdf` (13 págs) → `docs/CORRECCIONES-LUIS.md`: la biblia cubre todo; 6 novedades menores anotadas (paso P-S0-06).
  - **Sistema documental creado:** `docs/RUTA-MAESTRA.md` (tablero maestro con unidades E0–E13, re-baseline R-001, tracker ~21%), `docs/DECISIONES.md` (D-000…D-010 con briefs), `docs/PROTOCOLO-SESION.md`, `docs/delegacion/` (protocolo + 3 plantillas), `docs/qa/`, `docs/planes/` (regla del sello de vigencia; PLAN-M11-FASE2 archivado), esta bitácora.
  - **HANDOFF adelgazado** a manual técnico: nota M13 SIN CÓDIGO, secciones nuevas §8e (espejo inventario) y §8f (taller ST), estado migrado a RUTA-MAESTRA; README con nuevo orden de lectura + mapa de docs; CLAUDE.md regla de oro ampliada con el cierre de sesión.
- **Pasos marcados:** P-S0-01 [x], P-S0-02 [x] (los commits: ver este mismo push).
- **Decisiones:** creadas D-001…D-010 (todas abiertas salvo D-000 retroactiva); ninguna tomada.
- **Delegaciones:** preparadas (pendientes de envío por Mauricio): P-S0-07 (infra cPanel: cron duplicado, password BD, deploy.sh) y P-S0-08 (query duplicados `bsale_variant_id` + CSV de bodegas).
- **Próximo paso:** P-S0-03 (despachar briefs) y P-S0-07/P-S0-08 (delegaciones); luego arrancar E1 (M15 Notificaciones).
