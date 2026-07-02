# Registro de decisiones — DaliGo

> **Qué es esto:** toda decisión que afecta arquitectura, negocio o plan se registra aquí con un ID `D-xxx`.
> `docs/RUTA-MAESTRA.md` marca los pasos bloqueados con la etiqueta `[B:D-xxx]`; este archivo es la única fuente del detalle.
> Origen inicial: la tabla "Pendientes por confirmar antes de Sprint 0" de la biblia (`PROYECTO_DALIGO.md` §7).

---

## 1. Cómo registrar una decisión

Formato fijo (máx. ~15 líneas por decisión):

```markdown
### D-0NN · Título corto
- **Estado:** ABIERTA | TOMADA | DESCARTADA · **Decisor:** quién · **Fecha límite útil:** cuándo deja de ser gratis esperar
- **Contexto:** por qué hay que decidir (1–3 líneas, con referencia a biblia/HANDOFF)
- **Opciones:** (a) … (b) … — con recomendación marcada
- **Decisión:** — pendiente — | lo decidido, fecha y por quién
- **Consecuencias:** qué cambia según lo decidido
- **Bloquea:** pasos de RUTA-MAESTRA afectados (etiqueta [B:D-0NN])
- **Mientras tanto:** trabajo NO bloqueado que se puede seguir haciendo
```

Al **tomarse** una decisión: (1) completar la ficha, (2) `grep "\[B:D-0NN\]" docs/RUTA-MAESTRA.md` y quitar la marca de los pasos desbloqueados, (3) anotarla en la entrada de sesión de `docs/BITACORA-SESIONES.md`.

---

## 2. Índice y semáforo

| ID | Título | Estado | Decisor | Bloquea | Límite útil |
|---|---|---|---|---|---|
| D-001 | Nombre definitivo del sistema | 🔴 ABIERTA | Luis + Mauricio | Branding, plantillas M15, manuales F3 | antes de F3 (nov 2026) |
| D-002 | Matriz "qué rol ve qué" | 🔴 ABIERTA | Luis (+ Héctor/Gonzalo) | Vista cruzada M04, M16-v2 | antes de M16-v2 (~W33) |
| D-003 | Bodegas virtuales (~25): levantamiento | 🔴 ABIERTA · **la más urgente** | Luis + Ricardo | M04-F1 completo | **antes de E3 (~W13)** |
| D-004 | Boleta rápida: flujo tributario | 🔴 ABIERTA | Contabilidad (vía Luis) | M05-F3 | antes de W23 |
| D-005 | Huecos API Bsale (preguntas a Víctor) | 🔴 ABIERTA | Víctor (Bsale) | M05-F2 (DTE), webhooks, push kardex M11 | **antes de W18** |
| D-006 | CRM / zonas de vendedores: ¿entra? | 🔴 ABIERTA | Mauricio | Diseño hoja de ruta M08 | antes de E8 (~W27) |
| D-007 | WhatsApp Business API | 🔴 ABIERTA | Marco | Canal WhatsApp de M15/M12/M14 | no bloquea el motor (email primero) |
| D-008 | Impresora térmica (modelo) | 🔴 ABIERTA | Gonzalo + Mauricio | M09-mini (E12) | antes de ene 2027 |
| D-009 | WordPress: curso WooCommerce vs código propio | 🔴 ABIERTA | Mauricio + Luis | M10 (backlog post-9-meses) | sin urgencia |
| D-010 | Computadores nuevos para devs | 🟡 ABIERTA (probablemente ya no aplica) | Mauricio | nada | revisar y cerrar |
| D-000 | [retroactiva] Roles reconciliados a 8 ASCII | 🟢 TOMADA | equipo (2026-06) | — | — |

**Ritual:** revisar este semáforo cada viernes (ver `docs/RUTA-MAESTRA.md` §0). Objetivo H1': **todas cerradas al 31-jul-2026**.

---

## 3. Decisiones ABIERTAS

### D-001 · Nombre definitivo del sistema
- **Estado:** ABIERTA · **Decisor:** Luis + Mauricio · **Fecha límite útil:** antes de F3 (capacitación/manuales, nov 2026)
- **Contexto:** Luis pidió nombre propio para no confundir con Bsale ("escríbanlo diez mil veces"). Candidatos en biblia §7: APP DALI, Importadora DALI. Nombre de trabajo actual: **DaliGo**.
- **Opciones:** (a) DaliGo (ya es el nombre del repo y del trabajo diario — **recomendada**), (b) APP DALI, (c) Importadora DALI.
- **Decisión:** — pendiente —
- **Consecuencias:** el nombre vive solo en `config/app.php` + tabla `configuraciones` → renombrar cuesta 1 commit. Las plantillas de M15 y los manuales de F3 lo usan: decidir antes de escribirlos en masa.
- **Bloquea:** nada del código actual; `[B:D-001]` solo en pasos de F3 (manuales/capacitación).
- **Mientras tanto:** seguir como DaliGo.

**Brief listo para enviar (copy/paste a Luis/Mauricio):**
> Hola, necesitamos fijar el nombre definitivo del sistema (hoy lo llamamos DaliGo). Opciones: (1) DaliGo, (2) APP DALI, (3) Importadora DALI, u otro que prefieran. El nombre aparecerá en la pantalla de login, los correos automáticos y los manuales del personal. Si no hay respuesta antes de octubre, seguimos con "DaliGo" que ya usa todo el equipo. ¿Cuál eligen?

### D-002 · Matriz "qué rol ve qué" (reportes y vista cruzada)
- **Estado:** ABIERTA · **Decisor:** Luis (con Héctor y Gonzalo) · **Fecha límite útil:** antes de M16-v2 (~W33)
- **Contexto:** biblia §7: cada cargo debe ver solo lo suyo (el soplador no ve contabilidad). Falta que dirección valide la matriz módulo×rol. Los permisos técnicos ya existen (seeder con 8 roles / 14 permisos y creciendo).
- **Opciones:** (a) **enviar matriz pre-llenada con propuesta conservadora para que SOLO corrijan** (recomendada — no pedirles que la inventen), (b) taller presencial de definición.
- **Decisión:** — pendiente —
- **Consecuencias:** define la vista cruzada de stock (M04) y los tableros filtrados de M16-v2.
- **Bloquea:** `[B:D-002]` en M04 (solo la vista cruzada para roles operativos) y M16-v2.
- **Mientras tanto:** default conservador — operativos ven solo lo suyo; admin/jefes ven todo. M16-v0/v1 salen solo para admin.

**Brief listo para enviar:** preparar la planilla módulo×rol pre-llenada (se genera en E3 con los permisos reales del seeder) y enviarla con la instrucción: "marquen ✓ o ✗ donde no estén de acuerdo; lo no marcado queda como está".

### D-003 · Bodegas virtuales: levantamiento y destino ⚠️ LA MÁS URGENTE
- **Estado:** ABIERTA (catastro ✔ obtenido 2026-07-02; falta la respuesta de Luis/Ricardo) · **Decisor:** Luis + Ricardo (Víctor si hay dudas técnicas) · **Fecha límite útil:** antes de E3/M04-F1 (~fines de julio)
- **Contexto:** biblia §3 (corrección de Luis): limpiar las bodegas virtuales heredadas. **Catastro real verificado en producción** (evidencia `docs/qa/INFRA/2026-07-02--INFRA--duplicados-variantid-catastro-bodegas.md`): son **16 bodegas** (no ~25 como estimaba la biblia). Además resuelve el misterio "Santa Rosa": ES una bodega de Bsale.
- **Opciones por bodega:** (a) se mantiene (¿con qué propósito y de qué sucursal?), (b) se elimina/fusiona, (c) queda solo histórica.
- **Decisión:** — pendiente —
- **Consecuencias:** define la clasificación física/virtual/propósito y el mapping bodega↔sucursal de M04; mal resuelta, corrompe todo el inventario.
- **Bloquea:** `[B:D-003]` en P-M04-01 y siguientes.
- **Mientras tanto:** el espejo sigue sincronizando todo; la clasificación es aditiva (columnas locales nuevas).

**Brief listo para enviar a Luis/Ricardo (tabla pre-llenada — solo corrigen/completan):**

> Ricardo/Luis: esta es la lista REAL de las 16 bodegas que hoy existen en Bsale. Pre-llenamos lo que
> creemos; corrijan lo que esté mal y completen los «?». Las que se marquen "NO se usa" solo se dejarán
> de mostrar en el sistema nuevo (en Bsale no tocamos nada). Plazo ideal: 2 semanas.
>
> | Bodega (Bsale) | ¿Se usa? | Propósito (propuesta) | Sucursal |
> |---|---|---|---|
> | MIRADOR | ¿SÍ? | Física — bodega central | Mirador |
> | COQUIMBO | ¿SÍ? | Física | Coquimbo |
> | ABATE MOLINA | ¿SÍ? | Física | Abate Molina |
> | BUZETA | ¿SÍ? | Física — almacenaje | Buzeta |
> | BODEGA SANTA ROSA | ¿SÍ? | ¿Central de insumos (tapas/manillas)? | ¿Mirador? |
> | BODEGA SERVICIO TECNICO | ¿? | Virtual — taller. **¿Es duplicada de "SERVICIO TECNICO"? ¿cuál se usa?** | ¿Mirador? |
> | SERVICIO TECNICO | ¿? | Virtual — taller (ver fila anterior) | ¿? |
> | BODEGA MERMAS | ¿SÍ? | Virtual — mermas/dañados | transversal |
> | RESERVA SUCURSALES | ¿? | Virtual — reservas entre sucursales | transversal |
> | CONTENEDORES | ¿? | ¿Importaciones en tránsito (China)? | ¿Abate? |
> | CERTIFICACIONES | ¿? | ¿? | ¿? |
> | SERAFIN ZAMORA | ¿? | ¿Punto de venta/bodega antigua? | ¿? |
> | CONCEPCIÓN | ¿? | ¿Ex-sucursal / punto remoto? | ¿? |
> | VIÑA DEL MAR | ¿? | ¿Ex-sucursal / punto remoto? | ¿? |
> | ABATE PRUEBA | ¿NO? | Parece de PRUEBA — ¿eliminar del sistema nuevo? | — |
> | COQUIMBO PRUEBA | ¿NO? | Parece de PRUEBA — ¿eliminar del sistema nuevo? | — |

### D-004 · Boleta rápida: flujo tributario
- **Estado:** ABIERTA · **Decisor:** Contabilidad (Melissa/Scarleth) vía Luis · **Fecha límite útil:** antes de M05-F3 (~W23)
- **Contexto:** biblia §3/§7: venta "paga y se va" en <1 minuto sin datos del cliente. Hay que validar el tratamiento tributario antes de construirla.
- **Opciones:** (a) boleta electrónica a consumidor final (RUT `66666666-6`) vía Bsale — **probable respuesta correcta, confirmar**, (b) otro mecanismo que indique contabilidad.
- **Decisión:** — pendiente —
- **Consecuencias:** define M05-F3. Si no llega a tiempo, F3 se posterga sin arrastrar a F1/F2.
- **Bloquea:** `[B:D-004]` en P-M05 (solo sub-fase F3).
- **Mientras tanto:** M05-F1 (cotizaciones) y F2 (emisión normal) completos no la necesitan.

**Brief listo para enviar:**
> Consulta para contabilidad: para ventas rápidas de mesón (cliente paga y se va, sin dar datos), ¿basta con emitir boleta electrónica a consumidor final (66.666.666-6) por Bsale como hoy? ¿Hay tope de monto u otra restricción que debamos respetar en el sistema? Respuesta simple sí/no + condiciones nos sirve.

### D-005 · Huecos de la API Bsale (reunión/respuestas de Víctor)
- **Estado:** ABIERTA · **Decisor:** Víctor (Bsale) · **Fecha límite útil:** antes de M05-F2 (~W18)
- **Contexto:** las preguntas YA están redactadas en `docs/BSALE_API.md` §Huecos (endpoints de marcas, scopes del token, modified-since, webhooks, unicidad de code, plan contratado, uptime, OAuth). Falta despacharlas y sumar 2 nuevas: **acceso a sandbox para pruebas de escritura de DTE** y **confirmar bodegas virtuales de la cuenta real** (la DEMO tiene 0).
- **Decisión:** — pendiente —
- **Consecuencias:** habilita la emisión de documentos (M05-F2), define si los traspasos de M04-F2 escriben stock en Bsale o quedan locales, y si el kardex M11 se puede empujar.
- **Bloquea:** `[B:D-005]` en P-M05-F2, P-M04-F2 (parcial), push kardex M11.
- **Mientras tanto:** M05-F1, M13, diseño de M07 no dependen; el espejo read-only ya funciona.

**Brief listo para enviar:** copiar `docs/BSALE_API.md` §Huecos + añadir: "¿Nos habilitan una cuenta sandbox para probar emisión de documentos sin tocar producción? ¿La cuenta real de Dali tiene bodegas virtuales configuradas y cuántas?"

### D-006 · CRM / zonas de vendedores: ¿entra al alcance?
- **Estado:** ABIERTA · **Decisor:** Mauricio · **Fecha límite útil:** antes de E8/M08 (~W27)
- **Contexto:** biblia §7. La hoja de ruta de M08 se organiza por ZONA con vendedor asignado; hay que decidir si "zona" es un atributo simple o un CRM completo.
- **Opciones:** (a) **`zona` como atributo del cliente** (norte/oriente/costa/valles) — recomendada para MVP, (b) CRM completo con carteras e indicadores → post-9-meses.
- **Decisión:** — pendiente —
- **Consecuencias:** (a) permite hojas de ruta por zona sin proyecto nuevo; (b) desplaza fechas.
- **Bloquea:** `[B:D-006]` en P-M08 (diseño de hoja de ruta).
- **Mientras tanto:** modelar `zona` nullable en clientes desde E5 (barato y compatible con ambas).

### D-007 · WhatsApp Business API
- **Estado:** ABIERTA · **Decisor:** Marco · **Fecha límite útil:** no bloquea (el motor M15 sale con email + campanita)
- **Contexto:** biblia §7: confirmar migración a WhatsApp Business API. M15 se diseña con canal WhatsApp enchufable (stub hasta tener API).
- **Opciones:** (a) Meta Cloud API directa, (b) BSP (proveedor intermedio), (c) mientras tanto deep-links `wa.me` sin API (cero costo — es el puente actual para M12).
- **Decisión:** — pendiente —
- **Consecuencias:** define costo/plazo del canal WhatsApp real para M15/M14/M12.
- **Bloquea:** `[B:D-007]` solo en el paso "activar canal WhatsApp" de M15.
- **Mientras tanto:** email + campanita in-app (M15), links `wa.me` (M12).

**Brief listo para enviar:**
> Marco: ¿en qué estado está la migración a WhatsApp Business API? Necesitamos saber: (1) ¿ya hay número/cuenta aprobada?, (2) ¿proveedor: Meta directo u otro?, (3) ¿costo mensual estimado y plazo? Con eso activamos las notificaciones automáticas por WhatsApp del sistema nuevo. Mientras tanto usaremos correo + enlaces manuales de WhatsApp.

### D-008 · Impresora térmica nueva (etiquetas marketplace)
- **Estado:** ABIERTA · **Decisor:** Gonzalo + Mauricio · **Fecha límite útil:** antes de E12/M09-mini (ene 2027)
- **Contexto:** biblia §7: la actual se sobrecalienta con volumen ML. Necesaria para las etiquetas 10x15 de M09.
- **Opciones:** cotizar 2–3 modelos (Zebra / Xprinter u otros compatibles con etiquetas ML 10x15).
- **Decisión:** — pendiente —
- **Bloquea:** `[B:D-008]` en P-M09 (impresión de etiquetas).
- **Mientras tanto:** nada de M09 se construye antes de F4; el M09-mini (bandeja + boleta vinculada) no requiere la impresora.

### D-009 · WordPress: curso WooCommerce vs código propio (M10)
- **Estado:** ABIERTA · **Decisor:** Mauricio + Luis · **Fecha límite útil:** sin urgencia (M10 es backlog post-9-meses)
- **Contexto:** biblia §7: WordPress 2016 casi descontinuado; opción A curso WooCommerce (~$200 USD/7 días) vs B rehacer en código propio; migrar el servidor web separado del correo. **Posible opción C** detectada en el escaneo de Luis (`docs/CORRECCIONES-LUIS.md`): anotación "(Nuevo APP B[sale?])" junto a M10 — ¿la tienda/app web de Bsale como canal? Confirmar lectura con Luis (paso P-S0-06).
- **Decisión:** — pendiente —
- **Bloquea:** `[B:D-009]` en M10 completo (todo backlog).
- **Mientras tanto:** nada — M10 está fuera de los 9 meses.

### D-010 · Computadores nuevos para devs
- **Estado:** ABIERTA (probablemente ya no aplica) · **Decisor:** Mauricio · **Fecha límite útil:** revisar y cerrar
- **Contexto:** biblia §7 lo lista, pero el modelo de trabajo real es dueño + IAs con el equipamiento actual.
- **Recomendación:** cerrarla como DESCARTADA salvo que se incorporen devs humanos.
- **Bloquea:** nada.

---

## 4. Decisiones TOMADAS (cronológico inverso)

### D-000 · [retroactiva] Roles reconciliados a 8 identificadores ASCII
- **Estado:** TOMADA (2026-06) · **Decisor:** equipo
- **Contexto:** roles históricos con mayúsculas/acentos (`Soplador`, `Jefatura`) rompían consistencia.
- **Decisión:** 8 roles canónicos ASCII: `admin`, `member`, `vendedor`, `jefe_ventas`, `jefe_bodega`, `conductor`, `tecnico`, `soplador`. Detalle en `docs/AUDITORIA-M01-M02.md`.
- **Consecuencias:** el seeder es la fuente de verdad; todo rol nuevo se agrega ahí (idempotente).
