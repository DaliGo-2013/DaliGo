# RUTA MAESTRA — DaliGo · paso a paso y estado vivo

> Este documento responde dos preguntas: **¿dónde estamos parados?** y **¿qué sigue?**
> La especificación (el QUÉ y el POR QUÉ) vive en `PROYECTO_DALIGO.md` (la biblia) — aquí NO se repite, se enlaza.
> **Regla de oro:** si hiciste código y no actualizaste este archivo en el mismo push, la sesión no terminó.
> Cómo trabajar con este documento: `docs/PROTOCOLO-SESION.md`.

---

## 0. DÓNDE ESTAMOS HOY (panel vivo — actualizar en cada cierre de sesión)

| Campo | Valor |
|---|---|
| **Última actualización** | 2026-07-28 (**P-MOB-01 · STREAM RESPONSIVE MÓVIL DEL DUEÑO EN PRODUCCIÓN** — brief de diseño propio, probado en el simulador de iPhone: los formularios del QR se manejan con una mano (barra de envío **fija al pie** con safe-area + **acordeón por máquina** con «Máquina 2 de 3» y resumen al plegar), **anti-zoom de iOS** en los 4 componentes de campo (`text-sm`→`text-base sm:text-sm`: Safari hace zoom bajo 16px), el **técnico de terreno** ya puede llamar (`tel:`), llegar (Maps) y **cerrar su trabajo** desde el celular, campanita casi a pantalla completa en móvil, pantalla sin conexión que dice **qué sí funciona**, y barrido táctil de 44px + safe-area en toda la app. **Hallazgo:** el backend de `agenda-terreno.estado` existía desde el principio, con permiso y tests, pero **ninguna vista lo exponía**. **Medido y NO cambiado** (regla del dueño): las tablas de repuestos no desbordan a 375px y el listado de órdenes ya son tarjetas. **Descartado como bug:** `inputmode="numeric"` en el RUT — `dvRut()` devuelve 'K' y el teclado numérico de iOS no la tiene (~1 de cada 11 no podría escribir su RUT); **la rama `feature/responsive-tactil-forms` lo tiene puesto, no mergear ese pedazo**. 3 permisos sin etiqueta en la pantalla de Roles, corregidos. Gate R-31 **APROBADO** tras cazar en el propio lote un `confirm()` que un apóstrofo reventaba. Suite **992 verdes**) · 2026-07-26 noche (**CRUCE BIBLIA ↔ APP, a pedido del dueño**: se buscó el documento maestro, se contrastó con la app real y se sinceró la documentación. Hallazgos: (1) la biblia estaba **congelada en junio** y le faltaba un módulo entero construido en julio → entra **M17 Servicio en terreno** (~27 rutas, en producción desde el 24-07) con la regla de biblia §9 para que no vuelva a pasar; (2) el **tracker §10 mentía hacia abajo** —M14/M15/M16 en 0 % pese a estar cerradas con QA aprobado, M12 en 25 % siendo el módulo más grande— → re-baseline a base 105 y **≈21 % → ≈37 %**; (3) el pivote a DESPACHOS del 13-07 que pospuso M04 **nunca se registró** en §11 → **R-002** retroactiva, más **R-003** por la entrada de M17; (4) `P-M04-05` llevaba 24 días hecho sin marcar y `P-M04-01` arrastraba un `[B:D-003]` ya tachado. **Lo que el número no dice: el ciclo de la factura —M04→M05→M07→M08, 35 de los 105 puntos y el objetivo central del proyecto— sigue en CERO**) · 2026-07-26 (**E-NAV P-NAV-10 · panel flotante medido**: la campanita se salía 72px de la pantalla —`w-80` anclada `end-0` dentro de una sidebar de 264px—; reincidencia del hallazgo [2026-07-01] sobre los globos ⓘ, que arregló el componente y no el mecanismo. Directiva Alpine `x-dg-anclar` que mide y coloca; fuera el prop `align` del ⓘ y sus 14 usos a mano; 8 candados mutados; medido sin login en banco estático a 375/768/1024/1280/1600/1920; **aprobado por el dueño en staging y en celular**. Suite **920 verdes / 4418 aserciones**. Antes ese día: merge de `fix/iconos-size-prop` y la CI corriendo también en PRs) · 2026-07-25 (**E-NAV P-NAV-09 · un solo ancho de página**: 7 anchos entre 75 vistas y 47 de 69 pantallas con el título desalineado del contenido hasta 352px → 2 tokens `listado`/`formulario` declarados una vez en el layout; 5 candados) · 2026-07-24 noche (**403/404 amables + 3 enlaces rotos arreglados** en rama `feature/errores-amables`: el usuario que navega sin permiso va al **Inicio con una mini-notificación** —canal `aviso` en el layout— y el resto ve páginas de error con marca DaliGo en vez de la pantalla de Symfony en inglés; arreglados el «Volver» del **conductor** (403 en su única pantalla), la notificación de cotización **fuera de la cartera** del vendedor y el **404** al aprobar un ajuste cuyo reporte fue borrado; **D-014 TOMADA**; suite **889 verdes**; `phpunit.xml` fija `memory_limit=512M` porque la suite pasó los 128M y `artisan test` —el comando de la CI— moría antes del primer test) · 2026-07-24 (**tarjetas Trello del dueño, stream Claude**: **historial propio del soplador — últimos 45 días con filtro Desde/Hasta** en rama `feature/soplador-historial-45dias` (`fce895d` + `39b820f` + merge `323439b`, suite **868 verdes**, gate R-31 **APROBADO CON OBSERVACIONES** tras cazar un **500 real** en el filtro de fechas: `Carbon::hasFormat` acepta años de 5 dígitos y `createFromFormat` reventaba — fix con regex estricta + try/catch + round-trip; detalle en la bitácora de sesiones). Verificadas en la misma sesión: tarjeta de **procedencia saco/caja** al asignar = **ya resuelta** el 23-07 (`2cc391c`, sin trabajo); tarjeta de **separar por categoría las aprobaciones** = hecha en `feature/aprobaciones-categorias` (gate R-31 aprobado); **notificaciones solo administrables por Luis + TI** en `feature/notificaciones-solo-admin` (gate aprobado tras remediar un bundle stale). Las 3 ramas quedan **sin mergear**, esperando la orden del dueño) · 2026-07-23 (**E10-v1.2 CERRADA = MERGE A MAIN/DEPLOY** — P-M16V1-05: gate R-31 adversarial APROBADO CON OBSERVACIONES corregidas `326d87f`, dos merges de main en vuelo absorbidos, suite final **790 verdes**, merge `6d8ae18` por orden del dueño; evidencia `docs/qa/M16/`; flaky preexistente de `IngresoTallerPublicoTest` derivado a tarea aparte) · 2026-07-22 (**P-M16V1-04 hecho en rama `feature/m16-accesos-iconos`** — accesos del Inicio como cards con ícono + color personalizable por usuario, estilo Bsale sobrio; D-013 TOMADA; `4b5a64f`, suite 768 verdes, preview 375/768/1280 OK; falta P-M16V1-05 gate R-31 + merge doble llave — ficha E10-v1.2 en §4) · 2026-07-21 noche (**stream boceto ST/terreno — Marco + Claude**: mucho a producción hoy — **P-M12-02 fase CORREO** cotización al cliente con respuesta ACEPTO/NO ACEPTO registrada (`e2b1227`); **Agenda de terreno** = aviso a ventas de solicitudes por coordinar (`af70cab`) + confirmación del cliente a la cita (agendar/reprogramar/anular, `39e6a65`); antes: fusión calendario+lista, multi-día/viajes con bloqueo por ocupación, franjas de 2 h, técnico por defecto, "Por coordinar" en carrusel, seguimiento sin "esperando repuesto". Suite **728**. Detalle por feature en bitácora; falta wa.me de P-M12-02 hasta D-007) · 2026-07-21 (**E-TZ · PLAN-TIMEZONE 100% EN CÓDIGO** — P-TZ-01 `293d0aa` + P-TZ-02 `50f1f61` en producción con doble llave; calendario y hallazgo `update():151` aplicados por Marcos por canal directo `daf948d`; frontera nocturna+mes blindada `6e40051`, suite 704/2259; queda solo P-TZ-03 QA de borde del dueño) · 2026-07-17 (**E2·M14 CERRADA** — acta QA guiado 15-07, matriz 8/8 en producción) · 2026-07-13 (**P-M14-06 hecho en rama** — historial admin `/admin/aprobaciones` con filtros + resumen, `8c8d3e2`, 485 tests, verificado en preview 375/768/1280; E2·M14 va **6/7**, solo falta P-M14-07 merge coordinado) · 2026-07-08 (**E1·M15 CERRADA — primera unidad completa de la flota, kickoff→producción en 6 días**: P-M15-10 [x] con entregabilidad verificada — SPF/DKIM VALID + DMARC creado, Gmail=RECIBIDOS con captura del dueño, fila mail Enviada, correos M12 des-atascados del log; evidencia en `docs/qa/INFRA/`) · 2026-07-07 noche (**P-M15-09 [x] CERRADO** — QA staging APROBADO CON OBSERVACIONES aceptado y archivado en `docs/qa/M15/`; motor de reintentos probado EN PRODUCCIÓN · 2026-07-07 tarde (**M15 MERGEADO A MAIN = DEPLOY** — P-M15-09 fase deploy hecha con doble llave, reintentador corregido a `everyFifteenMinutes()` · **E2 arranca**: `docs/planes/PLAN-M14.md` sellado — motor de aprobaciones, espera VISTO BUENO de Mauricio antes de la primera migración · **I-03 CERRADA**: token renovado por Mauricio y verificado — las 4 syncs corrieron OK en sus slots nuevos, espejo descongelado) · 2026-07-07 (**I-01 CERRADA en modo compatibilidad** — cron `*/15` + syncs en :00/:15/:30/:45 · **P-SPK-03 hecho → spike PWA COMPLETO**) · 2026-07-06 (**P-M12-01 piloto** QR mergeado `1639d71` LIVE; SMTP pendiente P-M15-10) |
| **Fase actual** | F1→F2 (código adelantado al Gantt; decisiones de F0 atrasadas) |
| **Unidad activa** | **E-NAV · Menú V4 — 8 de 10 pasos hechos** (P-NAV-08 Volver único 24-07 · P-NAV-09 ancho único 25-07 · P-NAV-10 panel anclado 26-07, los tres con QA del dueño; **pendientes P-NAV-05 gate R-31 formal y P-NAV-06 pantallas huérfanas**) · **E1 · M15 CERRADA 2026-07-08** · **E2 · M14 Aprobaciones CERRADA 2026-07-17** (QA del dueño 8/8 en producción, acta 15-07) · **E10-v0/v1 dashboard CERRADAS** · **E-TZ timezone APLICADA 21-07** (solo P-TZ-03 QA de borde pendiente) · **P-M12-02 fase CORREO aplicada 21-07** (stream boceto ST/terreno: cotización + comunicación al cliente) · stream 2 en DESPACHOS · E0 cerrada salvo pendientes menores (P-S0-03/04/05/06 + P-S0-09/10/11/12) |
| **Próximo paso** | ⚠️ *Corregido el 2026-07-26: las 4 ramas que esta fila daba por pendientes **ya están en `main`** —`errores-amables` (`f992d1e`), `soplador-historial-45dias` (`ffca25d`), `aprobaciones-categorias` (`6069354`), `notificaciones-solo-admin` (`9b85752`)—; la fila apuntaba a trabajo terminado hacía días.* · **La decisión que toca es de PRODUCTO, no de merge:** el ciclo de la factura (M04→M05→M07→M08) está en 0 % y es el objetivo central del proyecto; M04 sigue pospuesto desde R-002 (13-07) esperando a D-003. Definir si se retoma M04 o se sigue con la periferia · **Cierres baratos pendientes:** P-NAV-05 (gate R-31 formal), P-NAV-06 (pantallas huérfanas al menú), P-TZ-03 (QA de borde del dueño ~21:30), y el `.env` del servidor a `CACHE_STORE=file`/`SESSION_DRIVER=file` · **Decisiones:** 5 abiertas (D-003/004/005/006/008) con objetivo declarado de cerrarlas al **31-jul-2026** · **Ramas abiertas hoy:** `feature/despachos-v1` (14-07), `feature/errores-500-familia` (25-07), `feature/notif-especificas` (23-07), `feature/m15-notificaciones` (13-07, resto de una épica ya cerrada), `design/menu-talana` (23-07) |
| **Bloqueos activos** | D-003 (bodegas — Ricardo respondió 13-07, Luis pendiente; M04 pospuesto → sin fecha crítica), D-005 (soporte Bsale, bloquea M05-F2; ruta docs subió por DESPACHOS) — semáforo en `docs/DECISIONES.md` §2 |
| **Salud doc↔código** | VERIFICADA el 2026-07-07 (infra por SSH: crontab `*/15` vivo, 4 syncs OK en sus slots, espejo al día tras I-03) |
| **Avance global** | **≈ 58 %** sobre base 108 (tracker actualizado el 2026-08-10 en §10: **F1 de PLAN-M11-FINAL completa** —backflush + paradas con duración, M11 85 %, primer módulo forjado en paralelo por ambos streams—; el 06-07-ago: M04 40 % —bodegas paramétricas + wizard de baja—; el 05-ago: F2 despachos completa —M08 75 %— y E6 Devoluciones —M13 85 %—). **Del ciclo de la factura —35 puntos, el objetivo central— hay ≈ 53 %, pero M05 todavía no puede emitir un documento tributario real** (config vacía, candado apagado, sin ruta de emisión ni comando B6). F2 de M11 en curso (OEE + alertas) |

**Hecho:** M01 Core · M02 Catálogo+Precios · M03 Clientes · M11 Producción F1 · Taller ST básico (subset de M12) · Espejo inventario read-only (base de M04) · **M15 Notificaciones (E1, cerrada 2026-07-08)**
**En curso:** E0 (esta consolidación)
**Especificado sin código:** M04 · M05 · M07 · M08 · M12 (resto) · **M13 (sin código — ver nota §5.6)** · M14 · M15 · M16
**Standby/backlog:** M06 · M09 · M10

---

## 1. Cómo leer este documento

- **Unidades de trabajo `E0…E13`** (§4–§8): bloques secuenciales de 1–6 semanas; cada una tiene objetivo, prerrequisitos, rama git, criterio de "hecho" y qué se delega a la IA de QA.
- **Pasos `P-<área>-<nn>`**: la unidad atómica (1–4 horas). Marcas:

| Marca | Significado |
|---|---|
| `- [ ]` | Pendiente |
| `- [ ] [EN CURSO]` | Iniciado, no cerrado |
| `- [ ] [B:D-0NN]` | **Bloqueado** por la decisión D-0NN (ver `docs/DECISIONES.md`) — greppable con `\[B:D-` |
| `- [x] … (commit \`hash\` / evidencia)` | Hecho. **Prohibido marcar `[x]` sin commit o evidencia QA enlazada.** |

- Un módulo pasa a **HECHO** solo cuando todos sus pasos están `[x]` **y** hay al menos una evidencia QA con veredicto APROBADO en `docs/qa/Mxx/`.
- Los detalles de implementación de lo YA construido no están aquí: están en `HANDOFF.md` (manual técnico). Aquí solo el estado.

---

## 2. Mapa general (fases × módulos)

Orden rector: biblia §6 (Gantt). Fechas re-baselinadas el 2026-07-01 (§11, R-001).

| Fase | Contenido | Hito re-baselinado | Estado |
|---|---|---|---|
| F0 Discovery | Decisiones Sprint 0 (§3) | **H1' = 31-jul-2026: las 10 decisiones cerradas** | 🔴 atrasado (se persigue en paralelo) |
| F1 Transversales | M01 ✅ · M02 ✅ · M03 ✅ · **M15 (E1)** · **M14 (E2)** · M16-v0 (E10) | H2 login ✅ · **H3' ≈ 9-oct-2026** | 🟡 en curso |
| F2 Núcleo operativo | **M04 (E3–E4)** · M11 ✅F1 · **M05 (E5)** · **M13 (E6)** · **M07 (E7)** · **M08 MVP (E8)** · **M12 resto (E9)** · M16-v1 (E10) | **H4' ≈ 5-dic-2026** | ⚪ pendiente |
| F3 Piloto Mirador | Hardening, migración datos, capacitación, marcha blanca dic (E11) | **H5' = go-live 11-ene-2027** | ⚪ pendiente |
| F4 Rollout Abate | Config+capacitación, M09-mini (E12) | **H6' ≈ 9-feb-2027** | ⚪ pendiente |
| F5 Coquimbo + cierre | Config, deuda técnica, docs finales (E13) | **H7' ≈ fin feb-2027** | ⚪ pendiente |

---

## 3. Sprint 0 — decisiones que destraban el plan

Las 10 decisiones viven en **`docs/DECISIONES.md`** (fichas D-001…D-010 con briefs listos para enviar). Aquí solo el trabajo operativo:

- [x] **P-S0-01** · Registro de decisiones creado con briefs copy/paste (este push, 2026-07-01)
- [x] **P-S0-02** · Transcripción y cotejo de `Correcciones luis.pdf` → `docs/CORRECCIONES-LUIS.md` (este push, 2026-07-01)
- [ ] **P-S0-03** · Enviar los briefs D-001, D-003, D-004, D-005, D-007 a sus decisores (Mauricio los despacha; anotar fecha de envío en cada ficha)
- [ ] **P-S0-04** · D-003: ~~obtener el catastro~~ ✔ obtenido (16 bodegas, evidencia P-S0-08) → **enviar la tabla pre-llenada a Luis/Ricardo** (lista en `docs/DECISIONES.md` D-003)
- [ ] **P-S0-05** · Revisar semáforo cada viernes hasta cerrar las 10 (ritual §0)
- [ ] **P-S0-06** · Aclarar con Luis/Mauricio las 3 anotaciones ambiguas del escaneo (ver `docs/CORRECCIONES-LUIS.md` §Discrepancias): "14 m³" junto a peso/dimensiones, la serie de códigos `1010/1020/…/8010001`, y "(Nuevo APP B[sale?])" junto a M10
- [x] **P-S0-07** · Delegación infra a IA-cPanel: cron del scheduler corregido (los `*/20`+`*/15` reemplazados por UNO `* * * * *` — hallazgo: `bsale:sync-stock` a :50 JAMÁS había corrido en prod; primera corrida verificada, 28.350 stocks al día), `deploy.sh` des-congelado, `schedule:list` y logs limpios (evidencia: `docs/qa/INFRA/2026-07-02--INFRA--cron-deploysh-infra.md`)
- [x] **P-S0-08** · Query duplicados `bsale_variant_id`: **0 duplicados** → migración `unique` habilitada para E3. Catastro real de bodegas obtenido: **16** (no ~25) — "Santa Rosa" ES una bodega Bsale (evidencia: `docs/qa/INFRA/2026-07-02--INFRA--duplicados-variantid-catastro-bodegas.md`)
- [ ] **P-S0-09** · Rotar contraseña de la BD `impdali_daligo` + `.env` + `config:cache` (la clave se compartió por chat alguna vez — HANDOFF §9). **Pospuesto por decisión del dueño (2026-07-02)**; hacerlo idealmente antes de F3/piloto. La clave nueva NUNCA pasa por un chat: se escribe directo en cPanel y `.env`
- [x] **P-S0-10** · Investigado y reclasificado (2026-07-15, VB). **Confirmado por análisis de código:** los "44 omitidos / 44 errores" eran el **mismo** evento contado dos veces (el único `catch` que subía `omitidos` también empujaba a `errores`), y todos eran violaciones del `unique` sobre `rut` = **colisiones de RUT duplicado en Bsale** (esperado: Bsale trae varios registros por RUT). No es fallo. **Fix:** excepción tipada `ClienteDuplicadoException`; `ClientSync` ahora cuenta esos casos en un bucket `duplicados` aparte de `errores` (que queda para fallos reales, ~0); el comando muestra "Duplicados RUT" + nota "esperado, no es error"; log dice "N duplicados por RUT (esperado), N errores". Test `test_rut_collision_is_counted_as_duplicate_not_error`. 595 verdes. **Seguimiento (2026-08-17, VB):** además de contarlos, ahora se **listan** los RUTs/ids descartados (en el log y en la salida del comando, tope 50 en pantalla) como insumo para limpiar el origen en Bsale — `ClienteDuplicadoException` porta rut+id, `stats['duplicados_ruts']`, test extendido.
- [ ] **P-S0-11** · Confirmar seeders visibles (`RUNNING/DONE`) en el log de Actions del próximo deploy
- [ ] **P-S0-12** · Diagnosticar `git status` del servidor: "ahead of 'origin/main' by 111 commits" — probable remote `origin` apuntando al repo viejo o fetch por URL sin tracking ref. Pedir `git remote -v` en la próxima delegación de infra (no afecta el deploy actual)
- [x] **P-S0-13** · Auditoría E2E de M11 Producción + hardening pre-demo (pedido del jefe, fuera de plan): 3 exploradores + verificación línea a línea → 5 fixes (whereDate en historial del soplador, locks en devolver/ajustar/destroy espejando aprobar, `max:` anti-dedazo en cantidades, hint stale de asignar) + 3 tests de regresión (361 verdes) + demo local E2E verificada (commit `3ff976d`, 2026-07-02)
- [x] **P-S0-14** · Panel del jefe: sección "Pendientes de otros días" — las alertas por-aprobar/devueltos son globales pero la cola era solo de hoy → un enviado viejo quedaba contado e invisible (hallazgo del dueño en staging). Partial `_fila-reporte` compartido + test (commit `49f695a`, 2026-07-02)
- [x] **P-S0-15** · Aislamiento de pruebas: comando `produccion:limpiar-pruebas` (borra asignaciones/reportes/tandas/kardex + audits de reportes, con confirmación; catálogo intacto) + **D-011 TOMADA** (URL oficial `daligo.impdali.cl`, staging queda de pruebas, separación real en F3). Verificado: Bsale es solo-lectura por construcción (commit `3d1defd`, 2026-07-02)
- [x] **P-S0-16** · Kickoff del **stream 2** (segunda cuenta Claude): arranca E1 · M15 en la rama `feature/m15-notificaciones` con brief completo en `docs/delegacion/KICKOFF-E1-M15.md` (lectura obligatoria de toda la doc, reglas anti-colisión, territorio prohibido, merge coordinado; deploy/CI verificados solo-main) (commit `4da5de2`, 2026-07-02)
- [x] **P-S0-17** · **Flota constituida** (6 cuentas: 2 Max forjadores + 4 Pro con roles Director/QA/Investigador/Escriba) + tablero de 3 días: `docs/fleet/{FLOTA,TABLERO-3-DIAS,CONSUMO}.md` y `docs/delegacion/KICKOFF-DIRECTOR.md`. Incluye matriz modelo×esfuerzo (estado Anthropic verificado 2026-07-02) y ledger empírico de consumo vía `/usage` (este push, 2026-07-02)
- [x] **P-S0-18** · **Recetario de prompts + 3 skills de flota**: biblioteca oficial de Claude Code (48 prompts) evaluada y adaptada → `docs/delegacion/RECETARIO-PROMPTS.md` (24 fichas R-xx en español, por momento del flujo y rol) + skills `/arranque`, `/cierre`, `/pre-merge` en `.claude/skills/` (delgadas, anti-drift: solo referencian el doc canónico; viajan a las 6 cuentas vía pull) (este push, 2026-07-02)

---

## 4. F1 · Transversales restantes

### M01 Core — HECHO ✅
| Spec | Estado | Detalle técnico | Evidencia |
|---|---|---|---|
| biblia §4/M01 | 4/4 incrementos | `HANDOFF.md` §8 (histórico) | suite de tests (auth, usuarios, roles, sucursales, config, auditoría) |

### M02 Catálogo + Precios — HECHO ✅ (webhooks y enlace M04 pendientes para E13/E3)
| Spec | Estado | Detalle técnico | Evidencia |
|---|---|---|---|
| biblia §4/M02 | sync catálogo/precios + import/export CSV + cron horario | `HANDOFF.md` §8b | tests Bsale sync (catálogo, precios) |

### M03 Clientes — HECHO ✅ (boleta rápida es de M05; historial de compras post-M05)
| Spec | Estado | Detalle técnico | Evidencia |
|---|---|---|---|
| biblia §4/M03 | CRUD + sync ~47.800 clientes + vendedor_id | `HANDOFF.md` §8c | tests clientes + sync |

### E1 · M15 Notificaciones — núcleo multi-canal, email primero (~2 sem) — [CERRADA 2026-07-08 · stream 2 · 6 días de kickoff a producción]
> Asignada el 2026-07-02 al stream 2 (segunda cuenta Claude), rama `feature/m15-notificaciones`.
> Kickoff/contrato: `docs/delegacion/KICKOFF-E1-M15.md`. Los `[x]` de esta unidad se marcan en la rama.
> **Plan fino:** `docs/planes/PLAN-M15.md` (sello 2026-07-02, commit `4da5de2`) — **APROBADO por Mauricio el 2026-07-02** con 2 ajustes obligatorios incorporados (Notificacion sin audit / PreferenciaCanal sí; reintentador atómico con `withoutOverlapping` + claim por UPDATE) y 3 notas menores documentadas. Luz verde P-M15-01/02.
**Objetivo:** motor centralizado (tablas `notificaciones` + `preferencias_canal`, plantillas por evento, triggers, reintentos) con canal **email** operativo y canal **WhatsApp enchufable** (stub hasta D-007). No bloqueada por Marco: esa es la gracia del diseño.
**Rama:** `feature/m15-notificaciones` · **Depende de:** nada (sí requiere cron de cola → delegación).
**Hecho cuando:** tests verdes; en staging un evento llega por correo real y a la campanita; reintento ante fallo verificado.

- [x] **P-M15-01** · Migraciones `notificaciones` (polimórfica: evento, canal, destinatario, payload, estado, reintentos) + `preferencias_canal` — MySQL 5.7: VARCHAR(191) en índices; unique de preferencias con evento a 100 chars por el prefijo utf8mb4 (este push, 2026-07-02)
- [x] **P-M15-02** · `NotificacionDispatcher` + contrato `Canal` (`CanalMail`, `CanalDatabase`, `CanalWhatsApp` stub que loguea) + job `EnviarNotificacion` (tries=1, reintento propio con backoff) + 14 tests — suite 378 verdes (este push, 2026-07-02)
- [x] **P-M15-03** · Cola database + delegación IA-cPanel: cron `queue:work` — despachado y verificado (worker corre, procesa, sale sin errores); evidencia `docs/qa/INFRA/2026-07-04--INFRA--cron-queue-work-m15.md` (2026-07-04). ⚠️ La misma respuesta destapó que el cron del **scheduler** estaba en `*/20` → escalado al Director (I-01). **Actualización I-01 (2026-07-07):** HostGator estrangula crons por-minuto → grilla `*/15` alineada; el cron de cola quedó `*/15 … queue:work --stop-when-empty --max-time=840` (latencia ≤15 min; NO re-delegar la spec vieja por-minuto/`--max-time=55`)
- [x] **P-M15-04** · Plantillas por evento + seeds idempotentes + claves en `Configuracion` (`notif_plantilla_sistema_prueba`, `notif_reintentos_max`, `notif_backoff_minutos`, `notif_remitente_nombre` — grupo `notificaciones`) + 4 tests; verificado seed 2× a mano sin duplicados; suite 382 verdes (este push, 2026-07-04)
- [x] **P-M15-05** · Reintentos con backoff + vista `/admin/notificaciones` (permiso `view notificaciones`) — comando `notificaciones:reintentar` ATÓMICO (lockForUpdate + claim por UPDATE, robusto a scheduler degradado) agendado cada 5 min `withoutOverlapping`; panel read-only con filtros estado/canal/evento + botón "enviar prueba"; permiso aditivo en seeder + label; `PreferenciaCanal` en `AuditController::MODELOS`; 403 sin permiso con test (este push, 2026-07-04)
- [x] **P-M15-06** · Campanita in-app en nav (desktop + responsive) — icono `bell`, partial `campanita` (contador no-leídas + dropdown + marcar leída/todas), bandeja personal en rutas `auth` (valida dueño, 403 ajeno); cambio mínimo en `navigation.blade.php`; `npm run build` + grep del bundle verificado (`lg\:flex`/`lg\:hidden` presentes, escapadas). Suite 397 verdes (este push, 2026-07-04)
- [x] **P-M15-07** · Preferencias por usuario (canal por tipo de evento, opt-out) — tarjeta en el perfil (`prefs[evento][canal]`), `NotificacionPreferenciaController` con `updateOrCreate`; el dispatcher las respeta (test de integración form→dispatch). Canal database fijo (campanita siempre) (este push, 2026-07-04)
- [x] **P-M15-08** · Tests (dispatch por preferencia, reintento, opt-out, 403) — cobertura del checklist completa; suite 405 verdes (este push, 2026-07-04)
- **Correcciones de auditoría del Director (gate P-M15-09), aplicadas:** (1) página personal `/notificaciones` legible en móvil + panel admin en el nav Administración; (2) `leer` exige canal database (404 si no); (3) `withoutOverlapping(10)` en el reintentador; (4) barrido self-healing de pendientes huérfanas (>10 min) en el mismo comando; + menores (claim limpia `programada_para`, dedup de queries de la campanita, test de humo endurecido)
- [x] **P-M15-09** · Merge + deploy + QA staging — HECHO (2026-07-07): merge a main `cfae59a` (6 conflictos por unión, bundle grepeado 4/4, suite 444→445 verdes con la corrección `everyFifteenMinutes()` + test de grilla), deploy Actions verde (migraciones M15 DONE en MySQL prod + 6 seeders en el log), **QA staging/producción APROBADO CON OBSERVACIONES — aceptado por el Director** (database punta a punta OK: panel→cola→campanita→página personal→badge 0; mail Fallida = alcance P-M15-10; motor de reintentos PROBADO EN PRODUCCIÓN: intentos 1→2 + backoff `[5,15,60]` en vivo). Evidencia: `docs/qa/M15/2026-07-07--M15--qa-funcional-staging.md`
- [x] **P-M15-10** · Delegación IA-cPanel: SPF/DKIM/DMARC + entregabilidad — HECHO (2026-07-08): APROBADO CON OBSERVACIONES, 15/16 pasos + paso 12 cerrado con captura del dueño (**Gmail=RECIBIDOS, no spam**). Causa raíz del correo roto: cuenta `servicio@staging.impdali.cl` con auth fallida → queda `servicio@impdali.cl`; SPF reparado a VALID (faltaba la IP del server), DKIM VALID, DMARC creado `p=none`; fila mail del panel = **Enviada**; bonus M12 verificado (mailer `smtp`, nada al log). "Mostrar original" (SPF/DKIM/DMARC en cabeceras) pendiente-opcional; Outlook sin casilla; rotación de claves → R-04 del tablero. Evidencia: `docs/qa/INFRA/2026-07-08--INFRA--entregabilidad-correo-p-m15-10.md`
- **Micro-backlog M15** (del QA staging 2026-07-07, sin bloqueo — construir cuando toque, NO ahora): (a) el panel `/admin/notificaciones` no muestra el correo de destino, solo el nombre; (b) el error SMTP (`ultimo_error`) sale truncado en la UI del panel; (c) endurecer `test_campanita_visible_en_el_nav` (assertear el badge)

### E2 · M14 Aprobaciones digitales + spike PWA offline (~2.5 sem, spike en paralelo)
**Objetivo M14:** motor polimórfico (`aprobaciones`, `reglas_aprobacion`), umbral desde `Configuracion` (`umbral_aprobacion_clp` ya sembrado), bandeja del aprobador usable desde celular, escalamiento por scheduler, notifica vía M15. Primer consumidor real: ajuste de reporte de producción (M11).
**Objetivo spike:** mitigar el MAYOR riesgo técnico (offline M08) en W12, no en W27 — service worker + cola IndexedDB sobre `mi-reporte` del soplador; memo de arquitectura para M08.
**Ramas:** `feature/m14-aprobaciones` · `spike/pwa-offline-m11`.
**Hecho cuando:** flujo solicitar→notificar→aprobar/rechazar→escalar con tests; QA aprueba desde celular real; spike demostrado con modo avión.

- [x] **P-M14-01** · Esquema motor (`aprobaciones` polimórfica + `reglas_aprobacion`) — MySQL 5.7-safe, seeders idempotentes, 6 tests (`5d9286d`, 2026-07-08)
- [x] **P-M14-02** · Servicio `Aprobaciones::solicitar()` (evalúa reglas; auto-aprueba si no matchea — clave del "Héctor 5→1-2 pasos") — + `aprobar/rechazar` con conflicto→rechazo dentro de la transacción, eventos M15 reales, 10 tests (`5470f31`, 2026-07-08)
- [x] **P-M14-03** · Bandeja móvil `/aprobaciones` (botones h-12, `lockForUpdate` contra doble-tap — lección bitácora 2026-06-30) — + «Mis solicitudes», rechazo con chips + «Otro», 8 tests HTTP (`7a14927`, 2026-07-08)
- [x] **P-M14-04** · Escalamiento por scheduler (N min configurable → siguiente rol + re-notificación) — `aprobaciones:escalar` en grilla `*/15` (I-01), 5 tests (`7a01da2`, 2026-07-10)
- [x] **P-M14-05** · Cablear `ProduccionController::ajustar` como primer consumidor — magnitud Σ|Δ| vs umbral 50, flash honesto, 10 tests (`7a01da2`, 2026-07-10)
- [x] **P-M14-06** · Historial + vista por aprobador/solicitante, auditable — `/admin/aprobaciones` con filtros `whereDate` + resúmenes sin N+1, 6 tests (`8c8d3e2`, 2026-07-12)
- [x] **P-M14-07** · Tests + merge + QA staging desde celular — merge coordinado doble llave (`69a93a2`, 14-07, Actions verdes) + **QA guiado del dueño 15-07: matriz A1-A8 COMPLETA 8/8 OK en producción** (auto-umbral, pendiente, aprobar/rechazar desde el teléfono, auto-admin, conflicto por payload obsoleto, doble-tap idempotente, escalar=0 por diseño v1) — acta `docs/fleet/buzon/partes/2026-07-15--max-1--acta-qa-m14-m15.md` → **E2 · M14 CERRADA** (2026-07-17)
- [x] **P-SPK-01** · Spike: manifest + service worker sobre `mi-reporte` (instalable, cache assets, detección online/offline) — `public/{manifest.json,sw.js,icons/}`, ruta `/offline` standalone, `Alpine.store('red')` + `<x-produccion.indicador-red>`; estrategia conservadora validada adversarialmente (fallback SOLO en catch por los opaqueredirect de auth, scope `/`, guard de localhost); 4 tests, 368 verdes (`ee01204`, 2026-07-02)
- [x] **P-SPK-02** · Spike: cola IndexedDB para `registroStore` offline con idempotencia (UUID cliente) — migración `cliente_uuid` + unique `[reporte_id, cliente_uuid]`, endpoint idempotente dentro del lock + respuesta JSON, `resources/js/offline-queue.js` (encolar/drenar con token CSRF fresco, clasificación transitorio/permanente), integración en el form del soplador (encola offline + contador + reload al reconciliar); 4 tests de idempotencia, 372 verdes; verificado E2E en preview (2 tandas offline → sincronizan sin duplicar) (`793bfcc`, 2026-07-02)
- [x] **P-SPK-03** · Spike: prueba de campo (modo avión, matar app a mitad de cola) + memo `docs/SPIKE-PWA.md` con la arquitectura elegida para M08 — memo sellado y verificado contra el código (7 secciones + guardarraíl golden-hash en `PwaTest`); **prueba de campo APROBADA por el dueño el 06-07** (capturas verificadas por el Director, tablero día 3 `50f8878`): A OK, B OK, 4/4 tandas sin duplicados, motivo por tanda sobrevivió la cola (este push, 2026-07-07)

### E10-v0 · M16 BI corte 0 — Dashboard ejecutivo v0 — ✅ CERRADA 2026-07-14 (plan: `docs/planes/PLAN-M16-V0.md`)
> Alcance re-baselinado por el plan sellado: el Inicio (`/dashboard`) se convierte en el tablero, cards por permiso (no "solo admin"); **stock crítico OMITIDO en v0 con evidencia** (sin señal de mínimo en el espejo Bsale — plan §4). Los IDs P-M16-0x de abajo son los pasos del PLAN-M16-V0 (el P-M16-02 de E10-v1 es otra cosa).
- [x] **P-M16-01** · Controller: 6 cards nuevas de lectura agrupadas por módulo (Producido/Avance/Merma de hoy, Recepciones por confirmar, Aprobaciones pendientes espejo de bandeja, Notificaciones fallidas) — queries agregadas `whereDate`/COUNT/SUM sin N+1, semilla del Inicio intacta, `$indicadores` plano preservado como contrato de tests; +10 tests (conteos exactos + visibilidad por rol), 581 verdes (`900d74a`, 2026-07-14)
- [x] **P-M16-02** · Vista `/dashboard` por secciones (encabezado de módulo + misma grilla `x-stat-card`) — responsive verificado 375/768/1280 sin scroll horizontal, build con grep 7/7 (`f268997`, 2026-07-14)
- [x] **P-M16-03** · Gate pre-merge R-31 adversarial 16/16 OK con 3 observaciones anotadas para v0.1 (`f9b0721`) + merge coordinado con doble llave Director+Mauricio a main (`4900d5b`, Deploy+Tests verdes, bundle verificado servido en staging) — **E10-v0 CERRADA**; la rama `feature/m16-v0-dashboard` cumplió su ciclo (2026-07-14)

### E10-v1.0 · M16-v1 «Pulso del día» — rediseño de decisión (plan: `docs/planes/PLAN-M16-V1.md`, opción A con visto bueno del dueño 14-07)
> Veredicto del dueño sobre v0 ("solo accesos directos") → investigación con fuentes (NN/g, Few, Eckerson, Lean, ProKanban) → opción A: excepciones con edad arriba (andon), pulso producción/taller con medida directa + serie 7d, accesos como zócalo. v1.1 post-merge despachos: franja de ventas por zona.
- [x] **P-M16V1-01+02** · Capa de datos + vista (rama `feature/m16-v1-pulso`): franja de excepciones (solo lo desviado, con edad del más viejo y destino; «Operación al día» quieto), pulso de producción (producido vs asignado en barra directa + merma con prom. 7 días previos + mini-serie CSS) y taller (aging 0-7/8-30/30+ portable PHP+`whereDate` + flujo semanal), zócalo compacto con TODOS los accesos (+Notificaciones/Aprobaciones); series expuestas como estáticos de modelo (`seriePorDia`/`asignadasPorDia`, delegación sin duplicar); 13 tests nuevos, 593 verdes, grep 9/9, responsive 375/768/1280 (`3f8b98f`, 2026-07-14)
- [x] **P-M16V1-03** · Gate R-31 14/14 OK (2 lentes, obs. menores anotadas) + merge coordinado con doble llave Director+Mauricio a main (`6caf1f9`, Actions 2/2 verdes, bundle vivo) — **E10-v1.0 CERRADA** salvo el QA de 5 segundos del dueño en staging (criterio de aceptación; hallazgo ya emitido: campanita invisible en móvil → tweak dictado) (2026-07-14)

### E10-v1.2 · M16 accesos con ícono + color por usuario (rama `feature/m16-accesos-iconos`)
> Pedido del jefe 22-07: cards estilo Bsale en el Inicio (ícono + palabra, cuadradito de color suave) sin perder la sobriedad. Decisiones del dueño en sesión: personalizar EN el dashboard (botón «Personalizar»), **default sobrio** (naranjo/gris — los pasteles son opt-in por usuario, D-013) y mantener la agrupación por módulo.
- [x] **P-M16V1-04** · Zócalo → cards con squircle de ícono (11 Heroicons nuevos + `wrench-screwdriver`/`bell` reusados; componente `<x-dashboard.acceso>`); fuente única `App\Support\AccesosDashboard` (keys/permisos/íconos/defaults); modo «Personalizar» con Alpine `dgTiles` (paleta de 8, pintado optimista + rollback, CSRF fresco del `<meta>`, `PATCH dashboard.colores.update` con paleta cerrada y 422 a cards desconocidas); persistencia POR USUARIO `users.dashboard_colores` (TEXT + cast `array`, MySQL 5.7-safe, fuera de `$fillable` y de la auditoría); 10 tests nuevos (`DashboardColoresTest`) + asserts «Personalizar» en `DashboardTest`, suite **768 verdes**; preview 375/768/1280 sin scroll horizontal (2/3/4 columnas), PATCH 200 verificado, bundle grep críticas `lg:*` + 6 pasteles OK (`4b5a64f`, 2026-07-22)
- [x] **P-M16V1-05** · Gate R-31 adversarial (5 lentes × panel independiente + adjudicación contra código): 35/36 gates OK al primer paso; 3 hallazgos reales (foco/aria/contrato del PATCH) corregidos en `326d87f` (endpoint pasó a MERGE — un PATCH parcial ya no borra prefs de cards invisibles — + 3 candados de test); 1 falsa alarma rechazada; flaky preexistente de `IngresoTallerPublicoTest` documentado (idéntico a main, 12/12 aislado — tarea aparte). **VEREDICTO: APROBADO CON OBSERVACIONES (corregidas)**. Dos merges de main en vuelo absorbidos con bundle regenerado (`c4877bd`); suite final **790 verdes**. Merge a main por orden del dueño `6d8ae18` — **E10-v1.2 CERRADA** (2026-07-23; evidencia en `docs/qa/M16/2026-07-23--M16--gate-r31-accesos-iconos.md`)

### E-NAV · Menú V4 «Talana» — sidebar izquierda con acordeón (rama `feature/menu-v4-acordeon`; diseño: rama `design/menu-talana` + proyecto «DaliGo» en claude.ai/design)
> Dirección del dueño 24-07 tras research completo + plan aprobado: V4 = sidebar fija 264px con módulos en acordeón (chip de ícono + chevron), topbar delgada h-14, drawer izquierdo 300px en móvil. Decisiones del dueño: Mi producción/Aprobaciones de PRIMER nivel (1-clic del operario), alcance solo navegación, pulir sobre la app real (no más bocetos), pantallas huérfanas después. Motivación extra: el nav viejo ya había drifteado entre sus copias desktop/móvil (permiso de tiempos-reparación y el ítem Notificaciones faltaban en móvil).
- [x] **P-NAV-01** · Fuente única `App\Support\MenuPrincipal` (24 ítems reales con route/permiso/patrones activo/badge declarativo; visibilidad de módulo DERIVADA de sus ítems — mata el drift por construcción) + 8 candados en `MenuPrincipalTest` (route/permiso/ícono/patrón/badge/labels únicos/cards⊆menú/poda por rol) + 6 íconos Heroicons nuevos (`ecb49c4`, 2026-07-24)
- [x] **P-NAV-02** · Componentes: `x-layout.sidebar` (UN solo `<aside>`: fijo 264px lg: / drawer 300px con anti-flash pre-Alpine), `x-layout.topbar` (título del módulo activo + campanita M15 + avatar), `x-sidebar-group` (details/summary nativo, chevron `group-open:rotate-90`), `x-sidebar-item` (aria-current contiguo). El build purgó de paso 21 clases muertas `gray-*` del bundle anterior (`36c7a00`, 2026-07-24)
- [x] **P-NAV-03** · Cutover del shell: `app.blade.php` flex (aside + columna `min-w-0`), composer del badge → `MenuPrincipal::badges()`, `SidebarTest` (6 tests). Suite **816 verdes / 2744 aserciones** ×3 corridas; candados NavigationTest/DashboardTest/CampanitaTest **sin tocar un assert**; mutación anti verde-engañoso (badge fuera → DashboardTest rojo → revert); preview 1280/1024/375: main 760px a 1024 sin scroll horizontal, drawer con hamburguesa 44px/backdrop/Escape/aria-expanded, campana móvil visible (`1536f1a`, 2026-07-24)
- [x] **P-NAV-04** · Retiro del nav legacy (`navigation.blade.php` + `nav-link`/`nav-dropdown`/`responsive-nav-link`/`responsive-nav-heading`), catálogo de CLAUDE.md actualizado a los componentes nuevos, 2 gotchas a bitácora (`view:clear` antes del build de producción; directiva Blade pegada a texto no compila `\B@`) (este mismo push, 2026-07-24)
- [ ] **P-NAV-05** · Gate R-31 formal del Auditor + QA del dueño en el ambiente real desde el celular. Avance al 24-07: revisión adversarial pre-gate ya corrida (3 lentes + refutación por hallazgo: 13 confirmados y corregidos en `2557043`, 0 altos en código, paridad de permisos 24/24 verificada); merge de origin/main en vuelo absorbido con bundle regenerado (`545b397`, suite integrada 825/2786). El dueño pidió el merge a main para probar en el ambiente real (mismo esquema de aceptación de E10-v1.0) — el R-31 formal queda pendiente sobre main
- [x] **P-NAV-07** · Pulidos iterativos con QA del dueño en staging, mismo día del cutover (los 4 APROBADOS por el dueño en el ambiente real): **p1** sin topbar en desktop —56px al área de trabajo—, campanita+usuario al pie de la sidebar, barra móvil mínima h-12 (`37c8791`) · **p2** acordeón exclusivo nativo (`details name=`), categorías en mayúscula con chip encendido, ítem activo con borde brand (`567b9a4`) · **p3** badges de pendientes declarativos por ítem/categoría (aprobaciones bandeja, producción por aprobar, devueltos del soplador, ST por confirmar; suma en categoría cerrada vía `group-open:hidden`), campanita-hub de funciones gateado por permiso, categoría activa marcada al cerrarse; +2 índices (`d077336`) · **p4** doctrina "badge del menú = ACCIÓN anclada a su ítem": fuera el badge de estado del taller (los candados de DashboardTest re-anclados al contrato accionable), + señal agenda por coordinar (este push, 2026-07-24)
- [x] **P-NAV-09** · **Un solo ancho de página** (salió de una falsa alarma del dueño: reportó que Inventario se veía corrido a la derecha; **no lo estaba** — sus dos mediciones de consola cuadran al píxel con `mx-auto max-w-7xl`, y los errores de su consola eran de una extensión de Chrome, no de DaliGo). Pero investigarlo destapó: **7 anchos distintos** entre 75 vistas y la banda del título **fija** en `max-w-7xl` en el layout → **47 de las 69 pantallas con cabecera (68%) tenían el título desalineado del contenido**, en 6 desfases de 64px a **352px**. Sobrevivió porque es defecto **de escritorio** (cero bajo 768px, máximo desde ~1544px) y las más afectadas son de uso móvil — la peor era `lote/create` con 344px, la única pantalla del conductor. **Nunca hubo una decisión**: `max-w` no aparecía en `CLAUDE.md` ni en `docs/`, y ningún test lo cubría. Decisión del dueño: **dos tokens** (`listado` 1280 · `formulario` 768) declarados **una vez** en `<x-app-layout>`, con el layout emitiendo los tres contenedores (título, aviso, cuerpo) desde la misma variable — desalinearlos deja de ser posible por construcción; y **el tope de 1280 se queda** (no se persiguen los ~500px vacíos de un monitor ancho). Barrido de 75 vistas con script determinista + guarda de balance de `<div>`, 5 casos a mano. **5 candados nuevos** en `AnchoDePaginaTest` (los primeros de layout del proyecto) **validados por mutación**: al devolver la banda a `max-w-7xl` fijo, 2 se ponen rojos. Suite 901 → 906 verde (este push, 2026-07-25)
  - **Hallazgos anotados y NO ejecutados** (para cuando toque, no ahora): (a) 3 pantallas con truncado real que sí ganarían con más de 1280 —`servicio-tecnico/index` mete 5 campos en una línea, `audits/index` recorta el diff, `productos/index` la categoría— y se confirmó que `x-list-row` coopera (el sobrante va a la columna truncada, no al medio); (b) el **padding vertical** tiene 7 valores (`py-12`×50, `py-8 sm:py-12`×16, `py-4 sm:py-8`×4 + 4 únicos), en su mayoría con criterio, pendiente de unificar en pasada aparte; (c) las 3 cifras apiladas del `sm:w-48` de `bodegas/show` necesitan rehacer el slot `meta` con `<x-produccion.metrica>` (ensanchar no lo arregla); (d) el `<table>` de `productos/importar` es el único sin `overflow-x-auto` y su envoltorio usa `overflow-hidden`, que **recorta en silencio** en vez de disparar el gate R-31
- [ ] **P-NAV-06** · (post-visto bueno del dueño) Pantallas huérfanas al menú: Máquinas/Tipos de botellón/Kardex bajo Operación, Conductores bajo ST — 4 líneas de datos en `MenuPrincipal` + regenerar bundle de diseño (`DesignCaptureTest` → DesignSync) para que claude.ai/design refleje la app real. ⚠️ **Al hacerlo, quitarles el `:back`**: hoy Máquinas/Tipos de botellón/Conductores conservan su "Volver" porque es su única salida estando fuera del menú (excepción sancionada en P-NAV-08); en cuanto entren al menú, un listado del menú NO lleva Volver. El candado `VolverTest::test_los_listados_huerfanos_conservan_su_volver` avisa solo: falla en cuanto se agreguen al menú
- [x] **P-NAV-10** · **El panel flotante se coloca donde quepa, medido** (reporte del dueño en staging: la campanita abría cortada por el borde izquierdo — "NOTIFICACIONES" se leía "CIONES"). Causa aritmética: panel `w-80` (320px) anclado `end-0` a un botón dentro de una sidebar de **264px** → ocupaba x ≈ **−72 → 248**, ~72px fuera de pantalla. **Reincidencia**: la bitácora [2026-07-01] ya había concluido, sobre los globos ⓘ, que *"el `align` es estático y no se puede acertar"* — pero aquel arreglo reescribió `info-tip` a mano y **no tocó `x-dropdown`**, que manifestó lo mismo apenas la campanita se mudó a la sidebar (p5 de P-NAV-07). Decisiones del dueño: **que se corra hasta caber** (manteniendo 320px, aunque sobresalga de la sidebar) y **unificar también el ⓘ**. Solución en el mecanismo, no en el componente: directiva Alpine `x-dg-anclar` que al abrir mide y decide (nace hacia el lado con más espacio → se corre si se sale → voltea o scrollea si no cabe a lo alto; se abstiene si el panel es `fixed`). La regla del lado **reproduce exactamente lo que las vistas elegían a dedo**, y por eso se pudo borrar el prop `align` del ⓘ con sus **14** usos manuales. Extras: tope estático `max-w-[calc(100vw-1rem)]`, cierre con **Escape**, validación del prop `width` (era un `match` con caso especial: `width="56"` emitía una clase inválida y el panel quedaba sin ancho **en silencio**), y `z-40` en `lg:` para la sidebar (`sticky` crea contexto de apilamiento aunque el z-index sea `auto`). **8 candados** en `PanelAncladoTest`, los 6 escenarios validados por **mutación** uno por uno. Suite **912 verdes / 4353 aserciones** (este push, 2026-07-26)
  - **Verificado sin login** (sesión local expirada; no se ingresan contraseñas): banco de pruebas estático en `public/` —temporal, borrado antes del commit— con el CSS/JS compilados reales y el markup real de los componentes vía `Blade::render`, medido con `getBoundingClientRect()` a 375/768/1024/1280/1600/1920. Los 4 paneles caben enteros en todos los anchos, sin scroll horizontal; los dos ⓘ caen a lados **opuestos** por sí solos; con 829px de contenido en una ventana de 380px → `max-height` 306px + scroll interno. Patrón a repetir cuando haya que verificar UI sin sesión. **APROBADO por el dueño en staging el 2026-07-26, cobertura completa**: la campanita en escritorio ("se ve bien") y el globo ⓘ **desde el celular** —la hoja inferior `fixed inset-x-4 bottom-4`, la única superficie que el banco de pruebas no reproduce fielmente y donde `x-dg-anclar` se abstiene a propósito—. Sin pendientes
  - **Hallazgo anotado y NO ejecutado:** `app.css:70` pone `overflow: clip` en `.dg-menu-grupo::details-content`. Cualquier panel que se ponga a futuro sobre un ítem del acordeón del menú quedará recortado en duro, y `x-dg-anclar` no puede salvarlo (no es desborde de viewport). Hoy no hay ninguno
- [x] **P-NAV-08** · **Un solo "Volver"** (pedido del dueño): auditoría → **79 controles de volver en 13 familias** distintas. Causa raíz **documental**: `page-header` ya tenía la prop `back` pero el catálogo de CLAUDE.md no la mencionaba → 5 vistas la usaban y **26 copiaron el bloque a mano en el slot `action`, o sea al lado opuesto**. Decisiones del dueño: flecha + texto **fijo** "Volver" (destino al tooltip), **arriba a la izquierda** pegado al título, **una sola salida** en formularios (fuera la X y el "Cancelar" del pie), **sin Volver en los listados del menú**, y comportamiento "siempre al padre pero restaurando el scroll si venías de ahí". Entregado en 4 commits: `x-volver` + handler delegado en `app.js` que reemplaza 5 `onclick` de `history.back` copiados (`ed7928b`) · barrido de 33 vistas, familias A/C/F/G, borrado del doble destino de `produccion/reporte` y de 5 "Volver al inicio" de listados del menú (`54164dc`) · 17 formularios + poda de `form-actions`/`form-footer` (`afb9a9d`) · doctrina en CLAUDE.md. **4 candados nuevos** en `VolverTest`, incluido uno **derivado de `MenuPrincipal`** que atrapó drift propio durante la construcción (qr/seguimiento/informes son ítems del menú y se les había puesto Volver) y un barrido de los 210 fuentes Blade contra los idiomas viejos. Suite 901 verde; restaurado de scroll verificado con clics reales en preview (vuelve a 1400px exactos; llegando desde el Inicio va al listado, no al Inicio). Fuera de alcance por decisión: las públicas del QR (este push, 2026-07-24)

### E-TZ · PLAN-TIMEZONE — hora chilena sin romper el motor — ✅ APLICADO 100% EN CÓDIGO 2026-07-21 (plan: `docs/planes/PLAN-TIMEZONE.md`)
> Origen: hallazgo #2 del QA guiado 15-07 («15:45» cuando en Chile eran 11:45). El análisis destapó que el síntoma era el problema MENOR: el «hoy» del servidor corría en UTC (P2) y dejaba ciega a la operación nocturna desde las ~20:00 de Chile. Opción C aprobada COMPLETA por Director + dueño (20-07): storage y motor quedan en UTC; el día de negocio y el render pasan a hora chilena.
- [x] **P-TZ-01** · Día de negocio: helper `App\Support\FechaNegocio` (+`config/daligo.php`) en ~25 sitios (soplador/cola/pulso/prefills/visita pública/QR/informes ST) + **reloj determinista de la suite** (mediodía UTC en `tests/TestCase` — mata la clase flaky-por-reloj) + batería de frontera nocturna 7/7 — merge doble llave `293d0aa` (2026-07-20)
- [x] **P-TZ-02** · Capa render: macro `Carbon::enChile()` en los 8 formatos absolutos (historiales, auditoría, bandeja, tandas); `diffForHumans` y casts `date` intactos a propósito — merge doble llave `50f1f61` (2026-07-21)
- [x] **P-TZ-02b** · Calendario de agenda (grilla `isToday`→`esHoy`) + hallazgo `update():151` de la auditoría de Max-1 — aplicados por **Marcos por canal directo del dueño** (`daf948d`, 21-07): precedente anti-churn — en territorio con churn activo el fix viaja por el dueño y la flota fija el comportamiento con tests (2026-07-21)
- [x] **P-TZ-02c** · Batería de frontera de agenda (nocturna + mes + redirect del hallazgo), rama SOLO-tests gated que **nació verde** sobre el fix de Marcos — merge doble llave `6e40051`, suite 704/2259 (2026-07-21)
- [ ] **P-TZ-03** · QA de borde del dueño (~21:30 Chile: día correcto en toda la app, agenda abre en HOY, historiales en hora chilena) — único pendiente; no es código

### E-PLAN · Página «Plan del proyecto» (/plan) — carta Gantt transicional (rama `feature/plan-proyecto`)
> Pedido del dueño 30-07: carta Gantt consultable por los participantes y modificable — 3 colores de estado, medidor de % global, apartado de «trabajos extras en paralelo», y actualización automática desde el repo al commitear. Decisiones del dueño (AskUserQuestion 30-07): plan oficial se LEE del repo + extras editables en UI · visibilidad por permiso nuevo asignable · Gantt clásico por módulo · incluye countdown de hitos, semáforo de decisiones y última actualización + enlaces.
- [x] **P-PLAN-01** · Fuente única `App\Support\PlanProyecto`: **parsea el tracker §10 de ESTE archivo** (doctrina de estado único — push a main = deploy = página al día, nadie mantiene una segunda copia) + fechas de barras e hitos como const del repo; parser del semáforo §2 de `DECISIONES.md`; página `/plan` (gantt por módulo con ventana+relleno, medidor global, hitos con countdown por `FechaNegocio`, decisiones abiertas, extras CRUD en BD `plan_extras`); permisos nuevos `ver plan proyecto` / `gestionar plan proyecto` (aditivos; se asignan por rol desde la UI de Roles); ítem de menú en Administración (1 línea en `MenuPrincipal`). Candados en `PlanProyectoTest` (12): parser contra el archivo REAL con invariantes auto-consistentes (Σpesos == TOTAL, **mutado**: peso alterado → rojo), biyección MODULOS↔tracker (un ítem nuevo en el tracker exige su línea de fechas en el mismo push), permisos, CRUD y validación de extras (rama `feature/plan-proyecto`, 2026-07-30; **en producción 31-07** con Deploy+Tests verdes — de paso el push destapó y se corrigió el flaky de calendario de los días 29/30/31, ver bitácora [2026-07-31])
- [x] **P-PLAN-02** · **Gantt clickeable con detalle por módulo** (feedback del dueño 31-07: el hover con `title` no le funcionó — estaba en el NOMBRE del módulo, no en la barra, y el tooltip nativo ni existe en táctil → se reemplazó por click, no se "arregló"). Cada fila del Gantt es ahora un botón (`aria-expanded`/`aria-controls`) que abre un panel de detalle **debajo del dibujo, fuera del `overflow-x-auto`** (en móvil usa el ancho completo del card, no los 640px del dibujo): estado + % + peso + ventana de fechas + **fundamento del % del tracker** + dos columnas curadas **«Completado» / «Por completar»** (arrays `hecho`/`falta` nuevos en `PlanProyecto::MODULOS` — capa narrativa del repo, editar = commit = deploy; el % sigue AUTO). Single-open por `sel` único, sin `x-transition` (gotcha [2026-07-22]). +2 candados (todo módulo trae sus bullets; el ancla `plan-detalle-{key}` se renderiza por módulo + un bullet real de cada columna). Verificado en preview: click abre/cambia/cierra, 375px sin scroll horizontal de página y panel entero en viewport (`a787705`, en producción 31-07 con Deploy+Tests verdes)
- [x] **P-PLAN-03** · **Extras automáticos desde los bloques del repo** (pedido del dueño 31-07: que el trabajo extra que entra por GitHub se agrupe en bloques con sentido y se auto-ingrese al apartado de extras). La agrupación YA existía: las unidades de RUTA-MAESTRA con nombre **con guión** (E-NAV, E-TZ, E-PLAN — las oficiales son numeradas E1…E13 y mapean a módulos que ya están en el Gantt). `PlanProyecto::bloquesExtra()` las parsea (título + pasos `[x]`/`[ ]` a columna 0; % = hechos/total; estado por conteos) y la sección «Trabajos extras en paralelo» gana la sub-sección **«Del repo (automático)»**: filas clickeables con badge/barra/`x/y pasos` → panel con los pasos completados y pendientes (layout de dos columnas extraído a `_columnas-detalle` y compartido con los paneles del Gantt). La lista manual queda como «Anotados a mano» con su CRUD intacto. Un bloque E-xx nuevo pusheado a GitHub aparece solo con el deploy — cero mantención. +2 candados (parser contra el archivo real: E-NAV/E-PLAN presentes, E1/E2/E10 AUSENTES por la guarda del guión, invariantes; render de anclas + paso estable), **mutado** (checkbox malformado → rojo → restaurado). Meta: este mismo paso aparece en la página al desplegarse (`2c4517e`, en producción 31-07 con Deploy+Tests verdes)
- [x] **P-PLAN-04** · **Marcador de «hoy» preciso en el Gantt** (feedback del dueño con captura: la línea era tenue, sin rótulo, y el eje solo marca meses — no se leía el día exacto). Tres refuerzos con datos que ya existían: chip **«Hoy · d mes»** sobre la línea (clampeado 4–96% para no recortarse en los bordes; la línea queda en el % exacto), línea sólida `brand-600`, y la fecha completa fija en la cabecera del card («Hoy: dd-mm-aaaa»). Todo desde `FechaNegocio` (día de negocio chileno, doctrina P-TZ-01) vía `PlanProyecto::hoyFecha()`. +1 candado determinista (la página muestra la fecha del día de negocio computada, no hardcodeada). Verificado en preview: chip centrado al píxel sobre la línea (725==725), sin pisar los rótulos de mes, 375px sin scroll horizontal (`e02cbfd`, en producción 03-08 con Deploy+Tests verdes)
- [x] **P-PLAN-05** · **«Plan del proyecto» fuera de Administración** (pedido del dueño 03-08 con captura): el ítem pasa del acordeón a **link directo de primer nivel al final del menú** — 1 línea movida en `MenuPrincipal::MODULOS` + ícono nuevo `presentation-chart-bar` (Heroicons outline, copiado de `pencil` CON su guarda `@props`/`@php` del tamaño, gotcha [2026-07-24]). +1 candado: `plan` existe como link directo y NO vive bajo `administracion.items` (un merge no puede devolverlo adentro). Verificado en preview: fuera del acordeón, último del nav, con ícono y `aria-current`; la poda por permisos intacta (este mismo push, 2026-08-03)

### E-MENU · PLAN-MENU-DENSIDAD — menú 47→32, consolidar sin perder nada — ✅ CERRADO 2026-08-18 con QA final del dueño (plan y acta: `docs/planes/PLAN-MENU-DENSIDAD.md`)
> Directriz del dueño (11-ago): «poco a poco, lento pero seguro, decisiones con calma». El lente de cada veredicto: **«¿este apartado puede ser integrado con otro, o necesita vivir sí o sí solo?»**. Protocolo: un lote = un merge = una doble llave del dueño, QA por bloque en celular, forjado por Max-1 con verificación del Director en cada lote (suite completa local de AMBOS + CI verde). Resultado: **47 → 32 rótulos en doce lotes**, cero pantallas perdidas, cero permisos perdidos, cero rojos propios. Dos «vive-solos» decididos con evidencia (Informe ST y Conductores: audiencia partida entre dominios sin anfitrión común — consolidarlos habría regalado 403s o filtrado pantallas). Queda en la casa: `<x-tab-nav>` hasta 4 columnas (mapa count→clase anti-purga), `MenuConsolidacionesTest` con 14 entradas mutadas, el molde P-NAV-06 para mover pantallas dentro/fuera del menú con rastro, y la doctrina «el menú jamás ofrece un 403».
- [x] **P-MENU-L1** · Piloto: «Precios» vive como pestaña del Catálogo (tab-nav Productos · Listas de precios); nace el mini-candado `MenuConsolidacionesTest`, heredado por todos los lotes siguientes (`ca91422`, 12-ago) — menú 47→46
- [x] **P-MENU-L2** · Retiro completo del boceto «Seguimiento» (ítem, ruta, vista, componente y su test): el único lote que RESTA código, +3/−244; la decisión de negocio sobrevive en `docs/reglas/` (`ee1a72c`, 13-ago) — 46→45
- [x] **P-MENU-L3** · «Estado» vive como pestaña «Estado de la conexión» de Documentos; nace el componente compartido `<x-tab-nav>` extraído al 3er uso (`47785ad`, 13-ago) — 45→44
- [x] **P-MENU-L4** · «Códigos QR» entra como BOTÓN gateado en la cabecera del Listado ST (no pestaña: permisos NO idénticos, una pestaña regalaba 403 al vendedor con `view`) (`6d6a2ce`, 13-ago) — 44→43
- [x] **P-MENU-L5** · «Servicios de terreno» (tarifario UF) como pestaña gateada de la Agenda; el forjador corrigió el dictado con evidencia (el técnico industrial ve la Agenda sin el permiso del tarifario) (`c5f5b47`, 13-ago) — 43→42
- [x] **P-MENU-A1** · Bloque A (ST): «Costos generales de reparación» al Listado ST vía desplegable «Configuración» (QR + Costos por `@canany`); decisión de UX ratificada por el dueño (`0e9feaa`, 13-ago) — 42→41
- [x] **P-MENU-A2** · «Traslados al taller» como pestaña del Listado ST conservando su OR de permisos; cierra el Bloque A con el Informe ST declarado VIVE SOLO (1er nudo de audiencia) (14-ago) — 41→40
- [x] **P-MENU-B1** · Bloque B (Logística): «Cargas reales» como pestaña del Simulador (permiso idéntico por construcción: mismo grupo de rutas); Conductores VIVE SOLO (2º nudo, mismo patrón del Informe) (`c67d882`, 14-ago) — 40→39
- [x] **P-MENU-C1** · Bloque C (Administración): «Roles» como pestaña GATEADA de Usuarios (`manage roles` = solo admin ⊂ `view users` = admin+3 jefes); card «Roles» del Inicio retirada; de pasada, hotfix `5892eea` a la bomba de calendario de `VisitaIndustrialTest` (`fc47fd1`, 17-ago) — 39→38
- [x] **P-MENU-C2** · «Registro del sistema» 3→1: Auditoría rebautizada absorbe Notificaciones e Historial de aprobaciones — primera consolidación múltiple, tab-nav TRIPLE, cards del Inicio 3→1, rastro del QA 15-07 preservado (`6fa64cd`, 17-ago) — 38→36
- [x] **P-MENU-D1** · Bloque D (Producción): «Kardex» vuelve a ser hija del panel (botón de cabecera + Volver restaurado con candado en el mismo commit); estreno del molde P-NAV-06 con rastro de tres vidas (`61dd90d`, 18-ago) — 36→35
- [x] **P-MENU-E1** · El cierre: «Configuración de producción» 4→1 (Máquinas anfitriona · Tipos de botellón · Recetas · Moldes, sin gateo — permiso idéntico ×4); se paga la deuda del `<x-tab-nav>` a 4 columnas; el forjador cazó que `grid-cols-4` base NO estaba en el bundle (el grep contó el substring de `xl:grid-cols-4`) y aplicó I-06 (+1 regla/0 caídas) (`8fb0c5c`, 18-ago) — 35→**32**

### E-PARAM · PLAN-PARAMETRICOS — cacería de hardcodes: lo que el dueño debería poder cambiar sin programador (plan: `docs/planes/PLAN-PARAMETRICOS.md`)
> Pedido del dueño 18-ago, sucesor directo del PLAN-MENU-DENSIDAD: valores hardcodeados que deberían ser paramétricos, módulo por módulo en el orden de la sidebar (Dashboard → Comercial → … → ST → Plan). Tres niveles por hallazgo — 1 editable en caliente (tabla `configuracion` + UI), 2 config de despliegue (`config/*.php`), 3 se queda con porqué escrito — y el veredicto de cada uno es del dueño sobre el mapa de auditoría del módulo (fase A solo-docs, fase B lotes con doble llave). Regla de oro: parametrizar NO cambia comportamiento — el valor actual queda como default, delta 0 tests salvo los candados nuevos del parámetro.
- [x] **P-PAR-DASH-F0** · Mapa de auditoría del Dashboard (anexo §5.1 del plan): 8 hallazgos — 4 nivel 1 aprobados por el dueño (las 3 ventanas de días del pulso + la card Sucursales que nombra las 4 a mano), 4 nivel 3 confirmados (con los porqués), 6 anotaciones cross-módulo que quedan para sus auditorías; alerta clave: el `$d7` bicéfalo y los rótulos gemelos de la vista que deben derivar del parámetro (`8cf58ca`, 18-ago)
- [x] **P-PAR-DASH-1** · Las dos ventanas del pulso configurables: claves `dashboard_dias_serie_produccion` + `dashboard_dias_referencia_merma` (grupo dashboard, default 7 = valor histórico, seeder idempotente con ayuda en español, rango 2-31 en la UI vía mecanismo `RANGOS` reutilizable); rótulos «Últimos {N} días» / «prom. {N} días» DERIVADOS del valor del cálculo; 4 candados molde del proyecto (default idéntico + independencia entre claves con cifra calculada + rango ambos bordes) + mutación 7→9 verificada; delta exacto +4 tests, cero cifras viejas cambiadas (`bcbfb00`, 18-ago)
- [x] **P-PAR-DASH-2** · Cortes de antigüedad del taller configurables (claves `dashboard_corte_taller_reciente`/`_antiguo`, defaults 7/30, validación cruzada por PARES_ORDENADOS) + desacople del `$d7` bicéfalo (la «última semana» queda fija con variable propia y porqué nivel 3); rótulos derivados con la aritmética exacta; candado del desacople con cifra; mutación verificada (`0c2bcad`, 18-ago)
- [ ] **P-PAR-DASH-3** · La card Sucursales del Inicio deja de nombrar las sucursales a mano: la descripción deriva de la tabla `sucursales` (sucursal nueva aparece sola) — cierre del módulo Dashboard + QA del dueño
- [ ] **P-PAR-SIGUIENTES** · Módulos restantes en orden de sidebar (Comercial → Operación → Logística → Facturación → Administración → Mi producción → Mis entregas → Aprobaciones → ST → Plan), cada uno con su fase A de mapa + veredictos + fase B de lotes

---

## 5. F2 · Núcleo operativo

### E3 · M04-F1 Inventario: del espejo a módulo (~2.5 sem) — [B:D-003]
**Base real:** el espejo read-only YA existe (`Bodega`, `Stock`, `StockSync`, cron `:45` — grilla `*/15` de I-01) — documentado en `HANDOFF.md` §8e. E3 construye encima, no desde cero.
**Rama:** `feature/m04-inventario-f1` · **Depende de:** D-003 (levantamiento) — D-002 deseable, no bloqueante (default conservador).
**Hecho cuando:** stock mostrado cuadra contra Bsale en 5 SKUs × 3 bodegas (QA staging); roles operativos reciben 403 en la vista cruzada.

> ⚠️ **Enmienda (2026-08-06, dictado v36):** `PLAN-M04.md` v2 (VIGENTE, visto bueno del dueño 06-ago) reorganizó
> la F1 como **P-M04-10/11/12** (bodegas full paramétricas, GO a Max-1) — ojo con la **colisión de número**: el
> `P-M04-10` viejo de E4 (tests de concurrencia) es OTRO paso y sigue vivo; la renumeración/reestructura de E3-E4
> contra el plan v2 es del Director. El viejo `P-M04-01` queda **absorbido** por el nuevo P-M04-10 (mismo trabajo).

- [x] **P-M04-10 (v2)** · Bodegas editables: capa local (`sucursal_id`/`proposito`/`en_operacion`/`clasificacion_confirmada`/`estado_baja`/`alias`), seeder D-003 por `bsale_office_id` que jamás pisa una fila confirmada, edit/update con `manage sucursales` (guardar = confirmar), badges y scopes — rama `feature/m04-bodegas-parametricas` `9e23e62`; baterías `ClasificacionBodegasSeederTest` (5) + `BodegaClasificacionTest` (9), 3 mutaciones rojas donde deben
- [x] **P-M04-11 (v2)** · Guardas de sucursales COMPLETAS: destroy bloquea por bodegas, hojas de ruta, devoluciones y traslados ST (el sweep encontró 4 FKs RESTRICT que daban 500) + test de la guarda de máquinas que faltaba + `Js::from` en el confirm (apóstrofo) — mismo commit; 6 tests nuevos en `SucursalManagementTest`
- [x] **P-M04-12 (v2)** · Adopción automática: bodega nueva del sync nace por clasificar + aviso M15 `bodega.nueva` a `manage sucursales` (una sola vez, `wasRecentlyCreated`; candado «sync 2× no duplica ni pisa lo local») + botón instructivo «Agregar bodega» — mismo commit; 4 tests en `BsaleStockSyncTest`
- [x] **P-M04-20 (v2, F2)** · Wizard de baja con orden de traslado: vacía → baja al tiro; con stock → orden con FOTO denormalizada + `pendiente_traslado`; **cierre automático post-sync** (orden `completado` + aviso M15 al solicitante) + aviso «llegó stock» una vez por orden sin revivir la bodega + anular + Excel sobre el escritor de la casa — rama `feature/m04-baja-bodegas` `1b3d9bf`; los 7 candados del dictado v38 (3 mutados en rojo); `BajaBodegaTest` (11) + 4 en `BsaleStockSyncTest` + `TrasladoBodegaExcelTest` (5)
- [x] ~~**P-M04-01**~~ · Campos locales en `bodegas` — **absorbido por P-M04-10 (v2)** el 2026-08-06 *(marca `[B:D-003]` retirada el 2026-07-26: `docs/DECISIONES.md` §D-003 ya la había tachado al posponerse M04 en R-002; la etiqueta acá había quedado stale y hacía ver como bloqueado un paso que solo está pospuesto)*
- [ ] **P-M04-02** · Vistas de stock por producto/bodega/sucursal + permisos `view stock`/`manage inventario`
- [ ] **P-M04-03** · Vista cruzada filtrada por perfil (accesos por rol se definen al CIERRE del módulo — estrategia D-002; interim: solo admin/jefes)
- [ ] **P-M04-04** · Alertas básicas: bajo mínimo, sin movimiento 10 días; punto de reorden por SKU
- [x] **P-M04-05** · Migración `unique` en `bsale_variant_id` — habilitada: P-S0-08 confirmó **0 duplicados** en prod (evidencia `docs/qa/INFRA/2026-07-02--INFRA--duplicados-variantid-catastro-bodegas.md`, 2026-07-02; marcado `[x]` el 2026-07-26 — llevaba 24 días hecho con la casilla sin marcar)
- [ ] **P-M04-06** · Tests + merge + QA staging

### E4 · M04-F2 Reservas por vendedor + transferencias (~3 sem)
**Objetivo:** la corrección #2 de Luis: reservas con dueño (vendedor) y vencimiento; movimientos locales (patrón kardex de M11, sin corromper el espejo); transferencia entre sucursales consumiendo M14; alertas de reservas vencidas.
**Rama:** `feature/m04-reservas-transferencias` · **Depende de:** E2, E3; D-005 define si el traspaso empuja a Bsale o queda local (diseñar para ambos).
**Hecho cuando:** flujo Coquimbo C-08 digitalizado punta a punta en staging (solicitar → aprobar desde celular → notificar → reserva bloqueada); tests de concurrencia (`lockForUpdate`).

- [ ] **P-M04-07** · Esquema reservas (dueño, vencimiento configurable) + movimientos locales
- [ ] **P-M04-08** · Transferencias entre sucursales vía M14 + notificación M15
- [ ] **P-M04-09** · Liberación automática de reservas vencidas (scheduler) + alertas
- [ ] **P-M04-10** · Tests de concurrencia + merge + QA con 2 usuarios (vendedor + jefe)

### E5 · M05 Ciclo de la factura (~6 sem, 3 sub-fases mergeables)
**Rama(s):** `feature/m05-cotizaciones` → `feature/m05-emision-bsale` → `feature/m05-boleta-rapida`.
**Hecho cuando:** una cotización creada en staging termina como DTE real en el **sandbox** de Bsale (JAMÁS probar escritura contra producción primero); aprobación de Héctor en 1 paso.

- [ ] **P-M05-01** · F1: `cotizaciones`/`cotizacion_items`, vencimiento configurable, validación de stock asignado, estados del documento
- [ ] **P-M05-02** · F1: asignación vendedor/cliente/stock + envío por correo (M15)
- [ ] [B:D-005] **P-M05-03** · F2: emisión DTE vía Bsale API (sandbox primero), folio/urlPdf, idempotencia por `salesId`
- [ ] **P-M05-04** · F2: aprobación Héctor 5→1-2 pasos (reglas M14 auto-validan pago+stock+descuento) + QR del documento (insumo M07)
- [ ] **P-M05-05** · F2: cierre administrativo con conciliación de pagos
- [ ] [B:D-004] **P-M05-06** · F3: boleta rápida <1 min sin datos de cliente
- [ ] **P-M05-07** · F3: bono conductor por destino/km (tabla configurable) — dato para Matías/RRHH
- [ ] **P-M05-08** · Tests por sub-fase + QA staging del ciclo M-02 completo de la biblia

### E6 · M13 Devoluciones (~2.5 sem)
> ⚠️ **Nota de corrección (2026-07-01):** M13 NO tiene código. No confundir con la acción `devolver` de los reportes de producción (M11) ni con el taller (M12). Esta nota existe porque una versión anterior del HANDOFF daba a entender lo contrario.

> ⚠️ **Enmienda del criterio de «hecho» (2026-07-30, decisión del dueño sobre el parte v32 de Max-1):** el «Hecho cuando» exigía *«…hasta el reingreso a stock»*, que **no se puede cumplir** — el stock es un espejo read-only de Bsale y escribirlo espera a M04/D-005, así que E6 no habría podido cerrarse nunca. Se sella el patrón ya sancionado en M11: el reingreso se registra en un **kardex LOCAL** (`produccion_movimientos` «NUNCA toca `stocks`/`bodegas`», `HANDOFF.md:376`). De paso se corrigen las dos líneas que la misma decisión falsificaba: las dependencias y el texto de P-M13-03. Fundamento en `docs/planes/PLAN-M13.md` §4.

**Rama:** `feature/m13-devoluciones` · **Depende de:** **M14/M15** (las dos cerradas). **NO depende de E4/M04 ni de E5/M05** — D-005 lo dice textual: *«Mientras tanto: M05-F1, **M13**, diseño de M07 no dependen; el espejo read-only ya funciona»* (`docs/DECISIONES.md:144`). El reingreso va a un kardex local y M13 **no emite** nota de crédito.
**Hecho cuando:** flujo A-12 completo en staging desde el link público del cliente hasta el **CIERRE de la devolución** —reembolso aprobado vía M14 **o** movimiento de reingreso registrado en el kardex local—; límites de upload verificados por IA-cPanel.

- [x] **P-M13-01** · Formulario público del CLIENTE (ruta sin auth con token firmado) + fotos obligatorias (commits `7434476` esquema + `1cf3e02` frontera pública; GET y POST firmados, throttle propio, `DevolucionPublicaTest` 7 verdes)
- [x] **P-M13-02** · Categorización transporte/fábrica/otro + reglas automáticas por tipo y origen (commit `1cf3e02`; transporte exige transportista+seguimiento EN EL SERVICIO, `DevolucionAdminTest` 8 verdes)
- [x] **P-M13-03** · Reembolso vía M14 si ≥ umbral; reingreso como movimiento del **kardex LOCAL de devoluciones** si el producto está apto — **nunca escribe `stocks`/`bodegas`**; el push a Bsale espera a M04/D-005 (commits `30e8e26` motores + `1cf3e02`; candado «stocks byte a byte igual» MUTADO en rojo, `ReembolsoDevolucionTest` 5 verdes)
- [x] **P-M13-04** · Reportes por causa y por canal + tests + QA staging (desde un celular) — **segundo lote** (commit `bf31848`: informe mes/año idioma ST + badge «por recibir»; `DevolucionInformeTest` 6 verdes con el candado de los dos bordes; QA staging pendiente del deploy)

### E7 · M07 QR anti-fraude en retiro (~2 sem)
**Contexto:** caso real de intento de retiro con factura adulterada (biblia §4/M07). Mayor ROI político del proyecto.
**Rama:** `feature/m07-qr-retiro` · **Depende de:** E5-F2.
**Hecho cuando:** doble escaneo del mismo QR dispara alerta y bloquea; retiro > umbral exige aprobación remota al celular; pantalla de bodega refresca sola.

- [ ] **P-M07-01** · Validación de QR en puesto de bodega (estado/monto/cliente/items) + registro de escaneos
- [ ] **P-M07-02** · Alerta de doble entrega + bloqueo; entrega total/parcial
- [ ] **P-M07-03** · Aprobación remota sobre umbral (M14) + pantalla cola de bodega "tipo McDonald's" (polling)
- [ ] **P-M07-04** · Tests + QA staging con celular real escaneando QR impreso

### E8 · M08 Despacho + PWA conductor MVP (~5.5 sem) — mayor riesgo técnico
**Alcance MVP (biblia):** guía de despacho, hoja de ruta por zona con estados, PWA conductor con la arquitectura del spike (E2): ruta del día, confirmación firma+foto+hora **offline-first**, forma de pago; cierre de ruta alimenta bono (E5). Cotizador transportistas solo si hay API keys a tiempo. Venta-en-ruta y plan B = post-MVP.
**Ramas:** `feature/m08-despacho` + `feature/m08-pwa-conductor` · **Depende de:** E5, spike aprobado, D-006 (zona simple vs CRM).
**Hecho cuando:** un pedido de staging se entrega con el teléfono en modo avión y sincroniza al volver (firma y foto persistidas); ciclo M-03/M-04/M-05 de Mirador sin papel punta a punta.

- [ ] **P-M08-01** · Guía de despacho al emitir/cargar + estados de ruta (asignada→cargada→en ruta)
- [ ] **P-M08-02** · Hoja de ruta por zona *(enmienda 03-ago: D-006 RESUELTA — por zona, vendedor NO fijo; diseño en `docs/planes/PLAN-DESPACHOS-V2.md`, paso P-DSP-08)*
- [ ] **P-M08-03** · PWA conductor: ruta del día + confirmación offline-first (arquitectura del memo `docs/SPIKE-PWA.md`)
- [ ] **P-M08-04** · Cierre de ruta → cierre administrativo + bono conductor
- [ ] **P-M08-05** · Cotizador transportistas (Chilexpress/Starken/Cruz del Sur) — si hay API keys; si no → F4
- [ ] **P-M08-06** · Tests + QA offline agresivo (guion: matar app a mitad de sync, doble envío, foto grande con mala señal)

### E9 · M12 Servicio técnico completo (~2.5 sem — el taller base YA existe, ver HANDOFF §8f)
**Rama:** `feature/m12-taller-completo` · **Depende de:** E1 (alertas), E5 (vincular documento para garantía).
**Hecho cuando:** máquina de prueba recorre pre-ingreso→diagnóstico→aprobación por link→retiro; alertas 3/6/12 meses disparan con fechas simuladas.

- [ ] [EN CURSO] **P-M12-01** · Pre-ingreso online con QR (cliente llena antes de llegar) — **piloto adelantado** en rama `feature/m12-ingreso-qr-piloto` (2026-07-06): formulario público sin login vía QR firmado por sucursal (`URL::signedRoute` + throttle + honeypot) → orden real `fuente='qr'` sin confirmar → el encargado confirma la recepción (`confirmada_at`) y ahí se dispara el correo (Mailable standalone, migrable a M15). Historial **compartido** por las 3 sucursales con filtro/badge por **sucursal de recepción** y rótulo "se repara en **Mirador** (casa matriz)" — Coquimbo/Abate reciben pero no reparan. 391 tests verdes. Gate R-31 **APROBADO CON OBSERVACIONES** + **mergeado a main** (`1639d71`, deploy OK 16s) — **live en producción**. **Falta:** configurar SMTP en el `.env` del servidor (P-M15-10) para que el correo de confirmación se entregue (ya es resiliente si falta) + QA real en prod (escanear QR → enviar → confirmar).
- [ ] **P-M12-02** [EN CURSO — fase CORREO hecha 2026-07-21, ampliada 2026-08-06] · Cotización estructurada del técnico + aprobación del cliente por link. **Hecho:** snapshot `orden_servicio_cotizaciones` + carta formal por correo + página pública firmada ACEPTO/NO ACEPTO + avisos M15 a tecnico/jefe_ventas/vendedor/admin (720 verdes; bitácora 2026-07-21). **Ampliación 06-08 (dueño):** «¿por qué?» opcional junto a la respuesta (da vuelta el «sin comentario» del 30-07) que viaja en la campanita; carta «pase a retirar su equipo» cuando el cliente NO acepta (un aviso por cotización, con registro de quién avisó); campanita también en garantía (`garantia.detalle_enviado`); enviar la cotización desde una etapa previa mueve la orden sola a «Cotización». **Enmienda 07-08:** «Enviar» quedó en la misma fila que «Guardar» y ES un submit de ese formulario → guarda y manda en un paso (sale lo de la pantalla, no el snapshot viejo); la tarjeta de envío quedó solo como constancia y no se dibuja si no se envió nada. **Enmienda 07-08 (flujo):** el taller NO coordina plata — se le quitó `autorizar reparacion` al rol `tecnico` (migración, no solo seeder), la ficha no le muestra pagos y la aceptación del cliente ES su luz verde; cierra con el botón **«Avisar que está listo para retirar»** (carta al cliente con el monto aceptado + «paga en sala de ventas al retirar», campanita `taller.listo_para_retiro`). El cobro es en sala de ventas al retiro. **Enmienda 07-08 (tarde):** tras un NO ACEPTO la cita de retiro sale **sola al momento del rechazo** (día hábil siguiente vía `DiasHabiles` + `feriados_chile`; carta estilo banco sin respuesta; campanita «ciclo cerrado» al taller); el botón manual quedó de respaldo. La palabra que manda en la ficha es la de la **última** cotización (una aceptada superada por re-cotización ya no autoriza ni dice «puedes reparar»). **Falta:** botón `wa.me` con el mismo link (hasta D-007)
- [ ] **P-M12-03** · Alertas 3m (fin garantía) / 6m (bodegaje $) / 12m (desarme/reventa/donación con registro de destino) + tablero de máquinas próximas a plazo
- [ ] **P-M12-04** · Sugerencia automática de repuestos según histórico + cobro hora de servicio en no aprobadas
- [ ] **P-M12-05** · Tests + QA staging del flujo completo

### E10-v1 · M16 BI corte 1 (~1 sem, tras E5)
- [ ] **P-M16-02** · Ventas/descuentos por vendedor con margen (datos E5) + transferencias con aprobador (E4)

> Candidato para v2 (del escaneo de Luis, `docs/CORRECCIONES-LUIS.md`): reporte "pedidos de repuestos de servicio técnico" (la función operativa vive en M12; el listado/reporte iría en M16-v2).

### PLAN-M11-FINAL · M11 Producción versión definitiva (plan: `docs/planes/PLAN-M11-FINAL.md`, GO del dueño 07-ago; 2 streams paralelos, fuera de la E-numeración)

**Objetivo:** la información capturada VUELVE procesada a cada rol y producción se conecta al kardex (recetas/backflush) y a los moldes. F1: recetas+backflush (Max-1) ∥ paradas PWA (Max-2) · F2: OEE + alertas SIC · F3: moldes + kaizen. Insumo: benchmark de doble vía reconciliado (07-ago).

- [x] **P-M11-10 (F1, stream A)** · Receta paramétrica + backflush al aprobar: tabla `recetas` (producto botellón → rol preforma/tapa, cantidad decimal(14,4), `confirmada` estilo D-003) + seeder hipótesis [B] 1+1 que jamás pisa lo editado + CRUD `admin.recetas.*` con ítem de menú; el kardex descuenta **(buenos + merma) × receta** con tipo nuevo `consumo_tapa`; preview del reporte = `planParaReporte()` (la MISMA fuente que persiste — murió la divergencia preview/kardex); fallback sin receta = comportamiento histórico EXACTO (`ProduccionKardexTest` verde sin tocar) — rama `feature/m11-recetas-backflush` `f654a23`; candados del dictado v40 con 3 mutaciones rojas; `RecetaBackflushTest` (9) + `RecetaSeederTest` (2) + `RecetaCrudTest` (7)
- [ ] **P-M11-20 (F1, stream B — Max-2)** · Paradas con duración en la PWA (motivo tipificado + clase + origen + inicio/fin, por la MISMA cola offline)
- [x] **P-M11-11 (F2, stream A)** · OEE por máquina + Pareto de paradas: D×R×Q en el informe por máquina existente (presets semana/mes sobre el mismo rango) + comparativa contra `maquinas.oee_target` (B4) en el panel; disponibilidad = turnos × `produccion_minutos_turno` (clave nueva de Configuración — la duración del turno no existía como dato, [B] editable) − paradas NO planificadas (módulo-1440 de Max-2, solo lectura); rendimiento con `recetas.ciclo_ideal_seg` ÷ cavidades activas — sin ciclo se DECLARA, sobre 100 % avisa «ciclo mal cargado», jamás la cifra; merma con scrap de arranque separado (el motivo entró a MOTIVOS_DEFECTO: el dictado lo daba por existente y no estaba) — rama `feature/m11-oee-pareto` `4981c42`; candados del dictado v42 (3 mutados en rojo); `ProduccionOeeTest` (11)
- [ ] **P-M11-21 (F2, stream B)** · Alertas SIC (corte cada 2h) + panel vivo del jefe
- [ ] **P-M11-22 (F2, stream B)** · Semáforo de preformas + notas del jefe en mi-reporte
- [x] **P-M11-12 (F3, stream A)** · Molde como entidad: ficha estilo M18 (`moldes` + `molde_mantenciones`) con contador de ciclos que se alimenta solo al aprobar (unidades ÷ cavidades activas, POR TANDA al molde activo del tipo, mismo guard del backflush — devolver no resta, re-aprobar no re-suma), umbral → aviso M15 una vez por cruce (registrar mantención resetea y RE-ARMA), correctiva automática desde parada «Molde dañado» (una por reporte, anclada a él), y elección del jefe al aprobar cuando hay 2+ moldes activos del tipo (`produccion_reportes.molde_id` aditivo — frontera declarada). **El ciclo ideal NO se movió: la receta sigue como única portadora** (moverlo dejaba el OEE sin ciclos el día del deploy; la ficha lo muestra con enlace) — rama `feature/m11-moldes` `9728b0c`; candados del dictado v43 (contador mutado en rojo); `ProduccionMoldeTest` (11)
- [ ] **P-M11-23 (F3, stream B)** · Kaizen digital (proponer mejora → cola del jefe)

---

## 6. F3 · Piloto Mirador (E11) → **H5' go-live 11-ene-2027**

**Racional del re-baseline:** W34 original caía en fiestas; marcha blanca en diciembre (doble registro papel+sistema) y corte de papel post-fiestas. Es colchón, no atraso.
**Depende de:** E5–E9 estables en staging; **D-001 (nombre) es última llamada aquí**.
**Hecho cuando:** 1 semana de marcha blanca sin incidentes P1; checklist de go-live firmado por Luis/Mauricio.

- [ ] **P-F3-01** · Hardening: índices/carga (~48k clientes, ~28k stocks), revisión de seguridad de rutas públicas (M12/M13)
- [ ] **P-F3-02** · Backup automatizado + **restore ENSAYADO** (delegación IA-cPanel: dry-run en BD aparte)
- [ ] **P-F3-03** · Migración de datos: peso/dimensiones por SKU, usuarios reales con roles/sucursal
- [ ] **P-F3-04** · Manuales de 1 página por rol + capacitación (Pedro, Ricardo, Héctor, sopladores)
- [ ] **P-F3-05** · Marcha blanca diciembre (doble registro) + monitoreo semanal
- [ ] **P-F3-06** · Ejecutar la separación real staging/producción antes de usuarios reales (hoy staging = prod): prod = `daligo.impdali.cl` con BD limpia, staging queda en `staging.impdali.cl` (decisión D-011, 2026-07-02)
- [ ] **P-F3-07** · Go-live + criterio "1 semana sin P1" antes de cortar papel

---

## 7. F4 · Rollout Abate (E12) → **H6' ≈ 9-feb-2027**

- [ ] **P-F4-01** · Configuración/usuarios/capacitación Abate + especialidad taller (recepción que deriva a Mirador — validar flujo con Gonzalo)
- [ ] **P-F4-02** · **M09-mini (stretch):** bandeja de órdenes ML con filtrado de canceladas + boleta vinculada a ID de orden — ataca las **991 órdenes pendientes** reales
- [ ] [B:D-008] **P-F4-03** · Impresión de etiquetas térmica (según decisión de hardware)
- [ ] **P-F4-04** · Go-live Abate

---

## 8. F5 · Coquimbo + cierre (E13) → **H7' ≈ fin feb-2027**

- [ ] **P-F5-01** · Configuración Coquimbo (producción ya opera vía M11) + flujo C-01/C-08 con transferencias reales
- [ ] **P-F5-02** · Deuda técnica: webhooks Bsale (reemplaza polling), staleness de espejos, push kardex M11→Bsale (si D-005 lo validó), plan de migración MySQL 5.7→8.x
- [ ] **P-F5-03** · Documentación final + manuales + retrospectiva + traspaso a soporte

---

## 9. Módulos fuera de fase

| Módulo | Estado | Regla |
|---|---|---|
| **M06 POS sala de venta** | [STANDBY] | "Lo que funciona no se toca" (Luis). No construir. Revisable post-MVP. |
| **M09 Marketplaces completo** | [BACKLOG] | Solo el M09-mini entra como stretch en F4 (P-F4-02). API Falabella queda fuera. |
| **M10 eCommerce** | [BACKLOG] [B:D-009] | Fuera de los 9 meses. No dejar que entre por la ventana. |

---

## 10. Tracker de avance (base 100, ponderado por esfuerzo)

> Regla anti-autoengaño: un ítem solo suma cuando su criterio de "hecho" pasó QA en staging.
> M09/M10 están fuera de la base: si entran, suman como bonus, no diluyen.
>
> **Re-baseline del tracker · 2026-07-26.** Estaba mintiendo **hacia abajo**: marcaba
> M14, M15 y M16 en **0 %** aunque §4 de este mismo archivo las declara cerradas
> (8-jul, 17-jul, 14/23-jul) con QA del dueño aprobado, y M12 en 25 % cuando el
> taller es hoy el módulo más grande de la app. La base sube de 100 a **105**
> porque entra **M17 Servicio en terreno**, construido en julio y ahora sí en la
> biblia. Cada % corregido lleva su fundamento en la columna de la derecha.
>
> **Actualización · 2026-08-10.** **F1 de PLAN-M11-FINAL COMPLETA** con los DOS streams
> en producción: backflush de preformas al aprobar (Max-1, `3cc708f`) y paradas con
> duración en la PWA (Max-2, `1c040c3`) — primer módulo forjado EN PARALELO por ambos
> forjadores con frontera declarada, cero colisiones. M11 → 85 %, total ≈ 58 %. La
> pausa del 07-ago quedó levantada el mismo día por el dueño para M11 (benchmark de
> doble vía en `docs/investigacion/` como insumo). F2 en curso: OEE (A) + alertas SIC
> y panel vivo (B).
>
> **Actualización · 2026-08-07.** **F2 de M04 en producción** (`237185b`, doble llave):
> el wizard de baja — vacía muere al tiro, con stock exige orden de traslado con foto +
> Excel + cierre automático post-sync. M04 → 40 %; ciclo de la factura ≈ 53 %. **La
> flota entera queda EN PAUSA por orden del dueño (vía Luis)**: F3, ronda 2 y todo GO
> congelados hasta nueva orden (dictados v39/v18).
>
> **Actualización · 2026-08-06.** **M04 Inventario se destraba tras 24 días pospuesto**
> (R-002): D-003 resuelta parcial en la mañana (Excel Ricardo+Luis) y **F1 de PLAN-M04 en
> producción el mismo día** (`276a54f`, doble llave) — bodegas full paramétricas por
> corrección de rumbo del dueño. M04 → 30 %. **El ciclo de la factura llega al 50 %
> justo** (17.5 de 35) — y por primera vez con avance en su INICIO, no solo en los
> extremos.
>
> **Actualización · 2026-08-05 (tarde).** Cuarta doble llave del día: **P-DSP-09, la PWA
> del conductor SOBRE la hoja** (`f3be802`) — dirección/teléfono por parada, receptor
> obligatorio, cobro en entrega, rechazo en puerta con aviso. **F2 de PLAN-DESPACHOS-V2
> completa** (M08 → 75 %). El ciclo de la factura va en **≈ 46 %** (16.15 de 35).
>
> **Actualización · 2026-08-05.** Otras dos dobles llaves: la **hoja de ruta digital**
> (M08 → 65 %) y el **informe de devoluciones que COMPLETA E6** (M13 → 85 %). El ciclo
> de la factura va en **≈ 43 %** (14.95 de 35). M13: de cero a módulo completo en 6 días.
>
> **Actualización · 2026-08-04 (tarde).** Dos merges con doble llave: la **PWA del
> conductor** (M08 30 → 55 %) y el **lote 1 de M13 Devoluciones** (0 → 60 %). El
> ciclo de la factura sube a **≈ 39 %** (13.75 de 35) — sigue entrando por los
> extremos: M05 aún no emite y M04 espera D-003 (Luis trabajando las bodegas).
>
> **Actualización · 2026-08-04.** Entra **M18 Logística** (peso 3, 75 %): la flota
> de vehículos, pedida por el dueño el 04-08 y construida ese día. La base sube de
> 105 a **108** y el global de ≈ 46 % a **≈ 47 %**. No es alcance nuevo inventado:
> es un módulo que la biblia no tenía y que la operación ya llevaba en una planilla
> (ver R-004 en §11).
>
> **Actualización · 2026-07-30.** Entra el stream DESPACHOS y el andamiaje DTE:
> M05 30 %, M07 70 %, M08 30 % — verificado contra `origin/main` (rutas, servicios
> y migraciones citadas en el informe de avance del 30-jul). El ciclo de la
> factura deja el 0 %: va en **≈ 31 %** (10.75 de 35 puntos).

| Ítem | Peso | % | Aporta | Fundamento del % |
|---|---|---|---|---|
| M01 Core | 6 | 100 % | 6.0 | — |
| M02 Catálogo+precios | 5 | 90 % | 4.5 | faltan webhooks y el enlace con M04 |
| M03 Clientes | 4 | 70 % | 2.8 | boleta rápida es de M05; historial post-M05 |
| M04 Inventario | 9 | **40 %** | 3.60 | **subido desde 30 % (07-ago)**: F2 de PLAN-M04 en producción (`237185b`) — **wizard de baja con orden de traslado**: bodega vacía muere al tiro, con stock exige destino + orden con foto denormalizada + Excel, cierre automático cuando el sync confirma stock 0, anulación que revive — **QA del dueño aprobado (07-ago, celular)**. F1 (06-ago, `276a54f`): clasificación editable, guardas, adopción automática — QA del dueño aprobado. Falta: kardex unificado (F3, EN PAUSA por orden del dueño) y el empuje a Bsale [B:D-005] |
| M05 Ciclo factura | 10 | **30 %** | 3.0 | **corregido desde 0 % (30-jul)**: andamiaje DTE completo y probado —puerto emisor Bsale, servicios, config, candados—, pero **NO EMITE**: `config/dte.php` con los 3 mapas vacíos, `emision_habilitada=false`, sin ruta de emisión ni comando `dte:emitir-prueba` (B6). Marcos activo aquí |
| M07 QR retiro | 4 | **70 %** | 2.8 | **corregido desde 0 % (30-jul)**: P-DSP-00..04 **en producción** — QR firmado de retiro, validación en puesto de bodega, doble-retiro cerrado (lock + candado a nivel grammar). NO cierra «retirar carga ajena» (decisión de producto reportada) y falta QA de bodega con papel impreso |
| M08 Despacho+PWA | 12 | **75 %** | 9.0 | **subido desde 65 % (05-ago tarde)**: P-DSP-09 en producción (`f3be802`) — **F2 de PLAN-DESPACHOS-V2 completa**: la PWA sobre la hoja (dirección/comuna/teléfono por parada, receptor obligatorio, cobro en entrega, rechazo en puerta + aviso M15). Antes ese día: hoja de ruta digital (`b9d89a3`); el 04-ago la PWA base (`d7803f9`). Falta: P-DSP-10 (cierre+bono, bloqueado por la ronda 2 con Luis) y el QA de campo punta a punta |
| M11 Producción | 6 | **100 %** | 6.00 | **PLAN-M11-FINAL 100% CONSTRUIDO (12-ago, `e180a6f`)**: kaizen digital cierra el plan — backflush, paradas con duración, OEE+Pareto, alertas SIC, panel vivo, semáforo de preformas, notas del jefe, moldes por ciclos y kaizen, en 5 días. Quedan solo datos [B] de Luis (GP, receta real, ciclos ideales, turnos) y pulido de claves de turno — no restan al alcance construido. Antes: **F2 COMPLETA + moldes de F3 (11-ago)**: semáforo de preformas + notas del jefe (`c8b343c`) y **el molde como entidad** (`e4248aa`: ficha tipo M18, contador de ciclos automático, aviso de mantención por umbral, correctiva automática por «molde dañado») en producción. Falta SOLO: kaizen P-M11-23, pulido de claves de turno, y las confirmaciones [B] de Luis (GP, receta real, ciclos ideales). Antes: **F2 casi completa (10-ago tarde)**: OEE por máquina + Pareto (`2264e8c`) y alertas SIC + panel «Hoy en vivo» (`3fbc7cd`) en producción — el jefe recibe proyección vs meta cada 2 h y el gerente tiene su número contra target. Falta P-M11-22 (semáforo de preformas + notas del jefe) para cerrar F2, y de F3 el kaizen — **los moldes (P-M11-12) quedaron en doble llave el 11-ago** (`feature/m11-moldes` `9728b0c`). Antes: **F1 COMPLETA (10-ago)**: backflush al aprobar (`3cc708f`, 08-ago) + **paradas con duración en la PWA** (`1c040c3`, 10-ago — 7 motivos tipificados, clase planificada/no derivada server-side, origen máquina/operario, scrap de arranque, cavidades activas, turno noche con módulo 1440). Falta F2 (OEE + alertas SIC/panel vivo, EN CURSO) y F3 (moldes, kaizen); «GP» sigue [B] de Luis |
| M12 Servicio técnico | 8 | **60 %** | 4.8 | **corregido desde 25 %**: taller completo + portal QR + cotización al cliente con respuesta + lotes en ruta + informes (E9: 2 pasos en curso de 5; faltan alertas 3/6/12m, sugerencia de repuestos y cobro) |
| M13 Devoluciones | 4 | **85 %** | 3.4 | **E6 COMPLETA (05-ago)**: lote 1 (`7750951`, QA del dueño aprobado) **+ informe por causa/canal y badge (`6c91f94`) en producción** — módulo de cero a completo en 6 días. Resta el QA de staging del informe y el empuje del kardex a stock real (espera M04/D-003) |
| M14 Aprobaciones | 5 | **90 %** | 4.5 | **corregido desde 0 %**: E2 cerrada 17-jul, QA 8/8 en producción. Descuenta que hay **una sola acción cableada** (ajuste de producción) |
| M15 Notificaciones | 5 | **80 %** | 4.0 | **corregido desde 0 %**: E1 cerrada 8-jul con entregabilidad verificada. Descuenta el **canal WhatsApp, que es un stub** (D-007 aplazada) |
| M16 BI | 7 | **35 %** | 2.45 | **corregido desde 0 %**: entregado el TABLERO (cortes v0/v1/v1.2); pendiente el BI de reportes, que depende de M05 |
| **M17 Servicio en terreno** | **5** | **85 %** | **4.25** | **ítem nuevo**: agenda, confirmación del cliente, instalaciones, conductores — en producción desde el 24-jul |
| **M18 Logística** | **3** | **75 %** | **2.25** | **ítem nuevo (04-ago, R-004)**: flota de vehículos en producción — ficha, semáforo automático de vencimientos y aviso 30 días antes; reemplaza la planilla «Control vehiculos», con **los 17 vehículos de la flota ya cargados** en producción. Descuenta el QA del dueño; kilometraje y mantenciones quedaron fuera de alcance por decisión del dueño |
| F3 Piloto (hardening/migración/capacitación) | 7 | 0 % | 0 | — |
| F4 Rollout Abate | 5 | 0 % | 0 | — |
| F5 Coquimbo + cierre | 3 | 0 % | 0 | — |
| **TOTAL** | **108** | | **63.35** | **≈ 59 %** |

> **Lo que el número no dice, y hay que decir:** el ciclo de la factura
> (M04 → M05 → M07 → M08, **35 de los 105 puntos**, el objetivo central del
> proyecto) pasó de 0 a **≈ 31 %** con el stream DESPACHOS — pero el avance entró
> **por los extremos** (retiro y despacho), no por el inicio: **M05 todavía no
> puede emitir un documento tributario real** y M04 sigue en espejo read-only
> esperando D-003. Hasta que exista la primera emisión de prueba (B6 + config +
> autorización de Gerencia), "factura sin papel" sigue siendo un titular en
> deuda, por muy verde que se vea el resto de la tabla.

**Lectura ejecutiva (hitos):** H1' decisiones 31-jul · H2 ✅ · H3' transversales 9-oct · H4' núcleo 5-dic · **H5' Mirador sin papel 11-ene-2027** · H6' Abate 9-feb · H7' cierre fin feb-2027.

---

## 11. Re-planificaciones

Cada cambio al plan se anota aquí con fecha y motivo. El ORDEN de los módulos es el de la biblia y no se cambia sin decisión registrada.

### R-001 · 2026-07-01 — Re-baseline inicial (sesión E0)
- **Qué:** fechas de hitos ajustadas (tabla §2); el orden de módulos NO cambió.
- **Por qué:** el código va ADELANTADO al Gantt (M01–M03+M11-F1+taller listos en W9; estaban planificados hasta W15–W23) pero las decisiones de F0 van ATRASADAS (debían cerrar en W8). El equipo real es 1 stream (dueño+IA), no 2 devs paralelos: la ruta es secuencial. H5 se movió del 21-dic al 11-ene-2027 porque W34 caía en fiestas (marcha blanca en dic, corte de papel después). Total: +4 semanas honestas vs plan original.
- **Además:** spike PWA offline adelantado a E2/W12 (el Gantt lo dejaba implícito en M08/W27) para atacar temprano el mayor riesgo técnico declarado (biblia §6/riesgos).

### R-002 · 2026-07-13 — Pivote a DESPACHOS; M04 pospuesto *(registrada retroactivamente el 2026-07-26)*
- **Qué:** el stream 2 se movió a DESPACHOS-V1 y **M04 Inventario quedó pospuesto sin fecha crítica**. El orden de la biblia dejó de cumplirse: F2 debía abrirse por M04.
- **Por qué:** D-003 (levantamiento de las 16 bodegas virtuales) seguía abierta con solo media respuesta —Ricardo contestó el 13-07, Luis no—, y M04 no se puede diseñar sin saber qué bodega es física, cuál virtual y a qué sucursal pertenece cada una.
- **Efecto medido:** los cuatro módulos del ciclo de la factura (M04, M05, M07, M08 = 35 de los 105 puntos del tracker) siguen en 0 % trece días después. Mientras tanto se construyó **M17 Servicio en terreno**, que no estaba en el plan.
- **Por qué se registra tarde:** el pivote se ejecutó y se mencionó en el cuerpo del documento, pero nunca se anotó en esta sección, que es donde la regla de arriba dice que van los cambios de plan. Se detectó el 2026-07-26 al cruzar la biblia contra la app. **El cambio de plan más importante desde el re-baseline vivió 13 días sin quedar registrado donde corresponde.**

### R-003 · 2026-07-26 — Entra M17 al alcance *(registro del hecho, no una decisión nueva)*
- **Qué:** **M17 Servicio en terreno** (técnico industrial: agenda, confirmación del cliente, instalaciones, conductores) se incorpora a la biblia como módulo 17, y al tracker §10 con peso 5. La base sube de 100 a 105.
- **Por qué:** ya estaba construido y en producción (14–24 de julio, ~27 rutas) pero no correspondía a ninguno de los 16 módulos: durante ~6 semanas la spec describía una app que no era la real. No es alcance nuevo — es alcance que se ejecutó sin pasar por la spec y ahora se sincera.
- **Regla que lo evita:** biblia §9 — *un módulo nuevo se agrega a la sección 4 ANTES de construirse*.

### R-004 · 2026-08-04 — Entra M18 Logística al alcance
- **Qué:** **M18 Logística** (flota de vehículos: ficha, documentos con vencimiento, semáforo y aviso automático) entra al tracker §10 con **peso 3 al 75 %**. La base sube de 105 a **108** y el global de ≈ 46 % a **≈ 47 %**.
- **Por qué:** lo pidió el dueño el 04-08 y se construyó el mismo día. No corresponde a ninguno de los 17 módulos de la biblia: la flota se administraba en la planilla `Vehiculos 2026.xlsx`, fuera de la app. **Es alcance nuevo, no alcance ejecutado a escondidas** — la diferencia con R-003 es que acá el registro entra el mismo día, no seis semanas después.
- **Peso 3 y no más:** es un CRUD de una entidad con avisos programados; comparado con M13 Devoluciones (peso 4) o M17 (peso 5) es más chico. Arrancó en 60 % y subió a **75 %** el mismo día, al cargarse los 17 vehículos de la flota en producción. El 25 % que falta es el **QA del dueño** y los bloques que quedaron fuera por decisión suya (kilometraje y mantenciones).
- **Qué NO cambia:** el ciclo de la factura sigue en 35 puntos y en ≈ 31 %. El módulo nuevo **no aporta al objetivo central** del proyecto; suma en la periferia. Vale decirlo para que el +1 % no se lea como avance del ciclo.
- **Regla que lo evita:** ninguna — esta vez la regla se cumplió. Se deja como precedente del camino correcto: módulo nuevo → fila en §10 + línea de fechas en `PlanProyecto::MODULOS` + esta R-00N, **en el mismo push**.
