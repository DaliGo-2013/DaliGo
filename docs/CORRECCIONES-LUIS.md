# Correcciones de Luis — transcripción del documento escaneado

> Fuente: `contexto dali/Correcciones luis.pdf` (13 páginas escaneadas, reunión 11-12 mayo 2026).
> Transcrito el 2026-07-01. Estado: transcripción fiel; [ilegible] marca texto no recuperable.
> Propósito: verificar que la biblia (PROYECTO_DALIGO.md §3) capturó TODAS las correcciones.

El documento es la **especificación modular impresa** ("Especificación modular · DALI Cargos-Transporte", 16 módulos) con **anotaciones manuscritas de Luis Lazcano** en lápiz pasta azul: checks, aspas, tachaduras, círculos y notas al margen. Abajo se transcribe el texto impreso completo por página, con las anotaciones intercaladas entre corchetes `[manuscrito: …]`.

---

## Cotejo contra la biblia

| # | Anotación detectada en el escaneo (pág.) | ¿Está en la biblia? | Nota |
|---|---|---|---|
| 1 | Flecha a "Modelo de usuarios con roles" + "Bsale / Víctor (VB)" (p.1, M01) | **SÍ** §3.1, §4 M01, §7 | Validar con Víctor qué se reutiliza de Bsale. |
| 2 | "(APP a Futuro" junto a Bloque A / M01 (p.1) | **PARCIAL** §7 (Identidad: "APP DALI / Importadora DALI") | Anotación ambigua; podría referirse al nombre/app propia o a una app móvil futura. |
| 3 | "peso y dimensiones" encerrado + recuadro "14 m³" + "(1.0)" (p.2, M02) | **PARCIAL** §4 M02, §6 | Peso/dimensiones para cotizar despacho: SÍ. La cifra "14 m³" no aparece en la biblia. |
| 4 | Serie numérica manuscrita "10 10 / 10 20 00Z (encerrado) / 10 30 / 10 40 030 / 10 50 / 80 10 001 / 80 20 …" (p.2) | **NO** | Críptica; posible esquema de códigos (SKU/folios/cuentas). Anotación adyacente [ilegible]. |
| 5 | Checks en bullets de M02 (listas por canal, reglas, CSV, versionado) (p.2) | **SÍ** §4 M02 | La biblia además agrega "botón de filtro previo a exportar" (salió de la reunión, no del marginalia). |
| 6 | "(Problema entre VENDEDORES no Sucursales)" junto a M04 (p.3) | **SÍ** §3.2, §4 M04 | Gestión por vendedor, no por sucursal. |
| 7 | "Providencia" tachada en bodegas físicas (p.3) | **SÍ** §3.4 | Providencia eliminada. |
| 8 | "(MEJORAR)" bajo bodegas virtuales (p.3) | **SÍ** §3.3, §7 | Replantear las ~25 bodegas virtuales. |
| 9 | "¿? → Perfiles" en vista cruzada de stock (p.3) | **SÍ** §3.5, §7 | Vista cruzada filtrada por perfil; matriz pendiente. |
| 10 | Subrayados "stock bajo mínimo" y "productos sin movimiento" (p.3) | **SÍ** §4 M04 | La biblia precisa "10 días → alerta al vendedor que lo compró" (de la reunión). |
| 11 | "¿Qué [ilegible] Bsale?" (p.4) + "ya está" ×2 + tachado de "Envío automático por correo…" (p.4, M05) | **SÍ** §3.1, §3.18, §4 M05 | Todo marcado como "ya en Bsale" en la biblia. |
| 12 | Garabato sobre "M06 · POS sala de venta" (p.4) + aspas en sus bullets (p.5) | **SÍ** §3.6, §4 M06 | M06 en STANDBY: "lo que funciona no se toca". |
| 13 | "(✓)" en "Verificación automática de transferencia bancaria" y "(✓)" en "Integración POS y Webpay" (p.5, M06) | **PARCIAL** | Probable "ya existe / conforme". Sin impacto mientras M06 esté en standby; la biblia no registra el matiz. |
| 14 | "(Cable)" junto a "Impresión a impresora térmica" (p.5, M06) | **PARCIAL** §8 A-01 | Lectura probable; coincide con "internet cableado en puntos críticos" del levantamiento. |
| 15 | Recuadro "QR", "(Folio / Picking)" con flecha a M07 y "PC +" (p.5) | **SÍ** §3.17, §4 M07 | Folio de picking + PC físico con impresora + prioridad MVP del QR antifraude. |
| 16 | "Hermoso" junto al escalamiento automático de M14 (p.6) | **SÍ** §4 M14 | Registrado literal: VALIDADO (Luis: "hermoso"). |
| 17 | "3era Región" junto a "camión Atacama" y "Atacama" tachado con "3era" encima (p.7, M08) | **SÍ** §4 M08 | "Camión a Atacama, 3ª región; valles = 7ª región para Quintana". |
| 18 | Paréntesis sobre "Plan B automático… en la zona" + "– Zona" (p.7, M08) | **SÍ** §3.11, §4 M08 | Rutas por zona con vendedor asignado; Plan B con cartera de la zona. |
| 19 | Letra "A" junto a M09 (p.7) y "A →" junto a M10 en Fase 3 (p.12) | **SÍ/PARCIAL** §4 M09, §4 M10 | Consistente con "Fase Abate" (M09) y "OPCIÓN A" (M10); el sentido exacto de la "A" es ambiguo. |
| 20 | "Cotizar →" sobre "etiquetas con impresora térmica dedicada" + tachadura (p.7, M09) | **SÍ** §4 M09, §7 | "Cotizar impresora térmica nueva (la actual se sobrecalienta)". |
| 21 | "(Nuevo APP B[sale?])" junto al título de M10 (p.8) | **NO** | Lectura dudosa. Si dice "Bsale", sugeriría una 3ª opción (tienda/app de Bsale) que la biblia no contempla. |
| 22 | "$ / tiempo" en margen de M10 (p.8) | **SÍ** §7 | "Costo vs tiempo" (hardware) y decisión A/B de M10 pendiente. |
| 23 | "Curso que 200.000 / 7 días → 1/2 sem / SW 3/4 semanas" (p.8) | **SÍ** §4 M10, §7 | Curso WooCommerce ~$200 USD / 7 días vs código propio. |
| 24 | Aspa sobre "constructivo" + "otra área" (p.9, M11) | **SÍ** §3.7 | Modo constructivo sale de M11 (operación de otra área). |
| 25 | "QR" junto al formulario de pre-ingreso (p.9, M12) | **SÍ** §4 M12 | Pre-ingreso con QR. |
| 26 | Flecha desde "Historial técnico por equipo" → "Solicitud de repuestos en base a la info" (p.9, M12) | **SÍ** §3.10, §4 M12 | Sugerencia de compra de repuestos según histórico del técnico. |
| 27 | "?" en "envío directo del cliente a Mirador, saltando Abate Molina" (p.9, M12) | **SÍ** §4 M12 | "Validar con Gonzalo el envío directo". |
| 28 | "QR" sobre el formulario de devolución del cliente (p.10, M13) | **PARCIAL** §4 M13 | Formulario que llena el cliente: SÍ. El detalle "con QR" no está explícito en M13. |
| 29 | "Importadora Dali" / "impo[rtadora]dali.cl" junto a M15 (p.10) | **SÍ** §7, §4 M10 | Identidad (Importadora DALI) y los 3 dominios separados. |
| 30 | Círculo en "IMAP de" y en "Triggers" + checks en M15 (p.10) | **SÍ** §4 M15 | SMTP + IMAP, triggers, reintentos. |
| 31 | Flecha tras "Opt-out por canal…" + "[ilegible] o [ilegible]" (p.11, M15) | **SÍ (probable)** §4 M15 | La biblia ya recoge "el cliente elige canal al registrarse; opt-out por canal". |
| 32 | Bullet manuscrito agregado a M16: "Pedido de ST de repuesto[s]" (p.11) | **PARCIAL** §3.10, §4 M12 | La función existe en M12; como reporte listado de M16 no aparece. |
| 33 | "Coquimbo" tachado en "MVP Coquimbo" → "General" + "Fase 1" (p.12) | **SÍ** §3.16 | Transversales una sola vez, desde el día 1, para las 3 sucursales; §4 reestructurado en ese sentido. |
| 34 | Subrayado/tachado del ítem "M10 … (puede entrar antes…)" en Fase 3 + "A →" (p.12) | **SÍ** §4 M10 | M10 con decisión pendiente y backlog post-9-meses; opción A marcada. |
| 35 | Aspa junto a la regla "no congelar el alcance de un módulo más allá de su Sprint 0" (p.13) | **PARCIAL** | El espíritu (revisión iterativa, piloto genera aprendizaje) está en la biblia, pero la regla textual no se cita. |

**Nota de dirección inversa:** la biblia contiene correcciones que NO están en el escaneo (n.º 8 soplador con celular, 9 aprobación del jefe de bodega, 13 boleta rápida, 14 Héctor de 5 pasos a 1–2, 15 umbral parametrizable): provienen de lo conversado en la reunión, no del marginalia. Es coherente, no es discrepancia.

## Discrepancias y novedades

Lo que el escaneo aporta y la biblia no tiene (todo menor/ambiguo; ninguna contradicción):

1. **"14 m³" (p.2, M02):** cifra en recuadro junto a "peso y dimensiones para cotizar despacho". Probable volumen de referencia (¿capacidad del camión?). No está en la biblia — confirmar con Luis.
2. **Serie numérica (p.2):** "10 10 / 10 20 00Z (encerrada) / 10 30 / 10 40 030 / 10 50 / 80 10 001 / 80 20 …" con palabra adyacente [ilegible]. Parece un esquema de codificación (SKU, folios o cuentas). No recogida.
3. **"(Nuevo APP B[sale?])" (p.8, M10):** lectura dudosa. Si dice "Bsale", implicaría evaluar la tienda/app nueva de Bsale como tercera opción del canal web (la biblia solo contempla Opción A WordPress y Opción B código propio). Confirmar antes de decidir M10.
4. **Bullet manuscrito en M16 (p.11): "Pedido de ST de repuesto[s]".** La funcionalidad está en M12 (§3.10), pero Luis lo pidió también como **reporte de M16**; agregar "reporte de pedidos/consumo de repuestos de ST" a la lista de M16 si se confirma.
5. **Marcas "(✓)" en M06 (p.5)** sobre "verificación automática de transferencia bancaria" e "integración POS y Webpay": probable "esto sí/ya existe". Sin efecto mientras M06 siga en standby, pero conviene registrar el matiz si M06 se reactiva.
6. **Anotaciones ilegibles sin cotejo posible:** junto a "Bloque D · Distribución" (p.6, dos palabras), bajo el ítem 8 del MVP (p.12, posible "Falabella"), y la palabra junto a la serie numérica (p.2).

**Veredicto:** la biblia cubre todo el contenido sustantivo del escaneo; las 18 correcciones de §3 están respaldadas por las anotaciones. Solo quedan los 6 puntos menores/ambiguos de arriba, ninguno contradice la biblia.

---

## Transcripción por página

Convenciones: texto impreso tal cual; `[manuscrito: …]` = anotación a lápiz pasta de Luis; `[✓]` = check manuscrito; `[✗]` = aspa manuscrita; `~~texto~~` = tachadura; `[ilegible]` = no recuperable.

### Página 1

**Especificación modular · DALI Cargos-Transporte**

Documento basado en los levantamientos de procesos de las sucursales Abate Molina, Coquimbo y Mirador (29 procesos mapeados). Define 16 módulos del sistema, su alcance funcional, las dependencias entre ellos, y una secuencia de construcción para abordar el proyecto paso a paso con un equipo de 2 personas.

**Cómo leer este documento**

Cada módulo tiene:

- **Qué hace** — alcance en una oración.
- **Funcionalidades clave** — lo que entra dentro de este módulo.
- **Procesos que resuelve** — referencia a los 29 procesos levantados, con código: A = Abate Molina, C = Coquimbo, M = Mirador, seguido del número de proceso.
- **Dependencias** — qué módulos deben existir antes de construir este.
- **Esfuerzo** — S (1-3 semanas), M (3-6 semanas), L (6-10 semanas), XL (10+ semanas), asumiendo equipo de 2 desarrolladores full-time.
- **Fase sugerida** — MVP, Fase 2, Fase 3 según el roadmap propuesto.

Los módulos están agrupados en 7 bloques temáticos.

---

**Bloque A · Fundamentos**

**M01 · Core / Plataforma base** [manuscrito al margen derecho, con paréntesis de apertura: "APP a Futuro"]

Qué hace: Provee la infraestructura compartida del sistema: autenticación, autorización por roles, multi-sucursal, auditoría y configuración global.

Funcionalidades clave:

- Autenticación (login, recuperación de contraseña, sesión) [trazo manuscrito curvo sobre el final de la línea]
- Modelo de usuarios con roles configurables por sucursal [manuscrito: flecha larga ← apuntando a este bullet desde la nota "Bsale / Víctor (VB)" — lectura probable; escrito "Bsals / VictoR (VB)"]
- Estructura organizacional: sucursales (Mirador, Coquimbo, Abate Molina, Providencia, Buzeta), áreas, equipos [✓]
- Permisos granulares por módulo y por acción [✓]
- Log de auditoría (quién hizo qué, cuándo, dónde) [✓]
- Configuración global del negocio (parámetros, % de distribución por sucursal, umbrales)
- API gateway interno para los otros módulos

### Página 2

Procesos que resuelve: Habilitador transversal. Sin este módulo nada más funciona.

Dependencias: Ninguna.

Esfuerzo: M (3-4 semanas).

Fase: MVP.

---

**M02 · Catálogo de productos + listas de precios**

Qué hace: Mantiene el catálogo maestro de productos con sus atributos físicos y precios diferenciados por canal de venta. [manuscrito, margen derecho, en recuadro: "14 m³"]

Funcionalidades clave: [tres ticks manuscritos sobre esta línea y manuscrito "(1.0)"]

- SKU maestro con categoría, marca, atributos físicos (peso y dimensiones para cotizar despacho) ["peso y dimensiones" encerrado en óvalo manuscrito, conectado con el recuadro "14 m³"] [✓]
- Listas de precios por canal: presencial, Mercado Libre, Falabella, web, mayorista [✓]
- Reglas de precio por cliente o segmento [✓]
- Importación y exportación masiva (CSV) para mantenimiento eficiente [paréntesis manuscrito de apertura antes de "Importación"; ✓]
- Versionado de precios con fecha de vigencia [✓ largo]

Procesos que resuelve: Habilitador para todo módulo de venta. Resuelve directamente "precios distintos por marketplace requieren manejo manual" (A04, A05).

Dependencias: M01.

Esfuerzo: M (3-4 semanas).

Fase: MVP.

[manuscrito en diagonal, margen derecho inferior, serie numérica en columna: "10 10 / 10 20 00Z" — esta línea encerrada en óvalo — "/ 10 30 / 10 40 030 / 10 50 / [más abajo] 80 10 001 / 80 20 0[ilegible]"; al costado derecho, palabra [ilegible, posible "Diseño" o "Dice Víctor"]; una raya larga diagonal cruza el bloque de M03]

---

**M03 · Clientes (base maestra)**

Qué hace: Centraliza la información de clientes para reutilizarla en cualquier punto de venta o canal, eliminando la carga manual repetitiva.

Funcionalidades clave:

- Ficha de cliente con RUT, razón social, giro, dirección, correo, teléfono
- Búsqueda por RUT con precarga al facturar [✓]
- Integración con lector de RUT físico tipo Sodimac para auto-rellenar datos [marca pequeña]
- Historial de compras del cliente
- Marca para envío automático de factura por correo [✓]
- Segmentación (mayorista, retail, recurrente) [✓]

### Página 3

Procesos que resuelve: A01 (los 5 minutos de carga manual de RUT en presencial), A02 (envío automático de factura por correo si está registrado), C03 (atención en sala de venta Coquimbo), todos los procesos donde se facture.

Dependencias: M01. [raya manuscrita en el margen derecho]

Esfuerzo: S (2-3 semanas).

Fase: MVP.

---

**Bloque B · Inventario** [manuscrito, entre paréntesis, junto al título: "Problema entre VENDEDORES no Sucursales" — escrito en dos líneas]

**M04 · Inventario multi-bodega**

Qué hace: Mantiene stock real y proyectado de cada SKU por bodega física y virtual, con visibilidad cruzada entre sucursales, reservas con dueño, transferencias con flujo de aprobación, y alertas de reorden.

Funcionalidades clave:

- Bodegas físicas: Mirador, Coquimbo, Abate Molina, ~~Providencia~~, Buzeta ["Providencia" tachada con línea manuscrita]
- Bodegas virtuales dentro de una física: contenedores, certificaciones/reserva, servicio técnico [manuscrito debajo, entre paréntesis: "MEJORAR"]
- Movimientos: ingreso, salida, ajuste, traspaso entre bodegas, recepción de proveedor [✓]
- Reservas con dueño (sucursal o usuario) y vencimiento configurable [✓]
- Punto de reorden por SKU con sugerencia de cantidad según rotación histórica [✓]
- Alertas automáticas: stock bajo mínimo, reservas próximas a vencer, productos sin movimiento ["stock bajo mínimo" subrayado; "productos sin movimiento" subrayado y encerrado con trazo manuscrito]
- Vista cruzada de stock entre todas las sucursales [manuscrito a continuación: "¿? → Perfiles"]
- Solicitud de transferencia entre sucursales (consume M14 para aprobación)
- Algoritmo configurable de distribución por % histórico de ventas para mercadería importada

Procesos que resuelve: A09 (reposición de productos), A10 (stock y reservas entre sucursales), C01 (abastecimiento camión propio a Santiago), C02 (recepción y control de mercadería), C08 (solicitud de stock entre sucursales), M01 (asignación de stock desde Santa Rosa).

Dependencias: M01, M02.

Esfuerzo: L (6-8 semanas).

Fase: MVP.

### Página 4

[manuscrito, arriba del título, en dos líneas: "¿Qué [ilegible] es[a] Bsale?" — lectura parcial]

**Bloque C · Ciclo comercial**

**M05 · Cotización + ciclo de factura (con Bsale)** ["con Bsale" con subrayado manuscrito; raya al margen derecho]

Qué hace: Gestiona el ciclo completo de un documento tributario desde su origen como cotización hasta el cierre administrativo, integrándose con Bsale para emisión al SII.

Funcionalidades clave:

- Cotización con vencimiento configurable (5 días por defecto) [raya y manuscrito: "ya está"]
- Aplicación de descuentos según rango autorizado por vendedor [raya y manuscrito: "ya está"; check largo debajo]
- Validación automática de stock asignado antes de emitir [✓ antes del bullet]
- Emisión de boleta o factura electrónica vía API de Bsale
- Estados explícitos por documento: emitida → cargada → en ruta → entregada → cobrada → cerrada
- Asignación a vendedor, stock reservado, cliente, transportista [raya al margen]
- ~~Envío automático por correo si el cliente tiene email registrado~~ [línea completa tachada]
- Confirmación de lectura del correo
- Generación de QR escaneable para validación posterior (entrada a M07)
- Cierre administrativo con conciliación de pagos
- Cálculo automático de bono al conductor por destino y kilometraje (rutas fuera de Santiago)
- Alertas de cotizaciones próximas a vencer

Procesos que resuelve: A02 (facturación electrónica), M02 (cotización y autorización de factura), M05 (cierre administrativo del ciclo), y es el núcleo de todo flujo donde se emita documento tributario. [trazo curvo sobre "resuelve"]

Dependencias: M01, M02, M03, M04, M14 (para descuentos sobre 30%).

Esfuerzo: L (6-8 semanas).

Fase: MVP.

---

**M06 · POS sala de venta** [garabato/espiral de tinta manuscrito junto al título]

Qué hace: Interfaz operativa para sala de venta presencial, optimizada para velocidad, con roles separables (cotizador, cajero, entregador).

Funcionalidades clave:

### Página 5

- Pantalla rápida de venta (búsqueda producto, selección cliente, forma de pago) [✗]
- Lector de RUT integrado con auto-precarga del cliente [✗]
- Roles separables: un computador puede operar solo como "cajero" o solo como "cotizador" [✗]
- Verificación automática de transferencia bancaria (vinculación a número de operación) [manuscrito: "(✓)"]
- Integración POS y Webpay [manuscrito: "(✓)"]
- Impresión a impresora térmica [manuscrito: "(Cable)" — lectura probable]
- Atención multi-puesto en paralelo (varios computadores atendiendo al mismo tiempo) [✗]

Procesos que resuelve: A01 (venta presencial en sucursal — el cuello de botella de "el mismo vendedor cotiza, cobra y entrega"), C03 (venta presencial y entrega en bodega Coquimbo). [trazo sobre "cuello de botella"; aspas ✗ y "¿?" manuscritos bajo "vendedor cotiza, cobra y entrega"]

Dependencias: M01, M02, M03, M04, M05. [manuscrito, margen derecho, en recuadro: "QR"]

Esfuerzo: M (4-5 semanas).

Fase: Fase 2 (rollout Abate Molina, que es donde la sala de venta presencial es más crítica). En Coquimbo puede usarse una versión mínima como parte del MVP.

---

[manuscrito sobre el título, entre paréntesis, en dos líneas: "Folio / Picking", seguido de una flecha ▶ que se prolonga en un trazo largo curvo por el margen derecho hasta la zona de "Procesos que resuelve" de M07]

**M07 · Validación de retiro (anti-fraude con QR)**

Qué hace: Garantiza que una factura solo se entrega una vez, validando su estado en el sistema mediante un identificador único escaneable. Resuelve el caso real del casi-fraude millonario con factura adulterada.

Funcionalidades clave:

- Generación de QR único por documento al emitir factura/boleta [✓]
- Escaneo en puesto de bodega (PC con impresora del módulo, "estilo McDonald's") [marca]
- Validación en tiempo real: estado actual, monto, cliente, ítems [✓]
- Alerta automática inmediata si la factura ya fue retirada (intento de doble entrega) [✓]
- Aprobación remota requerida sobre $1.000.000 (notificación a Luis o Héctor por app) [✓]
- Marcado automático del documento como entregado (entrega total o parcial) [✓ largo]
- Pantalla en bodega tipo McDonald's: facturas emitidas arriba aparecen en cola abajo

Procesos que resuelve: A07 (retiro de mercadería con factura, donde casi se entrega un pedido millonario con factura adulterada), C03 (entrega en bodega Coquimbo, la aspiración explícita de Luis del "PC con impresora en bodega"). [✓ al final]

Dependencias: M01, M05, M14.

Esfuerzo: M (3-4 semanas). [manuscrito, margen derecho: "PC +"]

Fase: MVP.

### Página 6

**M14 · Workflow de aprobaciones**

Qué hace: Motor transversal de aprobaciones digitales para acciones que requieren autorización superior. Reemplaza las aprobaciones verbales y por WhatsApp con trazabilidad completa. [raya manuscrita]

Funcionalidades clave:

- Reglas configurables por tipo de acción (descuento sobre %, transferencia entre sucursales, retiro alto monto, ajuste de stock, etc.) [raya]
- Notificación al aprobador por múltiples canales (push, correo, WhatsApp) [✓] [raya]
- Aprobación remota desde celular sin necesidad de presencia física [✓]
- Histórico completo de aprobaciones con motivo y resultado [✓]
- Escalamiento automático si el aprobador no responde en N minutos [manuscrito: "← Hermoso"]
- Reportes por aprobador y por solicitante (para detectar patrones) [✓]

Procesos que resuelve: A11 (aprobación de descuentos sobre 30% por Luis), A07 (retiro de montos altos sin firma física de Luis/Héctor), A10 y C08 (autorización de transferencias entre sucursales), M02 (autorización de facturas por Héctor). [doble check ✓✓ manuscrito debajo]

Dependencias: M01, M15.

Esfuerzo: M (3-4 semanas).

Fase: MVP.

---

**Bloque D · Distribución** [manuscrito junto al título, entre paréntesis: dos palabras [ilegible]]

**M08 · Despacho + PWA conductor + cotizador transportistas**

Qué hace: Gestiona todo el flujo desde la generación de la hoja de ruta hasta la confirmación de entrega al cliente final, incluyendo la coordinación con transportistas externos.

Funcionalidades clave:

- Cotizador integrado vía APIs Chilexpress, Starken, Cruz del Sur (sugerencia automática del más conveniente o el preferido del cliente) [✓ bajo "Starken"]
- Generación automática de guía de despacho al emitir factura o al cargar el camión [raya]
- Hoja de ruta optimizada: agrupador automático por sector, optimización del orden de entregas [raya]
- PWA del conductor: ruta del día asignada, navegación, lista de stops, forma de pago por entrega ["PWA" encerrado en círculo manuscrito; ✓ largo bajo el bullet]

### Página 7

- Confirmación digital de entrega: firma del receptor, foto del producto entregado, hora exacta [raya]
- Tracking al cliente final: notificación con ventana estimada de entrega [raya]
- Umbrales configurables para salidas de ruta ("salir cuando se acumulen X pedidos o pasen Y días") [comillas dobles manuscritas sobre "configurables" y sobre "ruta"; ✓ debajo]
- Modo venta-en-ruta: catálogo offline + facturación posterior (camión Atacama) [manuscrito, margen derecho, dos líneas: "3era Región"]
- Plan B automático: si un cliente cancela en ruta, sugerir cliente alternativo en la zona [paréntesis manuscrito de apertura antes de "Plan B" y de cierre tras "zona"; "sugerir cliente" con trazo debajo; manuscrito al margen: "– Zona" — lectura probable]

Procesos que resuelve: A06 (cotización y despacho con transportistas), C04 (despacho urbano diario), C05 (ruta semanal a ~~Atacama~~ [manuscrito encima: "3era"; debajo: [ilegible, posible "7ma"]]), C06 (ruta a los valles), M03 (preparación de ruta y carga de camión), M04 (entrega en ruta y retorno). [raya al final]

Dependencias: M01, M04, M05, M15.

Esfuerzo: XL (8-10 semanas).

Fase: MVP (versión Coquimbo, sin marketplaces). Optimización avanzada y ruta Mirador en Fase 2.

---

**Bloque E · Canales digitales**

[manuscrito, letra "A" en el margen izquierdo] **M09 · Integración marketplaces (Mercado Libre + Falabella)** [el "M09" impreso con trazos manuscritos encima, garabateado]

Qué hace: Sincroniza órdenes, stock y precios bidireccionalmente con Mercado Libre y Falabella, eliminando el triple registro manual (notita → Excel → Bsale). [✓]

Funcionalidades clave:

- Integración API Mercado Libre: descarga automática de órdenes, filtrado de canceladas antes de imprimir, generación de boleta vinculada al ID de orden [raya]
- Integración API Falabella: descarga de órdenes, subida automática de boleta, gestión de reclamos [✓ debajo]
- Generación e impresión de etiquetas con impresora térmica dedicada [manuscrito encima: "Cotizar →"; al final de la línea, tachadura densa de tinta sobre texto manuscrito [ilegible] y debajo otra palabra [ilegible]]
- Aplicación automática de lista de precios por marketplace (consume M02) [✓]
- Bandeja única de tickets de reclamo de ambos marketplaces, categorizados [raya]
- Sincronización de stock disponible por canal (evitar sobreventa) ["sobreventa" subrayado; ✓]
- Reportes por marketplace (margen real, comisiones, devoluciones) [✓]

Procesos que resuelve: A03 (venta por Mercado Libre y sus +50 órdenes acumuladas los lunes), A04 (venta por Falabella), parcialmente A12 (devoluciones de marketplaces, complementado con M13).

Dependencias: M01, M02, M04, M05.

### Página 8

Esfuerzo: L (6-8 semanas).

Fase: Fase 2 (entrada en Abate Molina).

---

**M10 · Integración eCommerce (WordPress)** [manuscrito junto al título, entre paréntesis, en dos líneas: "Nuevo APP B[sale?]" — lectura dudosa]

Qué hace: Sincroniza la tienda WordPress propia con el sistema interno, automatizando la generación de factura, productos y stock, y mitigando los problemas del WordPress de 2016. [raya al final]

Funcionalidades clave:

- Sincronización productos y stock con WooCommerce (o plugin equivalente) [raya]
- Carga automática de productos de la orden al sistema interno (sin cargar uno por uno)
- Selector obligatorio de transportista en el checkout
- Cotización automática de despacho según destino y peso al pagar
- Coordinación de retiro en tienda con calendario
- Webhook para órdenes nuevas en tiempo real
- Plan de migración del WordPress de 2016 a servidor independiente del correo

Procesos que resuelve: A05 (venta por página web con WordPress).

Dependencias: M01, M02, M04, M05, M08.

Esfuerzo: M (4-5 semanas). [manuscrito, margen derecho: "$ / tiempo"]

Fase: Fase 2 o 3 según prioridad real del canal web.

---

**Bloque F · Operaciones especializadas** [manuscrito, margen derecho, tres líneas con raya vertical y checks: "Curso que 200.000 / 7 días → 1/2 sem / SW 3/4 semanas" — lectura probable de "SW" = software]

**M11 · Producción de botellones**

Qué hace: Captura y traza la producción diaria de botellones en Mirador y Coquimbo, eliminando la hoja de papel del soplador y el desfase de 24 horas en el ingreso a Bsale. [✓]

Funcionalidades clave:

- PWA del soplador con catálogo de SKUs que el operador conoce (botellón 20L con manilla, sin manilla, etc.)
- Registro de producción diaria con desglose: 1ra, 2da y malos [✓]
- Auditoría automática del recalibrado al cambiar tipo de preforma [✓✓]
- Descuento automático de preforma del stock al registrar producción [✓ grande]
- Indicadores por soplador: % 1ra, % 2da, % malos, productividad

### Página 9

- [línea superior cortada por el escaneo:] Tablero diario de producción con metas configurables [✓ con trazo]
- Asociación automática a Guía de Producción (GP) [raya]
- Modo "constructivo" para producción sin pausa al almuerzo [aspa ✗ manuscrita sobre "constructivo"; manuscrito al costado, entre rayas verticales: "otra área" — lectura probable]

Procesos que resuelve: C07 (producción de botellones en Coquimbo), M06 (producción de botellones en Mirador).

Dependencias: M01, M02, M04.

Esfuerzo: M (4-5 semanas).

Fase: Fase 3 (rollout Mirador, donde está el grueso de la producción).

---

**M12 · Servicio técnico**

Qué hace: Gestiona el ciclo completo del taller postventa, desde la pre-recepción de la máquina hasta el cierre con garantía o cobro de bodegaje, reemplazando OneDrive + papel + WhatsApp. [raya larga]

Funcionalidades clave:

- Formulario online de pre-ingreso que el cliente llena antes de llegar (datos cliente, datos máquina, falla) [manuscrito: "QR"]
- Folio único con QR que reemplaza la copia física impresa en duplicado [raya]
- Identificación automática garantía vs reparación con vinculación a boleta/factura original [✓]
- Categorías estándar de falla para reportes y análisis [✓]
- Diagnóstico digital del técnico (Fernando) con cotización estructurada [raya]
- Aprobación del cliente vía link enviado a WhatsApp [✓]
- Cobro integrado al retiro o anticipado por Webpay [✓]
- Alertas automáticas de garantía y bodegaje: 3 meses (fin de garantía), 6 meses (inicio cobro bodegaje), 12 meses (máquina pasa a DALI) [✓; trazo sobre "3 meses"]
- Historial técnico por equipo (todas las intervenciones, repuestos usados) [✓] [manuscrito: flecha → hacia nota en columna al margen derecho: "Solicitud de repuestos en base a la info" — lectura probable]
- Tablero de máquinas próximas a cumplir plazo [raya]
- Registro del destino final (desarme para repuestos, reventa, donación) [✓]
- Posibilidad de envío directo del cliente a Mirador, saltando Abate Molina [manuscrito: "?"]

Procesos que resuelve: A08 (recepción de máquinas para servicio técnico en Abate, que ocupa espacio sin corresponderle), M07 (recepción para servicio técnico en Mirador), M08 (diagnóstico y cobro técnico), M09 (garantía y bodegaje post-reparación). [✓]

Dependencias: M01, M02, M03, M04, M05, M15.

Esfuerzo: L (6-8 semanas).

### Página 10

Fase: Fase 3 (rollout Mirador). [raya larga sobre la línea]

---

**M13 · Devoluciones**

Qué hace: Estandariza la recepción y procesamiento de devoluciones, especialmente las de marketplaces, con trazabilidad y evidencia fotográfica.

Funcionalidades clave:

- Formulario estándar de devolución que llena el cliente [manuscrito encima: "QR"; ✓✓]
- Fotos obligatorias del producto al ser recibido en bodega [✓]
- Categorización de causa: daño en transporte, defecto de fábrica, otro [✓]
- Reglas automáticas según tipo de daño y origen [✓] [raya]
- Vinculación al transportista cuando hay reclamo de transporte [raya]
- Gestión de reembolso (consume M14 si requiere aprobación por monto) [✓]
- Reingreso automático a stock si el producto está en buen estado [✓]
- Reportes agregados por causa de devolución y por marketplace [✓]

Procesos que resuelve: A12 (devoluciones de marketplaces, hoy concentradas en una sola persona sin apoyo).

Dependencias: M01, M04, M05, M14, M15.

Esfuerzo: M (3-4 semanas).

Fase: Fase 2 o 3.

---

**Bloque G · Cross-cutting**

**M15 · Notificaciones (WhatsApp + correo)** [dos rayas manuscritas largas apuntando al título desde el margen derecho, con dos notas: "Importadora Dali" (lectura probable) e "impo[rtadora]dali.cl" (lectura probable)]

Qué hace: Motor centralizado de notificaciones a clientes finales, internas al equipo, y a aprobadores, vía correo electrónico y WhatsApp Business API.

Funcionalidades clave:

- Integración correo: SMTP saliente y IMAP de entrada para confirmaciones de lectura ["IMAP de" encerrado en círculo manuscrito; raya al final]
- Integración WhatsApp Business API [✓]
- Plantillas configurables por tipo de evento (factura emitida, despacho en ruta, servicio técnico listo, bodegaje próximo a vencer, etc.) [raya] [✓]
- Triggers automáticos desde los otros módulos ["Triggers" encerrado en círculo/paréntesis manuscrito]
- Reintentos automáticos ante fallas de envío [✓]

### Página 11

- [línea superior cortada por el escaneo:] …por destinatario [✓]
- Opt-out por canal (cliente puede pedir no recibir por WhatsApp) [manuscrito a continuación: flecha y "[ilegible] o [ilegible]" — posible "¿app o correo?" / "¿o cómo?"]

Procesos que resuelve: Habilitador transversal. Específicamente: A02 (envío automático de factura), A08 y M07 (notificaciones de estado del servicio técnico), C04 (ventana de entrega al cliente), M09 (alertas de garantía y bodegaje). [trazo sobre "A02"; raya bajo la última línea]

Dependencias: M01.

Esfuerzo: M (3-4 semanas).

Fase: MVP (con plantillas mínimas para el ciclo factura + despacho; expansión en fases siguientes).

---

**M16 · Reportes y BI** [paréntesis manuscrito "(" junto al título]

Qué hace: Tableros y reportes para análisis del negocio, accesibles según rol, que sustituyen los Excel manuales que hoy generan al cierre del día. [raya tras "negocio,"]

Funcionalidades clave:

- Tablero ejecutivo (Luis): ventas por sucursal, márgenes, stock crítico, descuentos aplicados [raya]
- Reportes de descuentos por vendedor con margen resultante (detección de patrones) [✓]
- Reportes de transferencias entre sucursales con quién aprobó y qué unidades movió [raya]
- Reportes de producción: % de mermas por soplador, productividad diaria [raya]
- Reportes de devoluciones por causa y por canal [raya]
- Reportes de despachos en curso con estado de seguimiento [✓]
- Reporte de bodegaje próximo a vencer en servicio técnico [raya]
- Exportación a Excel y PDF
- Histórico configurable con permisos por rol
- [bullet manuscrito agregado al final de la lista:] "Pedido de ST de repuesto[s]" — lectura probable

Procesos que resuelve: Habilitador transversal. Sustituye los Excel del cierre administrativo (M05 de Mirador) y da a Luis los reportes hoy ausentes sobre descuentos, transferencias y producción. [raya al final; raya al margen derecho]

Dependencias: Todos los módulos transaccionales que produzcan datos.

Esfuerzo: L (6-8 semanas, iterativo).

Fase: Iterativo desde MVP (primer tablero básico) con expansión continua en cada fase.

---

**Secuencia de construcción sugerida**

### Página 12

**Sprint 0 (semanas 1-2): Discovery cerrado**

- Workshop final con Luis, Héctor, Ricardo, Gonzalo para validar prioridades
- Decisión cerrada sobre Bsale (recomendación: estrategia híbrida)
- Validación del alcance de MVP módulo por módulo
- Acceso a credenciales Bsale, marketplaces, dominios web, WhatsApp Business
- Arquitectura técnica definida (stack, infraestructura, despliegue)

**MVP ~~Coquimbo~~ (mes 0-7)** ["Coquimbo" tachado; manuscrito encima: "General" (lectura probable) con signo "!"; manuscrito debajo: "Fase 1"]

Construcción en este orden, con paralelismos cuando los dos desarrolladores puedan trabajar en módulos independientes:

1. M01 Core (semanas 3-5)
2. M02 Catálogo + M03 Clientes (semanas 4-7, en paralelo desde el final de M01)
3. M04 Inventario multi-bodega (semanas 5-12)
4. M05 Cotización + ciclo factura + Bsale (semanas 7-14, paralelo parcial con M04)
5. M14 Aprobaciones + M15 Notificaciones (semanas 10-13) [trazo antes del número]
6. M07 Validación de retiro QR (semanas 12-15) [manuscrito, margen izquierdo: [ilegible, posible "con"]]
7. M08 Despacho + PWA conductor (semanas 14-20)
8. Hardening + piloto Coquimbo + ajustes (semanas 20-26)

[manuscrito bajo el ítem 8: [ilegible, posible "Falabella"]]

Salida del MVP: Coquimbo opera con stock multi-bodega, ciclo de factura trazable, validación de retiro anti-fraude, y PWA del conductor para rutas urbana, Atacama y valles. Bsale sigue emitiendo al SII.

**Fase 2 · Rollout Abate Molina (mes 7-11)**

1. M06 POS sala de venta [raya]
2. M09 Integración marketplaces (Mercado Libre + Falabella) [raya; la "M" de M09 con trazo encima]
3. M13 Devoluciones (entra junto con marketplaces) [✓ margen derecho]
4. Adaptaciones a M04 y M05 según el aprendizaje real del uso en Coquimbo [raya]
5. Piloto en Abate Molina

**Fase 3 · Rollout Mirador (mes 11-15)** [marca manuscrita tipo "Y"/check junto al título]

1. M11 Producción de botellones
2. M12 Servicio técnico (todo el flujo del taller, incluye garantías y bodegaje) [raya]
3. M10 Integración WordPress (puede entrar antes si la prioridad del canal web es alta) [línea horizontal manuscrita que subraya/tacha todo el ítem; manuscrito, margen izquierdo: "A →"; la "M" de M10 con trazo encima]
4. M16 Reportes BI con expansión completa [raya]

### Página 13

- [ítems superiores cortados por el escaneo; se lee:] 5. Piloto en Mirador [parcial]
- 6. Apagado de los workflows manuales heredados (WhatsApp, Excel, OneDrive) [✓]

**Mapa de dependencias**

Resumen de qué depende de qué, para planificar el orden de construcción:

[Recuadro impreso con un diagrama de árbol en tipografía monoespaciada, **casi completamente ilegible por el bajo contraste del escaneo**. Se alcanzan a distinguir fragmentos: "…M07 Validación QR…", "…C04 Despacho + PWA…", "…M09 Marketplaces…", "…M10 eCommerce…", "…M12 Servicio técnico…", "…M13 Devoluciones…", "M14 Aprobaciones → consumido por M05, M07, M08, M13", "M15 Notificaciones → consumida por casi todos", "M16 Reportes → consume datos de todos". Sin anotaciones manuscritas visibles dentro del recuadro.]

**Nota final**

Esta especificación es un esqueleto. Cada módulo necesita aterrizaje funcional con el equipo de DALI antes de empezar a construir: pantallas exactas, flujos detallados, reglas de negocio puntuales, casos límite.

Regla de oro para mantener el proyecto bajo control con 2 desarrolladores: **no congelar el alcance de un módulo más allá de su Sprint 0** [aspa/marca manuscrita junto a "no congelar el"], y revisar funcionalidades al cierre de cada módulo según lo aprendido en el anterior. El piloto Coquimbo va a generar aprendizajes que cambiarán el detalle de los módulos de Fase 2 y Fase 3.

Documento de trabajo, no contractual. Revisar y ajustar según levantamientos posteriores.
