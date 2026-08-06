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
- **Decisión:** **opción (a) con matiz**: `zona` como atributo simple — la hoja de ruta se arma POR ZONA, pero el vendedor **NO es fijo por ruta** (Luis, R21: hay rutas con ventas de varios vendedores y otras de uno solo). Sin CRM. El catálogo `zonas` ya existe en producción desde DESPACHOS-v1; el diseño de la hoja va en `docs/planes/PLAN-DESPACHOS-V2.md` §2. | lo decidido, fecha y por quién
- **Consecuencias:** qué cambia según lo decidido
- **Bloquea:** pasos de RUTA-MAESTRA afectados (etiqueta [B:D-0NN])
- **Mientras tanto:** trabajo NO bloqueado que se puede seguir haciendo
```

Al **tomarse** una decisión: (1) completar la ficha, (2) `grep "\[B:D-0NN\]" docs/RUTA-MAESTRA.md` y quitar la marca de los pasos desbloqueados, (3) anotarla en la entrada de sesión de `docs/BITACORA-SESIONES.md`.

---

## 2. Índice y semáforo

| ID | Título | Estado | Decisor | Bloquea | Límite útil |
|---|---|---|---|---|---|
| D-001 | Nombre definitivo del sistema: **DaliGo** | 🟢 TOMADA | Mauricio (2026-07-08) | — | — |
| D-002 | Matriz "qué rol ve qué" | 🟢 TOMADA como ESTRATEGIA | Mauricio (2026-07-08) | — | — |
| D-003 | Bodegas virtuales (16): levantamiento | 🟢 **RESUELTA PARCIAL 06-ago** · Excel conjunto Ricardo+Luis anexado: **11/16 firmes** (5 viven, 6 mueren), 5 [B] con pregunta puntual | Luis + Ricardo | mapping bodega↔sucursal → **M04 se destraba** | las 5 [B] cierran con la ronda 2 de Luis |
| D-004 | Boleta rápida: flujo tributario | 🔴 ABIERTA | Contabilidad (vía Luis) | M05-F3 | antes de W23 |
| D-005 | Huecos API Bsale (docs + soporte oficial) | 🔴 ABIERTA | Soporte Bsale (correo en curso vía Director) | M05-F2 (DTE), webhooks, push kardex M11 | **antes de W18** |
| D-006 | CRM / zonas de vendedores: ¿entra? | 🟢 RESUELTA 03-ago | Mauricio | Diseño hoja de ruta M08 | cerrada (respuesta de Luis, ronda 1 despachos) |
| D-007 | WhatsApp Business API | 🟡 APLAZADA | Mauricio (2026-07-13) | nada (stub hasta nueva orden) | — |
| D-008 | Impresora térmica (modelo) | 🔴 ABIERTA | Gonzalo + Mauricio | M09-mini (E12) | antes de ene 2027 |
| D-009 | WordPress: curso WooCommerce vs código propio | ⚫ DESCARTADA | Mauricio (2026-07-08) | — | — |
| D-010 | Computadores nuevos para devs | 🟢 CERRADA (PCs obtenidos) | Mauricio (2026-07-08) | — | — |
| D-011 | URL oficial (`daligo.impdali.cl`) y estrategia de entornos | 🟢 TOMADA | Mauricio (2026-07-02) | — | — |
| D-012 | Visibilidad del repo GitHub: queda PÚBLICO | 🟢 TOMADA | Mauricio (2026-07-08) | — | — |
| D-014 | Qué ve el usuario cuando cae en un 403 o un 404 | 🟢 TOMADA | Mauricio (2026-07-24) | — | — |
| D-013 | Excepción de paleta: pasteles opt-in en squircles del Inicio | 🟢 TOMADA | Mauricio (2026-07-22) | — | — |
| D-015 | Código de incidente en la página de error 500 | 🟢 TOMADA | Mauricio (2026-07-24) | — | — |
| D-000 | [retroactiva] Roles reconciliados a 8 ASCII | 🟢 TOMADA | equipo (2026-06) | — | — |

> D-014 se agregó al semáforo el 2026-07-26: tenía su ficha completa en §4 desde el 24-07 pero faltaba en esta tabla, que es la que se revisa. Una decisión que no aparece acá, para el ritual del viernes no existe.

**Ritual:** revisar este semáforo cada viernes (ver `docs/RUTA-MAESTRA.md` §0). Objetivo H1': **todas cerradas al 31-jul-2026** — quedan **3 abiertas** (D-004, D-005, D-008); el objetivo del 31-jul no se cumplió, D-006 cerró el 03-ago y **D-003 quedó resuelta parcial el 06-ago** (Excel de Luis anexado; 5 filas [B] esperan su pregunta de la ronda 2).

---

## 3. Decisiones ABIERTAS

> **D-001 y D-002 pasaron a TOMADAS** — fichas en §4. **D-009 DESCARTADA y D-010 CERRADA** — fichas en §4.

### D-003 · Bodegas virtuales: levantamiento y destino
- **Estado:** 🟢 **RESUELTA PARCIAL (2026-08-06)** — Excel conjunto `Bodegas Bsale.xlsx` entregado por el dueño (auditoría de Ricardo 13-07 + revisión de Luis, quien esperaba la confirmación de un tercero para UNA bodega — cuál, por identificar). **11/16 filas firmes; las 5 restantes quedan [B] con su pregunta puntual en la ronda 2.** · **Decisor:** Luis + Ricardo · **Cierre total:** cuando vuelva la ronda 2.
- **Contexto:** biblia §3 (corrección de Luis): limpiar las bodegas virtuales heredadas. **Catastro real verificado en producción** (evidencia `docs/qa/INFRA/2026-07-02--INFRA--duplicados-variantid-catastro-bodegas.md`): son **16 bodegas** (no ~25 como estimaba la biblia). Además resuelve el misterio "Santa Rosa": ES una bodega de Bsale.
- **Opciones por bodega:** (a) se mantiene (¿con qué propósito y de qué sucursal?), (b) se elimina/fusiona, (c) queda solo histórica.
- **Decisión:** clasificación **ADITIVA sobre el espejo** (en Bsale no se toca nada; el sync sigue trayendo las 16): **5 viven** (MIRADOR central, COQUIMBO, ABATE MOLINA, BUZETA almacenaje masivo, MERMAS virtual transversal), **6 mueren** = dejan de mostrarse (CERTIFICACIONES —Luis: «CERRAR»—, SERAFIN ZAMORA, CONCEPCIÓN, VIÑA DEL MAR, ABATE PRUEBA, COQUIMBO PRUEBA), **5 pendientes [B]** (tabla anexa). El mapping bodega↔sucursal usa las 4 sucursales que la app ya tiene (`SucursalSeeder`: Mirador, Coquimbo, Abate Molina, Buzeta) + `sucursal_id` NULL para las transversales.
- **Consecuencias:** define la clasificación física/virtual/propósito y el mapping bodega↔sucursal de M04; mal resuelta, corrompe todo el inventario. **M04 queda destrabado** → `docs/planes/PLAN-M04.md`.
- **Bloquea:** ya nada estructural; cada fila [B] bloquea solo SU fila en PLAN-M04.
- **Mientras tanto:** el espejo sigue sincronizando todo; la clasificación es aditiva (columnas locales nuevas).

**Anexo 2 — respuesta CONJUNTA (Excel `Bodegas Bsale.xlsx`, 2026-08-06; columna Estado = veredicto de la auditoría):**

| Bodega | Veredicto | Sucursal | Nota / pregunta [B] |
|---|---|---|---|
| MIRADOR | ✅ VIVE | Mirador | física, central |
| COQUIMBO | ✅ VIVE | Coquimbo | física |
| ABATE MOLINA | ✅ VIVE | Abate Molina | física |
| BUZETA | ✅ VIVE | Buzeta | física, almacenaje masivo (baja rotación) |
| BODEGA MERMAS | ✅ VIVE | — (transversal) | virtual, mermas/dañados |
| BODEGA SANTA ROSA | ⏳ [B] | ¿Mirador? | confirmar propósito (¿central de insumos?) y si su stock es vendible |
| BODEGA SERVICIO TECNICO | ⏳ [B] | ¿Mirador? | Ricardo: «MAQUINARIA (Carlos Tablante)» — ¿cuál de las 2 ST queda? |
| SERVICIO TECNICO | ⏳ [B] | ¿? | Ricardo: «MÁQUINAS Y HERRAMIENTAS» — NO parecen duplicadas, confirmar |
| RESERVA SUCURSALES | ⏳ [B] | — (transversal) | ¿se mantiene? ¿cómo nace una reserva hoy? |
| CONTENEDORES | ⏳ [B] | ¿Abate? | Luis: «¿NECESARIO?» vs Ricardo: «ahí se ingresa la mercancía por 1ª vez» → si muere, ¿dónde entra la importación? |
| CERTIFICACIONES | ❌ MUERE | — | Luis: «CERRAR» |
| SERAFIN ZAMORA | ❌ MUERE | — | bodega cerrada |
| CONCEPCIÓN | ❌ MUERE | — | sucursal cerrada |
| VIÑA DEL MAR | ❌ MUERE | — | sucursal cerrada |
| ABATE PRUEBA | ❌ MUERE | — | de prueba |
| COQUIMBO PRUEBA | ❌ MUERE | — | de prueba |

> **Preguntas de cierre (viajan con la ronda 2 de despachos):** (1) ¿cuál de las 2 ST queda y de qué sucursal? (2) Santa Rosa: propósito y vendibilidad; (3) Contenedores: si se cierra, ¿dónde se ingresa la importación?; (4) Reserva Sucursales: ¿se mantiene y cómo opera?; (5) ¿cuál era LA bodega que esperaba confirmación de un tercero?

**Anexo 1 — respuesta de RICARDO (Excel del dueño, 2026-07-13; verbatim resumido). Superseded por el Anexo 2:**

| Bodega | ¿Se usa? | Aclaración de Ricardo |
|---|---|---|
| MIRADOR | SÍ | Física — central |
| COQUIMBO | SÍ | Física |
| ABATE MOLINA | SÍ | Física |
| BUZETA | SÍ | "ALMACENAMIENTO MASIVO (mercancía de baja rotación)" |
| BODEGA SANTA ROSA | PENDIENTE | (Ricardo no aclaró → pregunta a Luis) |
| BODEGA SERVICIO TECNICO | PENDIENTE | "SERVICIO TECNICO MAQUINARIA (Carlos Tablante)" |
| SERVICIO TECNICO | PENDIENTE | "SERVICIO TECNICO DE MAQUINAS Y HERRAMIENTAS" |
| BODEGA MERMAS | SÍ | Virtual — mermas/dañados |
| RESERVA SUCURSALES | PENDIENTE | (sin aclaración) |
| CONTENEDORES | PENDIENTE | "DONDE SE INGRESA POR PRIMERA VEZ LA MERCANCIA" (recepción de importación) |
| CERTIFICACIONES | NO | "SE ALMACENAN MAQUINAS MIENTRAS LAS CERTIFICAN" |
| SERAFIN ZAMORA | NO | "BODEGA CERRADA" |
| CONCEPCIÓN | NO | "SUCURSAL CERRADA" |
| VIÑA DEL MAR | NO | "SUCURSAL CERRADA" |
| ABATE PRUEBA | NO | (prueba — eliminar del sistema nuevo) |
| COQUIMBO PRUEBA | NO | (prueba — eliminar del sistema nuevo) |

> **Hallazgo del Director:** las 2 filas de servicio técnico **NO son duplicadas** — son DISTINTAS ("MAQUINARIA / Carlos Tablante" vs "MÁQUINAS Y HERRAMIENTAS"). **Falta de Luis:** propósito/sucursal de Santa Rosa y Reserva Sucursales, y confirmar el mapeo de las 2 de ST.

**Brief original (referencia — tabla pre-llenada que se envió a Luis/Ricardo):**

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
- **Decisión:** pendiente (contabilidad no ha confirmado). *Nota 06-ago: esta línea tenía pegado por error el texto de la decisión de D-006 — corregido.*
- **Consecuencias:** define M05-F3. Si no llega a tiempo, F3 se posterga sin arrastrar a F1/F2.
- **Bloquea:** `[B:D-004]` en P-M05 (solo sub-fase F3).
- **Mientras tanto:** M05-F1 (cotizaciones) y F2 (emisión normal) completos no la necesitan.

**Anexo — respuesta de MELISA (cajera, sala de ventas; foto del dueño 2026-07-13). Sigue ABIERTA (faltan Scarlett y Héctor):**
- Documento que entrega hoy: boleta o factura, generada por **Bsale web**.
- Medios de pago: efectivo, débito, crédito, transferencia, Webpay, cheques, depósitos; el más usado es **débito/crédito (tarjeta)**. Bsale registra medio de pago + N° de comprobante.
- Factura: **la hacen ellas** en Bsale (razón social, giro, dirección).
- Cierre de caja: **sí**, por Bsale (detalla los movimientos del día). Cuadran contra el informe de las máquinas **Getnet** para los pagos con tarjeta. **2 cajas**; cada una cuadra su efectivo contra el total de Bsale al cierre.
- Lo más molesto hoy: **NO el cobro** (fluye) sino (a) validar pagos por transferencia/Webpay manualmente, y (b) vender repuestos que están en OTRA sucursal (ej. servicio técnico) y depender de que los pasen a Mirador.

> **Lectura del Director para M05/M13:** el cobro NO es el dolor → el MVP de ventas debe atacar (1) conciliación de transferencia/Webpay y (2) el traspaso entre sucursales (cruza con M04). **Getnet** como fuente de cuadre de tarjeta es dato nuevo para el diseño de caja (M13).

**Brief original (referencia):**
> Consulta para contabilidad: para ventas rápidas de mesón (cliente paga y se va, sin dar datos), ¿basta con emitir boleta electrónica a consumidor final (66.666.666-6) por Bsale como hoy? ¿Hay tope de monto u otra restricción que debamos respetar en el sistema? Respuesta simple sí/no + condiciones nos sirve.

### D-005 · Huecos de la API Bsale (investigación en docs oficiales + soporte Bsale)
- **Estado:** ABIERTA · **Vía de resolución:** investigación en la documentación oficial de Bsale + **correo a soporte oficial Bsale (en curso vía Director)** · **Fecha límite útil:** antes de M05-F2 (~W18)
- **⚠️ Corrección de identidad (2026-07-08):** la ficha original asumía a "Víctor" como contacto de Bsale — **es incorrecto**. Víctor es el **sysadmin INTERNO de DALI** (sistemas/redes/cPanel/correos); **no existe contacto directo en Bsale**. Toda referencia a "Víctor (Bsale)" en docs quedó corregida a "soporte oficial Bsale".
- **Contexto:** las preguntas YA están redactadas en `docs/BSALE_API.md` §Huecos (endpoints de marcas, scopes del token, modified-since, webhooks, unicidad de code, plan contratado, uptime, OAuth). Sumar 2 nuevas: **acceso a sandbox para pruebas de escritura de DTE** y **confirmar bodegas virtuales de la cuenta real** (la DEMO tiene 0). **Pivote a DESPACHOS (2026-07-13):** subió la prioridad de la ruta A (docs oficiales, vía IA de Chrome) con una pregunta nueva clave: *¿el endpoint `documents` permite filtrar por fecha de emisión/modificación para sync incremental, y trae los ítems del documento?* — insumo directo del espejo de documentos de DESPACHOS-v1. Textos reutilizables (correo a ayuda@bsale.app + prompt Chrome): `docs/fleet/buzon/dictados/navegador.md`.
- **Decisión:** **opción (a) con matiz**: `zona` como atributo simple — la hoja de ruta se arma POR ZONA, pero el vendedor **NO es fijo por ruta** (Luis, R21: hay rutas con ventas de varios vendedores y otras de uno solo). Sin CRM. El catálogo `zonas` ya existe en producción desde DESPACHOS-v1; el diseño de la hoja va en `docs/planes/PLAN-DESPACHOS-V2.md` §2.
- **Consecuencias:** habilita la emisión de documentos (M05-F2), define si los traspasos de M04-F2 escriben stock en Bsale o quedan locales, y si el kardex M11 se puede empujar.
- **Bloquea:** `[B:D-005]` en P-M05-F2, P-M04-F2 (parcial), push kardex M11.
- **Mientras tanto:** M05-F1, M13, diseño de M07 no dependen; el espejo read-only ya funciona.

**Brief:** copiar `docs/BSALE_API.md` §Huecos + añadir: "¿Nos habilitan una cuenta sandbox para probar emisión de documentos sin tocar producción? ¿La cuenta real de Dali tiene bodegas virtuales configuradas y cuántas?" → destinatario: **soporte oficial Bsale** (ayuda@bsale.app / canal oficial), no un contacto interno.

### D-006 · CRM / zonas de vendedores: ¿entra al alcance?
- **Estado:** **RESUELTA (2026-08-03)** · **Decisor:** Mauricio · resuelta con la respuesta de Luis (ronda 1 de despachos, R21)
- **Contexto:** biblia §7. La hoja de ruta de M08 se organiza por ZONA con vendedor asignado; hay que decidir si "zona" es un atributo simple o un CRM completo.
- **Opciones:** (a) **`zona` como atributo del cliente** (norte/oriente/costa/valles) — recomendada para MVP, (b) CRM completo con carteras e indicadores → post-9-meses.
- **Decisión:** **opción (a) con matiz**: `zona` como atributo simple — la hoja de ruta se arma POR ZONA, pero el vendedor **NO es fijo por ruta** (Luis, R21: hay rutas con ventas de varios vendedores y otras de uno solo). Sin CRM. El catálogo `zonas` ya existe en producción desde DESPACHOS-v1; el diseño de la hoja va en `docs/planes/PLAN-DESPACHOS-V2.md` §2.
- **Consecuencias:** (a) permite hojas de ruta por zona sin proyecto nuevo; (b) desplaza fechas.
- **Bloquea:** `[B:D-006]` en P-M08 (diseño de hoja de ruta).
- **Mientras tanto:** modelar `zona` nullable en clientes desde E5 (barato y compatible con ambas).
- **Recomendación del Director (2026-07-13):** **catálogo simple `vendedor↔zona`** (sin CRM), con **suplencia temporal del jefe** cuando falte el titular. Esta definición **alimenta directamente DESPACHOS-v1** (el catálogo `zonas` de la entidad `despacho` nace de aquí).
- **Anexo — info de zonas de Héctor (charla 08-07):** zonas de Santiago = **Norte** y **Sur**. Norte incluye Los Andes y San Felipe; Sur incluye Melipilla. 6ª región = Rancagua hasta Teno. 7ª región = Curicó y Talca. **Un vendedor por zona; el jefe reemplaza en vacaciones.** Modelo técnico (decisión del Director): tabla `zonas` (nombre + comunas/regiones) + `zona_id` en el vendedor; la zona del cliente se deriva de su `vendedor_id` (ya existe de M03); suplencia = reasignación temporal. Confirmar con Luis: límites exactos, comisiones, y si la zona define la ruta de despacho.

### D-007 · WhatsApp Business API
- **Estado:** **APLAZADA (2026-07-13)** · **Decisor:** **Mauricio** · **Fecha límite útil:** — (no bloquea nada)
- **Aplazamiento (2026-07-13, Mauricio):** el canal WhatsApp queda en **stub hasta nueva orden** (M15 salió a producción con email + campanita; el stub `CanalWhatsApp` loguea). La ficha conserva la investigación del Investigador del 08-07 (opciones/costos, en poder de Mauricio) para cuando se retome.
- **⚠️ Corrección de decisor (2026-07-08):** la ficha decía "Marco" — era una confusión con **Marcos (2º dev, stream M12)**. El decisor real es **Mauricio**.
- **Contexto:** biblia §7: confirmar migración a WhatsApp Business API. M15 quedó construido con canal WhatsApp enchufable (`CanalWhatsApp` stub que loguea, LIVE desde 2026-07-07).
- **Opciones:** (a) Meta Cloud API directa, (b) BSP (proveedor intermedio), (c) mientras tanto deep-links `wa.me` sin API (cero costo — es el puente actual para M12).
- **Decisión:** **opción (a) con matiz**: `zona` como atributo simple — la hoja de ruta se arma POR ZONA, pero el vendedor **NO es fijo por ruta** (Luis, R21: hay rutas con ventas de varios vendedores y otras de uno solo). Sin CRM. El catálogo `zonas` ya existe en producción desde DESPACHOS-v1; el diseño de la hoja va en `docs/planes/PLAN-DESPACHOS-V2.md` §2.
- **Consecuencias:** define costo/plazo del canal WhatsApp real para M15/M14/M12.
- **Bloquea:** `[B:D-007]` solo en el paso "activar canal WhatsApp" de M15.
- **Mientras tanto:** email + campanita in-app (M15, ya en producción), links `wa.me` (M12).

### D-008 · Impresora térmica nueva (etiquetas marketplace)
- **Estado:** ABIERTA · **Decisor:** Gonzalo + Mauricio · **Fecha límite útil:** antes de E12/M09-mini (ene 2027)
- **Contexto:** biblia §7: la actual se sobrecalienta con volumen ML. Necesaria para las etiquetas 10x15 de M09.
- **Opciones:** cotizar 2–3 modelos (Zebra / Xprinter u otros compatibles con etiquetas ML 10x15).
- **Decisión:** **opción (a) con matiz**: `zona` como atributo simple — la hoja de ruta se arma POR ZONA, pero el vendedor **NO es fijo por ruta** (Luis, R21: hay rutas con ventas de varios vendedores y otras de uno solo). Sin CRM. El catálogo `zonas` ya existe en producción desde DESPACHOS-v1; el diseño de la hoja va en `docs/planes/PLAN-DESPACHOS-V2.md` §2.
- **Bloquea:** `[B:D-008]` en P-M09 (impresión de etiquetas).
- **Mientras tanto:** nada de M09 se construye antes de F4; el M09-mini (bandeja + boleta vinculada) no requiere la impresora.

---

## 4. Decisiones TOMADAS (cronológico inverso)

### D-015 · Código de incidente en la página de error 500
- **Estado:** TOMADA (2026-07-24) · **Decisor:** Mauricio
- **Contexto:** cerrando la familia de páginas de error (500/429/503/comodines), un 500 le decía al usuario «algo falló» y nada más; cuando llamaba a TI había que adivinar la hora exacta para encontrar la excepción en el log.
- **Decisión:** la página del 500 muestra un **código de 6 caracteres** («A3F9KC») que el usuario dicta a soporte, y el **mismo** código viaja en la línea de log de esa excepción. Detalles no obvios:
  1. **Alfabeto sin `0/O`, `1/I/L` ni `U`** — se dicta por teléfono, y esos son los caracteres que se confunden al hablar.
  2. Se genera en el callback de **`$exceptions->context()`**, no en `report()`: los `contextCallbacks` corren dentro de `buildExceptionContext()`, o sea en el array de contexto de **esa misma línea de log**. Un `report()` obligaría a ensuciar el logger global con `Log::withContext()` (además de ser inasertable con `Log::spy()`).
  3. Vive en `Context::rememberHidden` (binding `scoped`), **no** en un `static`: así muere con la instancia de la app y no se filtra entre peticiones ni entre tests. *Hidden* porque el contexto visible se inyecta en el `extra` de **todas** las líneas de log de la petición, y aquí solo interesa la de la excepción.
  4. **Un `abort(500)` explícito NO muestra código**: los `HttpException` están en `$internalDontReport`, no se reportan y por lo tanto no hay línea de log que buscar. La vista degrada sola — mostrar un código mandaría a TI a buscar algo inexistente.
- **Alcance y límites (gate R-31):** el callback de `context()` es **global**: corre para TODA excepción reportada —incluidos comandos y jobs de cola y peticiones JSON, donde ninguna página muestra el código—, así que desde ahora **toda línea de log de excepción lleva la clave `incidente`** (útil al grepear logs). Va envuelto en `try/catch` porque `Handler::exceptionContext()` **no** protege los contextCallbacks: si lanzara, la excepción escaparía del kernel y el usuario no vería ni la página ni quedaría el log del error original. Límite aceptado: `Context::dehydrate()` incluye el contexto oculto y la cola deshidrata en cada `push`, así que una petición que reportó una excepción y luego despacha un job estampa ese código en el payload; si el job falla, su log reusa el mismo código. Es ambigüedad diagnóstica (no seguridad ni corrección) y no se corrige para no perder el ciclo de vida `scoped` que nos da el reset automático.
- **Consecuencias:** `App\Support\CodigoIncidente` es la fuente única (incluido el patrón, que los tests reusan en vez de duplicarlo); el candado central de `ErroresServidorTest` es que el código **mostrado** sea el **logueado**. Si algún día se quiere el código también para los 4xx, habría que `stopIgnoring(HttpException::class)`, lo que empezaría a loguear cada 404 — no se hace.
- **Bloquea:** nada.

### D-014 · Qué ve el usuario cuando cae en un 403 o un 404
- **Estado:** TOMADA (2026-07-24) · **Decisor:** Mauricio
- **Contexto:** un 403/404 mostraba una pantalla sin logo, sin menú y sin salida —la vista *minimal* del vendor de Laravel; la genérica de Symfony sale para los status que el vendor no trae— y, en el caso de spatie, **en inglés** (`User does not have the right permissions.`). El dueño pidió una mini-notificación («no tienes permiso… habla con un administrador») o que lo lleve al Inicio.
- **Decisión:** al usuario que **NAVEGA** (GET autenticado) se lo lleva al **Inicio con una mini-notificación** (canal de sesión propio `aviso`, renderizado una vez en el layout); todo lo demás conserva su status HTTP y cae en `errors/{403,404}.blade.php` con la marca DaliGo. Tres sub-decisiones no obvias:
  1. **Permiso y propiedad comparten el mismo texto genérico.** Decirle «ese reporte es de otro soplador» le confirma que el recurso existe (enumeración) y el remedio para él es idéntico. Se distinguen **solo en el log** (`motivo`: permiso|propiedad|estado|firma|no-encontrado).
  2. **Una ACCIÓN (POST/PUT/DELETE) rechazada por permiso se queda en 403**, no redirige: es un botón que no debió existir (bug de UI, no navegación del usuario), y así el contrato HTTP de las acciones no cambia.
  3. **El mensaje propio de `abort(403, '…')` se PRESERVA** (p. ej. «Este reporte ya no se puede editar.»): es copy de negocio para esa situación exacta; pisarlo con «habla con un administrador» sería mentir, porque el administrador no reabre un reporte cerrado. Corolario: el 2º argumento de `abort(403, …)` es **texto de cara al usuario** — jamás datos de otro usuario.
- **Consecuencias:** `session('aviso')` queda reservada al handler (`bootstrap/app.php`) y es la prueba de denegación en los tests (~56 asserts migrados de `assertForbidden` a `assertRedirect` + `assertSessionHas`); las peticiones JSON/fetch **conservan** su 403 porque `offline-queue.js` clasifica ese status como rechazo permanente; frontera aceptada: una URI que no matchea ninguna ruta no puede redirigir (no hay sesión) y muestra la página 404 con marca.
- **Bloquea:** nada.

### D-013 · Excepción de paleta: colores pastel OPT-IN en los squircles de los accesos del Inicio
- **Estado:** TOMADA (2026-07-22) · **Decisor:** Mauricio
- **Contexto:** el jefe pidió cards de módulo estilo Bsale en el Inicio (ícono + palabra, cuadradito de color suave); la paleta estricta de 4 de `CLAUDE.md` prohíbe verde/ámbar/azul.
- **Opciones:** (a) multicolor curado por defecto (look Bsale out-of-the-box); (b) **default sobrio** brand/neutral + paleta pastel curada de 8 que cada usuario elige POR CARD, persistida POR PERFIL (recomendada — no se pierde la sobriedad y quien quiera color lo activa).
- **Decisión:** (b), 2026-07-22, Mauricio. Los pasteles (celeste/verde/ámbar/violeta/turquesa/índigo, tono 100 de fondo + 700 de ícono) existen **SOLO** en el squircle del ícono de las cards del zócalo del dashboard y **SOLO** si el usuario los elige (`users.dashboard_colores`). El rojo sigue reservado a destructivo (fuera de la paleta elegible).
- **Consecuencias:** regla de paleta de `CLAUDE.md` enmendada con la excepción acotada; el mapa key→clases vive únicamente en `dashboard.blade.php` (`$paleta`, anti-purge); keys en `App\Support\AccesosDashboard`.
- **Bloquea:** nada.

### D-001 · Nombre definitivo del sistema: **DaliGo**
- **Estado:** TOMADA (2026-07-08) · **Decisor:** Mauricio
- **Contexto:** Luis pidió nombre propio para no confundir con Bsale. Candidatos de la biblia §7: APP DALI, Importadora DALI; nombre de trabajo: DaliGo.
- **Decisión:** el nombre definitivo es **DaliGo** — el que ya usa el repo, el equipo y la app. **Sin cambios de código** (ya se llama así en `config/app.php`/`configuraciones`).
- **Consecuencias:** desbloquea plantillas M15 y manuales/capacitación de F3 (marca `[B:D-001]` retirada de P-F3-04 en RUTA-MAESTRA).
- **Bloquea:** nada.

### D-002 · Matriz "qué rol ve qué" — TOMADA como ESTRATEGIA
- **Estado:** TOMADA como ESTRATEGIA (2026-07-08) · **Decisor:** Mauricio
- **Contexto:** biblia §7 pedía validar una matriz módulo×rol por adelantado con dirección. En la práctica los permisos se afinan mejor con el módulo funcionando delante.
- **Decisión:** **los accesos por rol se definen al CERRAR cada módulo, no por adelantado.** Al cierre de cada módulo, el paquete incluye su definición de accesos (con el seeder idempotente como fuente técnica). La matriz módulo×rol elaborada por el Investigador se archiva dentro de esta ficha como **propuesta de referencia para cada cierre** (anexo abajo).
- **Consecuencias:** la marca `[B:D-002]` deja de ser un bloqueo por-decisión-externa: P-M04-03 (vista cruzada) definirá sus accesos al cierre de M04. Default mientras tanto: conservador (operativos ven solo lo suyo; admin/jefes todo).
- **Bloquea:** nada como decisión; cada cierre de módulo hereda la tarea.
- **Anexo — matriz módulo×rol de referencia (Investigador, 08-07; verificada por el Director):** propuesta NO vinculante, a consultar al cerrar cada módulo. Leyenda: — sin acceso · Ver · Gestionar · Aprobar. Celdas con `*` = suposición por confirmar con Luis.

  | Módulo | admin | member | vendedor | jefe_ventas | jefe_bodega | conductor | tecnico | soplador |
  |---|---|---|---|---|---|---|---|---|
  | M01 usuarios/roles/config | Gestionar | — | — | Ver | Ver | — | — | — |
  | M02 catálogo y precios | Gestionar | — | Ver* | Ver* | Ver* | — | — | — |
  | M03 clientes | Gestionar | — | Gestionar | Gestionar | — | — | Ver* | — |
  | M04 inventario/bodegas | Gestionar | — | Ver* | Ver* | Gestionar* | — | — | — |
  | M05 ventas/boleta rápida | Gestionar | — | Gestionar* | Gestionar* | Ver* | — | — | — |
  | M06 compras | Gestionar | — | — | Ver* | Gestionar* | — | — | — |
  | M07 despachos/rutas | Gestionar | — | Ver* | Ver* | Gestionar* | Ver* | — | — |
  | M08 app conductor | Gestionar | — | — | — | Ver* | Gestionar* | — | — |
  | M09 impresión térmica | Gestionar | — | Ver* | Ver* | Ver* | — | Ver* | — |
  | M10 e-commerce | Gestionar | — | Ver* | Gestionar* | — | — | — | — |
  | M11 producción | Gestionar | — | — | Ver* | Gestionar | — | — | Gestionar |
  | M12 servicio técnico | Aprobar | — | Ver | Ver | Aprobar | — | Aprobar | — |
  | M13 caja/tesorería | Gestionar | — | — | Gestionar* | — | — | — | — |
  | M14 aprobaciones | Aprobar | Gestionar* | Gestionar* | Aprobar | Aprobar | Gestionar* | Gestionar* | Gestionar* |
  | M15 notificaciones | Gestionar | Ver* | Ver* | Ver* | Ver* | Ver* | Ver* | Ver* |
  | M16 dashboard/reportes | Gestionar | — | Ver* | Ver* | Ver* | — | — | — |

  Preguntas abiertas para Luis (al cerrar cada módulo): (1) M13 ¿el vendedor registra pagos de su boleta? (2) M14 ¿todos crean solicitudes o solo jefaturas/vendedor/técnico? (3) M07/M08 alcance exacto del conductor.

### D-009 · WordPress: curso WooCommerce vs código propio (M10) — DESCARTADA
- **Estado:** DESCARTADA (2026-07-08) · **Decisor:** Mauricio
- **Contexto:** biblia §7: WordPress 2016 casi descontinuado; opción A curso WooCommerce vs B código propio; posible opción C "(Nuevo APP B[sale?])" del escaneo de Luis.
- **Decisión:** descartada del alcance. M10 era backlog post-9-meses; no se invertirá en esta línea. Si el tema web renace, será una decisión nueva con contexto fresco.
- **Consecuencias:** M10 sale del backlog activo; la marca `[B:D-009]` queda sin efecto (el módulo entero está descartado de los 9 meses).

### D-010 · Computadores nuevos para devs — CERRADA
- **Estado:** CERRADA (2026-07-08) · **Decisor:** Mauricio
- **Decisión:** los equipos **ya se obtuvieron** — la necesidad quedó resuelta por la vía de los hechos.
- **Bloquea:** nada (nunca bloqueó pasos).

### D-011 · URL oficial del sistema y estrategia de entornos
- **Estado:** TOMADA (2026-07-02) · **Decisor:** Mauricio
- **Contexto:** hoy existe UNA sola instancia (`staging.impdali.cl`, BD `impdali_daligo`); la biblia mencionaba `daliprueba.cl` y el HANDOFF pedía reconciliar. Al testear producción surgió la duda de si los datos de prueba quedan para siempre y si algo se escribe en Bsale.
- **Decisión:** la app oficial vivirá en **`daligo.impdali.cl`**; `staging.impdali.cl` queda como ambiente de pruebas. La **separación real** de BD/entorno se ejecuta en F3 (paso P-F3-06), antes de usuarios reales. Mientras tanto: (a) Bsale es solo-lectura por construcción (`BsaleClient` no tiene métodos de escritura), así que probar flujos jamás toca Bsale; (b) los datos de prueba del módulo Producción se resetean on-demand con **`php artisan produccion:limpiar-pruebas`** (borra asignaciones/reportes/tandas/kardex local + sus audits; el catálogo no se toca).
- **Consecuencias:** P-F3-06 deja de ser "evaluar" y pasa a ser "ejecutar la separación con daligo.impdali.cl como prod"; el go-live parte con BD limpia (comando de limpieza + migración de datos P-F3-03).
- **Bloquea:** nada hoy.

### D-012 · Visibilidad del repositorio GitHub: queda PÚBLICO
- **Estado:** TOMADA (2026-07-08) · **Decisor:** Mauricio
- **Contexto:** GitGuardian alertó "secreto expuesto" en el repo (público) → incidencia **I-04** con gate de pushes. El barrido de la historia completa (233 commits, 14 ramas, 6 familias de patrones + verificación adversarial) dio **0 credenciales reales**; el detalle de la alerta confirmó **falso positivo** (placeholder «PEGAR» junto a `MAIL_USERNAME` en un doc de delegación). Las rotaciones de claves se hicieron de todos modos y el `git pull` del deploy quedó autenticado con deploy key SSH read-only (expediente: `docs/qa/INFRA/2026-07-08--INFRA--i04-gitguardian-barrido-deploykey.md`).
- **Decisión:** el repositorio **permanece PÚBLICO** por política del dueño. Las credenciales viven SOLO en secrets de GitHub Actions y en el servidor; los documentos usan placeholders («PEGAR…», `[CLAVE OCULTA]`, «REDACTADO») sin excepción.
- **Consecuencias / riesgo residual (aceptado):** (1) todo lo commiteado es visible públicamente y **para siempre** (clones/cachés de terceros persisten aunque se privatice después) → la disciplina de redacción de `docs/qa/README.md` es obligatoria y permanente; (2) los **logs de GitHub Actions son públicos** → ningún workflow debe imprimir valores sensibles; (3) la arquitectura/infra descrita en docs (rutas, crons, stack) es pública — aceptado; (4) la deploy key SSH del server queda como autenticación permanente del pull, inmune a un cambio futuro de visibilidad; (5) conviene repetir el barrido de secretos antes de hitos mayores (go-live F3).
- **Bloquea:** nada.

### D-000 · [retroactiva] Roles reconciliados a 8 identificadores ASCII
- **Estado:** TOMADA (2026-06) · **Decisor:** equipo
- **Contexto:** roles históricos con mayúsculas/acentos (`Soplador`, `Jefatura`) rompían consistencia.
- **Decisión:** 8 roles canónicos ASCII: `admin`, `member`, `vendedor`, `jefe_ventas`, `jefe_bodega`, `conductor`, `tecnico`, `soplador`. Detalle en `docs/AUDITORIA-M01-M02.md`.
- **Consecuencias:** el seeder es la fuente de verdad; todo rol nuevo se agrega ahí (idempotente).
