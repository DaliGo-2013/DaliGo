# DaliGo — Contexto completo del proyecto para desarrollo

> Documento maestro consolidado para asistentes de IA de programación.
> Fuentes: especificación modular v2.0 (correcciones de Luis Lazcano, mayo 2026), respuesta técnica de arquitectura y hosting (junio 2026), carta Gantt (9 meses), y levantamientos de procesos de las 3 sucursales (Abate Molina, Coquimbo, Mirador).
> Última consolidación: junio 2026.

---

## 1. Qué es DaliGo

**Empresa:** DALI Cargos-Transporte / Importadora DALI (Chile). Tres líneas de negocio cruzadas:

1. **Distribución** de productos abastecidos desde la planta de Santa Rosa.
2. **Producción propia de botellones** a partir de preforma importada de China, soplada en Mirador y Coquimbo (~600 botellones diarios por soplador).
3. **Servicio técnico postventa** de máquinas y herramientas (taller central en Mirador).

**Sucursales / bodegas físicas:** Mirador (bodega central), Coquimbo, Abate Molina, Buzeta (bodega secundaria). **Providencia fue eliminada** del listado de bodegas físicas activas.

**Qué es el sistema:** panel de gestión interno (ERP ligero) multi-sucursal + PWA móvil offline. **Complementa Bsale, NO lo reemplaza.** Bsale sigue siendo el sistema de facturación electrónica al SII.

**Objetivo central del proyecto:** eliminar el papel del ciclo completo de la factura (cotización → autorización → guía → entrega → cierre) y dar trazabilidad a operaciones que hoy viven en memoria, Excel y WhatsApp.

> Cita guía del proyecto: *"La idea es que a lo que realmente le tengan que dedicar tiempo, tengan todo el tiempo disponible, y las tareas repetitivas sean dos clics en su mayoría."*

**Usuarios:** ~20–40 internos, picos de 15–25 concurrentes. Roles: vendedor, jefe de ventas, jefe de bodega, conductor, técnico, soplador, admin.

**Nombre del sistema:** pendiente de definir oficialmente (sugerencias: APP DALI, Importadora DALI; nombre de trabajo: DaliGo). Luis pidió textualmente "escriban diez mil veces el nombre" para diferenciarlo de Bsale.

---

## 2. Stack técnico y restricciones de hosting (DECISIONES CERRADAS)

**Entorno:** HostGator cPanel compartido (usuario `impdali`), Apache + PHP-FPM, PHP 8.1–8.3, MySQL 5.7.23. Sin Docker, sin Redis, sin PostgreSQL, sin daemons.

| Capa | Decisión |
|---|---|
| Lenguaje | PHP 8.3 (default en MultiPHP) |
| Framework | Laravel (confirmar 11/12 contra PHP 8.3) |
| Panel web | Blade + Livewire 3 + Alpine.js (SSR, poco JS) |
| Móvil | PWA instalable (service worker + IndexedDB), sincronización diferida offline — conductor y sopladores |
| BD | MySQL 5.7.23, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` |
| Auth/permisos | Laravel Fortify/Breeze + `spatie/laravel-permission`; auditoría con `owen-it/laravel-auditing` |
| Colas/jobs | Driver `database`, procesadas por cron (`queue:work --stop-when-empty --max-time=840` cada 15 min — grilla `*/15`, I-01). SIN daemons |
| Cache/sesión | Driver `database` o `file` (no Redis) |
| Correo | Sendmail al inicio; SMTP autenticado o Mailgun/SES para entregabilidad |
| Assets | Vite con Node solo en build-time; el runtime NO necesita Node |
| Despliegue | Git de cPanel + Composer por SSH; código fuera de `public_html`; docroot → `/daligo/public` |

**Por qué no Node/Python:** exigirían Phusion Passenger (hoy 0 apps), versión de Node sin confirmar, sin websockets garantizados. DaliGo es 90% CRUD de gestión; no necesita tiempo real intensivo (notificaciones por push/WhatsApp/correo + polling ligero).

### Restricciones obligatorias del entorno

- Diseñar para MySQL 5.7 (NO 8 / MariaDB): **sin CTE `WITH`, sin window functions, sin `JSON_TABLE`**, sin columnas generadas avanzadas. El tipo JSON existe en 5.7 — usarlo solo para metadata.
- Collation `utf8mb4_unicode_ci` (NO `utf8mb4_0900_*`, es de MySQL 8).
- Índices únicos en strings: `VARCHAR(191)` (patrón `Schema::defaultStringLength(191)`).
- Sin procesos daemon: colas y schedule por cron en grilla `*/15` (HostGator reescribe los crons <15 min — I-01, CLAUDE.md [2026-07-07]; toda tarea agendada cae en :00/:15/:30/:45).
- Código fuera de `public_html`; solo `/public` como docroot. `.env` jamás versionado ni en `/public`.
- No interferir con los WordPress existentes (33 BBDD `wp_`/`wrdp_` en el mismo cPanel).
- Staging en `daliprueba.cl`; producción en subdominio dedicado (p.ej. `app.impdali.cl`). SSL (AutoSSL) resuelto antes de producción.

### Estructura de directorios en el servidor

```
/home4/impdali/
├─ public_html/        # WordPress dominio principal — NO TOCAR
├─ daligo/             # código producción, FUERA del docroot
│  ├─ app/ bootstrap/ config/ database/ routes/ resources/
│  ├─ storage/  vendor/
│  ├─ public/          # ÚNICA carpeta expuesta
│  └─ .env
└─ daligo_staging/     # misma estructura para daliprueba.cl
```

### Despliegue (resumen)

1. Subdominio staging → docroot `/daligo_staging/public`. 2. BD y usuario MySQL utf8mb4. 3. Clonar repo con Git de cPanel fuera del docroot. 4. `composer install --no-dev --optimize-autoloader`. 5. Configurar `.env` + `key:generate`. 6. `migrate --force` + `storage:link`. 7. `npm ci && npm run build` (build-time). 8. Cron 1: `schedule:run` en `*/15` (NUNCA por-minuto: HostGator lo reescribe — I-01). Cron 2: `queue:work --stop-when-empty --max-time=840` en `*/15`. 9. AutoSSL + HTTPS forzado. 10. Permisos de escritura a `storage/` y `bootstrap/cache/`. 11. Validar todo en staging antes de producción.

### Riesgos técnicos declarados

- MySQL 5.7 EOL (oct-2023): diseñar para 5.7 ahora, planificar migración a MySQL 8/MariaDB (VPS o plan superior) a mediano plazo.
- PWA offline (ruta Atacama): sincronización diferida y resolución de conflictos es **el punto técnico más delicado — prototipar pronto**.
- Hosting compartido: vecinos ruidosos, sin long-running processes.
- Entregabilidad de correo: configurar SPF/DKIM/DMARC.
- Dependencia de Bsale: validar con Víctor qué se reutiliza antes de construir.

---

## 3. Reglas de negocio estructurales (las 18 correcciones de Luis)

Decisiones tomadas en la reunión del 11–12 de mayo de 2026 que condicionan TODO el diseño:

1. **Bsale se mantiene como base.** El sistema lo complementa. Toda funcionalidad se valida con Víctor (Bsale) antes de duplicar.
2. **La gestión es por VENDEDOR, no por sucursal.** Reservas y permisos se asocian al vendedor que las gestiona. Cambia la base del modelo de inventario (M04).
3. **Limpieza de bodegas virtuales:** hoy hay ~25 virtuales para 3 físicas. Rediseñar (anotación "MEJORAR" de Luis). Requiere levantamiento antes de M04.
4. **Providencia eliminada** como bodega física. Quedan: Mirador, Coquimbo, Abate Molina, Buzeta.
5. **Vista cruzada de stock filtrada por perfil** del usuario. No es para todos. Matriz "qué perfil ve qué" pendiente de definir.
6. **M06 POS sala de venta en STANDBY.** "Lo que funciona no se toca" — Bsale en sala de venta sigue igual.
7. **Modo "constructivo" de producción sale de M11** (es operación de otra área).
8. **Soplador usa CELULAR, no tablet** (ya tienen celular; ahorro de costos).
9. **Producción requiere aprobación del jefe de bodega** antes de cargarse al stock.
10. **Servicio técnico sugiere compras según patrones históricos.** Ej.: "Fernando ocupa 25 termostatos/mes promedio; si pide 30, cuestionar."
11. **Rutas por ZONA con vendedor asignado** (zonas: norte, oriente, costa, valles). Cada zona tiene vendedor con cartera de clientes.
12. **Nombre de identidad propia** pendiente (que no se confunda con Bsale).
13. **Boleta rápida sin datos del cliente** para el que "paga y se va". Sin garantía asociada. Va en M03 y M05. Flujo tributario por validar con contabilidad.
14. **Aprobación de Héctor: de 5 pasos manuales a 1–2.** El sistema automatiza 3–4 validaciones (pago, stock, descuento); solo queda decisión humana en lo no resoluble.
15. **Umbral de aprobación remota parametrizable** ($1.000.000 era solo ejemplo). Configurable desde admin.
16. **Transversales se construyen una sola vez desde el día 1** para las 3 sucursales; solo las especialidades van por fases.
17. **QR antifraude (M07) es prioridad MVP** — validado con caso real: factura adulterada de botellones que casi termina en fraude millonario (intento de doble retiro).
18. **NO rehacer lo que ya está en Bsale:** lectores de RUT, boleta/factura, descuentos por rango autorizado, envío automático por correo, organigrama de usuarios. Conectarse a Bsale para usarlas.

---

## 4. Catálogo de módulos (16)

Notación de esfuerzo (2 devs full-time): S 1–3 sem · M 3–6 · L 6–10 · XL 10+.
Estados: VALIDADO / CON AJUSTES / NUEVO / YA EN BSALE / STANDBY.

### Bloque A · Transversales (las 3 sucursales, desde el día 1)

**M01 · Core / Plataforma base** — YA EN BSALE parcial · M (3–4 sem) · sin dependencias
Autenticación, roles configurables, multi-sucursal (Mirador, Coquimbo, Abate Molina, Buzeta), permisos granulares por módulo/acción, log de auditoría (quién/qué/cuándo/dónde), configuración global (parámetros, % distribución por sucursal, umbrales), API gateway interno. Validar con Víctor qué parte del modelo de usuarios se reutiliza de Bsale.

**M02 · Catálogo + listas de precios** — YA EN BSALE ~90% · S-M (2–3 sem) · dep: M01
SKU maestro con categoría, marca, **peso y dimensiones (lo principal que falta cargar — necesario para cotizar despacho)**. Listas de precios por canal (presencial, Mercado Libre, Falabella, web, mayorista). Reglas por cliente/segmento. Import/export CSV **con botón de filtro previo a exportar** (confirmado por Luis). Versionado de precios con vigencia.

**M03 · Clientes + boleta rápida** — CON AJUSTES · S (2–3 sem) · dep: M01
Ficha con RUT, razón social, giro, dirección, correo, teléfono. Búsqueda por RUT con precarga. Lector de RUT físico ya está en Bsale (reusar). Historial de compras. Marca de envío automático de factura por correo. Segmentación (mayorista, retail, recurrente). **NUEVO: modo boleta rápida** sin datos del cliente, sin garantía, atención < 1 minuto.

**M14 · Workflow de aprobaciones** — VALIDADO (Luis: "hermoso") · M (3–4 sem) · dep: M01, M15
Motor digital que reemplaza WhatsApp y aprobaciones verbales. Reglas configurables por tipo de acción (descuento >30%, transferencia entre sucursales, retiro alto monto, ajuste stock). Notificación al aprobador por push/correo/WhatsApp. **Aprobación remota desde celular.** Histórico completo con motivo y resultado. Escalamiento automático si no responde en N minutos. Reportes por aprobador/solicitante.

**M15 · Notificaciones (WhatsApp + correo)** — CON AJUSTES · M (3–4 sem) · dep: M01
Motor centralizado. SMTP saliente + IMAP para confirmaciones de lectura. WhatsApp Business API (**migración pendiente — confirmar con Marco**). Plantillas por tipo de evento, triggers desde otros módulos, reintentos automáticos. **El cliente elige canal (WhatsApp/correo/ambos) al registrarse.** Opt-out por canal.

**M16 · Reportes y BI** — CON AJUSTES · L (6–8 sem, iterativo) · dep: todos los transaccionales
Tablero ejecutivo: ventas por sucursal, márgenes, stock crítico, descuentos. Reportes: descuentos por vendedor con margen resultante (detección de patrones), transferencias con quién aprobó, producción (% mermas por soplador, productividad), devoluciones por causa/canal, despachos en curso, bodegaje próximo a vencer. Export Excel/PDF. **Vista filtrada por perfil: cada cargo ve solo lo suyo** (ej. técnico ve producción y ST, no contabilidad). Matriz por definir en Sprint 0. Sustituye los Excel del cierre administrativo.

### Bloque B · Operación común (las 3 sucursales, desde el día 1)

**M04 · Inventario multi-bodega** — CON AJUSTES (replanteo significativo) · L (6–8 sem) · dep: M01, M02
Stock **por vendedor** (no solo por sucursal). Bodegas físicas: Mirador, Coquimbo, Abate Molina, Buzeta. **Replantear las ~25 bodegas virtuales hacia algo más simple.** Movimientos: ingreso, salida, ajuste, traspaso, recepción proveedor. Reservas con dueño (vendedor) y vencimiento configurable. Punto de reorden por SKU con sugerencia según rotación histórica. Alertas: stock bajo mínimo, reservas vencidas, **producto sin movimiento 10 días → alerta al vendedor que lo compró**. Vista cruzada según perfil. Solicitud de transferencia entre sucursales (consume M14). Algoritmo configurable de distribución por % histórico de ventas (Mirador ~75%, Abate ~25% como referencia actual).

**M05 · Cotización + ciclo de factura (con Bsale)** — CON AJUSTES · L (6–8 sem) · dep: M01–M04, M14
Cotización con vencimiento configurable (5 días) — ya en Bsale. Descuentos por rango autorizado — ya en Bsale. **NUEVO: validación automática de stock asignado antes de emitir.** Emisión boleta/factura vía Bsale API. **Estados explícitos por documento: emitida → cargada → en ruta → entregada → cobrada → cerrada.** Asignación a vendedor, stock reservado, cliente, transportista. Envío automático por correo según preferencia — ya en Bsale. Generación de QR (entrada a M07). Cierre administrativo con conciliación de pagos. **Cálculo automático de bono al conductor por destino/kilometraje** (rutas fuera de Santiago). Aprobación de Héctor reducida a 1–2 pasos. Soporte a boleta rápida.

**M07 · Validación de retiro anti-fraude (QR)** — VALIDADO (caso real de fraude) · M (3–4 sem) · dep: M01, M05, M14
QR único por documento al emitir. Folio de picking al imprimir desde caja. Escaneo en puesto de bodega (PC físico con impresora). Validación en tiempo real: estado, monto, cliente, items. **Alerta automática si la factura ya fue retirada (doble entrega).** Aprobación remota sobre umbral parametrizable. Marcado automático como entregado (total o parcial). **Pantalla en bodega "tipo McDonald's"**: facturas emitidas en cola arriba, listas para entregar abajo (pedida explícitamente por Luis).

**M08 · Despacho + PWA conductor + cotizador transportistas** — CON AJUSTES · XL (8–10 sem) · dep: M01, M04, M05, M15
Cotizador integrado vía APIs **Chilexpress, Starken, Cruz del Sur** con sugerencia automática. Guía de despacho automática al emitir factura o cargar camión. **Hoja de ruta por ZONA con vendedor asignado**; cartera de clientes por zona con indicadores de color: verde (compra), amarillo (pendiente), rojo (no responde). PWA del conductor: ruta del día, navegación, stops, forma de pago. Confirmación digital de entrega: **firma + foto + hora exacta**. Tracking al cliente con ventana estimada. Umbrales configurables de salida de ruta (X pedidos o Y días). **Modo venta-en-ruta: catálogo offline + facturación posterior** (camión a Atacama, 3ª región; valles = 7ª región para Quintana). **Plan B automático: si un cliente cancela en ruta, sugerir cliente alternativo cercano de la cartera.**

### Bloque C · Especialidades por sucursal (por fases)

**M06 · POS sala de venta** — **STANDBY**, no construir. Bsale actual funciona bien (Abate ~22 facturas/día, ~7 presenciales; Coquimbo ~28/~10; Mirador ~72 presenciales/día). Revisable en fase futura.

**M09 · Marketplaces (Mercado Libre + Falabella)** — CON AJUSTES · L (6–8 sem) + cotización HW · dep: M01, M02, M04, M05 · Fase Abate
API ML: descarga de órdenes, filtrado de canceladas antes de imprimir, boleta vinculada al ID de orden (lunes se acumulan +50 órdenes). API Falabella: descarga órdenes, subida automática de boleta, gestión de reclamos. Etiquetas con **impresora térmica nueva (la actual se sobrecalienta — cotizar como parte del proyecto)**. Lista de precios por marketplace (consume M02). Bandeja única de reclamos de ambos marketplaces. Sincronización de stock por canal (evitar sobreventa). Reportes por marketplace: margen real, comisiones, devoluciones.

**M10 · eCommerce (WordPress/sitio propio)** — CON AJUSTES, decisión pendiente · M (4–5 sem opción A) o L (6–8 opción B) · dep: M01, M02, M04, M05, M08 · backlog post-9-meses
Sincronización productos/stock con WooCommerce. Carga automática de productos de la orden. Selector obligatorio de transportista en checkout. Cotización automática de despacho por destino/peso. Webhook de órdenes nuevas. OPCIÓN A: seguir con WordPress + curso WooCommerce (~$200 USD/7 días). OPCIÓN B: rehacer en código propio. WordPress de 2016 casi descontinuado; migrar a servidor independiente del correo. Mantener los 3 dominios (dali.cl, importadoradali.cl, repuestosdali.cl) por separación de spam.

**M11 · Producción de botellones (sopladores)** — CON AJUSTES · M (4–5 sem) · dep: M01, M02, M04 · ADELANTADO a Fase 2 del plan
**PWA del soplador en CELULAR.** Catálogo de SKUs que el operador conoce ("botellón 20L con manilla", etc.). Registro diario con desglose: 1ª, 2ª y malos (ej. 580/10/10 de ~600). **FLUJO DE APROBACIÓN: el registro se envía al jefe de bodega; solo aprobado se carga al stock.** Descuento automático de preforma al aprobarse. Auditoría del recalibrado al cambiar tipo de preforma. Indicadores por soplador: % 1ª, % 2ª, % malos, productividad. Tablero diario con metas; **meta del día visible al soplador en su celular**. Asociación automática a Guía de Producción (GP). Modo "constructivo" excluido.

**M12 · Servicio técnico (taller)** — CON AJUSTES · L (6–8 sem) · dep: M01, M02, M03, M04, M05, M15
Formulario online de pre-ingreso con QR que el cliente llena antes de llegar. Folio único con QR (reemplaza duplicado en papel). Identificación garantía vs reparación con vinculación a boleta/factura original. Categorías estándar de falla. Diagnóstico digital del técnico (Fernando) con cotización estructurada. **Aprobación del cliente vía link a WhatsApp.** Cobro integrado al retiro o anticipado por Webpay. Regla de negocio: en reparación no aprobada igual se cobra la hora de servicio; garantía sin cobro. **Alertas automáticas: 3 meses (fin garantía), 6 meses (inicia cobro bodegaje), 12 meses (la máquina pasa a DALI: desarme/reventa/donación con registro del destino).** Historial técnico por equipo. Tablero de máquinas próximas a cumplir plazo. **NUEVO: sugerencia automática de compra de repuestos según histórico del técnico.** Validar con Gonzalo el envío directo del cliente a Mirador (saltando Abate).

**M13 · Devoluciones** — VALIDADO · M (3–4 sem) · dep: M01, M04, M05, M14, M15 · ADELANTADO a Fase 2 del plan
**Formulario estándar que llena el CLIENTE** (no operador interno). Fotos obligatorias al recibir en bodega. Categorización: daño transporte / defecto fábrica / otro. Reglas automáticas según tipo de daño y origen. Vinculación al transportista si hay reclamo de transporte. Reembolso (consume M14 si requiere aprobación por monto). **Reingreso automático a stock si está en buen estado.** Reportes por causa y marketplace. Hoy todo lo concentra una sola persona sin apoyo.

### Mapa de dependencias

| Módulo | Depende de |
|---|---|
| M01 Core | — (base) |
| M02 Catálogo | M01 |
| M03 Clientes | M01 |
| M15 Notificaciones | M01 |
| M14 Aprobaciones | M01, M15 |
| M04 Inventario | M01, M02 |
| M05 Ciclo factura | M01, M02, M03, M04, M14 |
| M07 QR retiro | M01, M05, M14 |
| M08 Despacho/PWA | M01, M04, M05, M15 |
| M11 Producción | M01, M02, M04 |
| M12 Serv. técnico | M01, M02, M03, M04, M05, M15 |
| M13 Devoluciones | M01, M04, M05, M14, M15 |
| M09 Marketplaces | M01, M02, M04, M05 |
| M10 eCommerce | M01, M02, M04, M05, M08 |
| M06 POS (standby) | M01–M05 |
| M16 Reportes | todos los transaccionales |

---

## 5. Modelo de datos inicial (MySQL 5.7)

### Convenciones obligatorias

- InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`.
- Sin CTE, window functions ni JSON_TABLE. JSON solo para metadata.
- `VARCHAR(191)` para índices únicos de strings.
- Timestamps nullable estilo Laravel (`created_at`/`updated_at`). FKs explícitas con ON DELETE/UPDATE.

### Tablas del MVP por módulo

| Módulo | Tablas |
|---|---|
| M01 | usuarios, roles, permisos, role_user, permission_role, auditoria, configuracion |
| M02 | productos, listas_precios, precios |
| M03 | clientes |
| M04 | bodegas, stock, movimientos_stock |
| M05 | cotizaciones, cotizacion_items, facturas, guias_despacho |
| M07 | validaciones_retiro |
| M08 | rutas, ruta_paradas, entregas |
| M11 | produccion_botellones |
| M12 | st_recepciones, st_diagnosticos, st_garantias |
| M13 | devoluciones, devolucion_items |
| M14 | aprobaciones, reglas_aprobacion (motor polimórfico) |
| M15 | notificaciones, preferencias_canal |

Notas: `productos` lleva `bsale_id`, peso y dimensiones; `facturas` referencia el documento en Bsale; `produccion_botellones` lleva desglose primera/segunda/malos + estado pendiente/aprobado/rechazado con `aprobado_por`/`aprobado_at`.

### DDL de ejemplo validado para 5.7

```sql
CREATE DATABASE impdali_daligo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE productos (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku       VARCHAR(64)  NOT NULL,
  nombre    VARCHAR(191) NOT NULL,
  peso_kg   DECIMAL(10,3) NULL,
  alto_cm   DECIMAL(10,2) NULL,
  ancho_cm  DECIMAL(10,2) NULL,
  largo_cm  DECIMAL(10,2) NULL,
  bsale_id  BIGINT UNSIGNED NULL,
  atributos JSON NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_productos_sku (sku),
  KEY idx_productos_bsale (bsale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produccion_botellones (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id  BIGINT UNSIGNED NOT NULL,
  soplador_id  BIGINT UNSIGNED NOT NULL,
  fecha        DATE NOT NULL,
  primera      INT NOT NULL DEFAULT 0,
  segunda      INT NOT NULL DEFAULT 0,
  malos        INT NOT NULL DEFAULT 0,
  estado       ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  aprobado_por BIGINT UNSIGNED NULL, aprobado_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_prod_suc_fecha (sucursal_id, fecha),
  CONSTRAINT fk_prod_suc FOREIGN KEY (sucursal_id) REFERENCES sucursales(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Integraciones externas

Bsale API (SII, catálogo, stock — central; **detalle de endpoints/auth/límites en [`docs/BSALE_API.md`](docs/BSALE_API.md)**), WhatsApp Business API (migración pendiente), Webpay/Transbank (links de pago, NO pasarela propia), Google Maps (ruteo PWA), APIs Chilexpress/Starken/Cruz del Sur (cotización despacho), Mercado Libre/Falabella (fase posterior).

---

## 6. Plan de proyecto (carta Gantt — 9 meses, may 2026 → ene 2027, 39 semanas)

**Equipo:** 2 desarrolladores full-time (Dev A, Dev B) + PM/Arquitecto/QA compartidos.
**Piloto: MIRADOR 150** (la spec v2.0 decía Coquimbo, pero se cambió por instrucción de Mauricio: el equipo está físicamente en Mirador, feedback más fácil). El plan comprimió 15 meses → 9: el rollout completo es agresivo; **Coquimbo solo se INICIA al cierre**.

| Fase | Semanas | Contenido | Hito |
|---|---|---|---|
| F0 Discovery | W1–W8 (may–jun) | Kickoff, requerimientos, entrevistas 3 sucursales, evaluación Odoo vs alternativas vs extender Bsale, validación con Víctor (Bsale), nombre del sistema, doc. técnica, presupuesto, setup de entornos/repos/CI-CD | H1 W8: discovery aprobado + presupuesto firmado |
| F1 Transversales | W9–W22 (jul–sep) | M01 Core (Dev A, W9–12) → M02 Catálogo (Dev B, W11–13) → M03 Clientes (W13–15) → M15 Notificaciones (W15–18) → M14 Aprobaciones (Dev A, W14–17) → M16 BI iterativo (W18–26) | H2 W11 login operativo · H3 W22 transversales completos |
| F2 Núcleo operativo | W16–W32 (sep–nov) | M04 Inventario (Dev A, W16–22) → M11 Sopladores (Dev B, W19–23, ★adelantado) → M05 Ciclo factura (Dev A, W22–28) → M13 Devoluciones (Dev B, W24–27, ★adelantado) → M07 QR (W27–29) → M08 PWA conductor MVP reducido (Dev A, W27–32) → M12 Serv. técnico MVP reducido (Dev B, W29–32) | Hs W23 sopladores operativo · H4 W32 núcleo listo |
| F3 Piloto Mirador | W30–W34 (nov–dic) | Hardening (carga, seguridad, respaldos), migración de datos + carga peso/dimensiones SKU, capacitación (Pedro, Ricardo, sopladores), marcha blanca | **H5 W34: MVP EN PRODUCCIÓN EN MIRADOR 150** |
| F4 Rollout Abate | W34–W37 (dic–ene) | Configuración y migración Abate, especialidad serv. técnico + marketplaces (stretch), capacitación, go-live | H6 W37: Abate en producción |
| F5 Coquimbo + cierre | W36–W39 (ene) | Configuración Coquimbo + producción botellones (ya soplan), deuda técnica, documentación final, manuales, traspaso a soporte, retrospectiva | H7 W39: Coquimbo iniciado + proyecto cerrado |

**Prioridades explícitas de Mauricio (adelantadas):** login/registro, sopladores (M11), devoluciones (M13).
**Fuera de los 9 meses:** M06 (standby), M09 solo stretch parcial en Abate, M10 backlog post-go-live. M08 y M12 entran en versión MVP reducida.

### Riesgos del plan

- Rollout a 3 sucursales en 9 meses es agresivo (spec original: 15 meses).
- Dependencia de Bsale/Víctor: validar temprano qué se reutiliza.
- M08 (PWA conductor) es XL con modo offline — riesgo de subestimación.
- 2 devs = poca holgura ante imprevistos.
- Limpieza de ~25 bodegas virtuales puede destapar complejidad de datos.
- Faltan peso/dimensiones por SKU para migrar y cotizar despacho.

---

## 7. Pendientes por confirmar antes de Sprint 0

| Área | Decisión | Cuándo |
|---|---|---|
| Bsale | Reunión con Víctor: qué ya está cubierto en Bsale | Antes de Sprint 0 |
| Identidad | Nombre del sistema (APP DALI / Importadora DALI) | Antes de Sprint 0 |
| Hardware | Computadores nuevos para devs: costo vs tiempo | Antes de Sprint 0 |
| Alcance | CRM / sistema de zonas para vendedores: ¿entra o no? | Sprint 0 |
| Legal | Flujo tributario de boleta rápida sin datos | Antes de M05 |
| Inventario | Levantamiento de las ~25 bodegas virtuales | Antes de M04 |
| Permisos | Matriz "qué perfil ve qué" (vista cruzada stock) | Antes de M04 |
| WhatsApp | Confirmar migración a WhatsApp Business API (Marco) | Antes de M15 |
| Hardware | Cotizar impresora térmica nueva (la actual se sobrecalienta) | Antes de M09 |
| WordPress | Curso WooCommerce $200 USD vs código propio | Antes de M10 |

---

## 8. Procesos operativos actuales (levantamiento por sucursal)

Notación: prefijo de sucursal + número de proceso. **A** = Abate Molina, **M** = Mirador, **C** = Coquimbo. Estos son los flujos AS-IS que el sistema debe digitalizar.

### Personas clave (aparecen en los flujos)

| Persona | Rol |
|---|---|
| Luis Lazcano (hijo) | Sponsor del proyecto, jefe sucursal Coquimbo; aprueba descuentos >30% y retiros de alto monto |
| Héctor | Jefe de ventas (Mirador); autoriza facturas (hoy 5 validaciones manuales) |
| Ricardo | Arma rutas por sectores (Mirador); contacto stock Santiago |
| Anthony | Carga el camión en bodega Mirador |
| Pedro Cancino | Genera guías de despacho, Excel del día, recepción servicio técnico (Mirador) |
| Melissa | Cierre de caja (Mirador) |
| Scarleth | Cierra el ciclo factura por factura (Mirador) |
| Matías | RRHH; paga bono al conductor por rutas fuera de Santiago |
| Fernando | Técnico del taller (diagnóstico, cotiza de puño y letra) |
| Víctor | Contacto Bsale; también ingresa producción a Bsale |
| Gonzalo | Jefe Abate Molina; único que compra a China |
| Paulina | Ventas/oficina Coquimbo |
| Julio | Encargado de bodega Coquimbo |
| Marco | Contacto migración WhatsApp Business API |

### 8.1 Mirador (bodega central — 9 procesos)

**M-01 Asignación de stock desde Santa Rosa.** Pedido por WhatsApp a la planta de Santa Rosa → preparan tapas y manillas → retiro con guía de traslado interna → llegada a bodega → control de cantidades → confirmación u aviso de diferencias por mensaje → asignación por % de ventas a cada sucursal → distribución. Problemas: coordinación 100% WhatsApp sin trazabilidad; algoritmo de % vive fuera del sistema; las 3 bodegas virtuales de Mirador (servicio técnico, contenedores, certificaciones/reserva) se cuadran a mano. Oportunidades: orden de compra formal, recepción con confirmación digital por línea, asignación automática configurable, vista única por bodega.

**M-02 Cotización y autorización de factura.** Vendedor genera cotización (vence a 5 días) → aplica descuento si corresponde → Héctor revisa criterios de descuento → verifica pago Webpay → verifica stock asignado al vendedor → genera factura. Problemas: Héctor concentra todo (cuello de botella); validaciones manuales; stock por vendedor controlado offline; el vencimiento no genera alertas. Oportunidades: reglas de descuento en sistema, validación automática de stock, alertas de vencimiento, aprobación remota desde celular.

**M-03 Preparación de ruta y carga de camión.** Héctor pasa el lote diario a Ricardo → Ricardo arma ruta por sectores en Excel → hoja al conductor → conductor va donde Anthony en bodega → Anthony prepara y carga camión → facturas a Pedro Cancino → Pedro genera guía por factura → paquete de vuelta al conductor → sale. Problemas: hoja Excel sin orden por punto de entrega (el conductor rearma la ruta con su celular en el camino); tres pasadas de mano sin sistema único; sin trazabilidad por etapa. Oportunidades: ruta optimizada por sector/horario, guías automáticas al emitir factura, tablero de estados (asignada → cargada → en ruta), app móvil del conductor.

**M-04 Entrega en ruta y retorno.** Conductor entrega producto + factura cliente por cliente; la hoja indica forma de pago (Transbank/cheque/efectivo); marca entregas en hoja; al volver entrega la hoja a Ricardo como evidencia. Problemas: confirmación solo en papel, sin firma/hora/foto; diferencias de cobro se detectan tarde; cheques sin trazabilidad. Oportunidades: firma digital + foto desde la app, cierre por entrega en tiempo real, conciliación automática de pagos, tracking al cliente.

**M-05 Cierre administrativo del ciclo.** Hoja vuelve a Ricardo → si la ruta fue fuera de Santiago, Matías paga bono al conductor (ej. Rancagua, Curicó; sin tabla de referencia) → Pedro recibe facturas/boletas y genera el Excel del día → Melissa hace cierre de caja → Scarleth cierra el ciclo factura por factura. Problemas: Excel manual, cuatro manos por las mismas facturas, bono sin tabla, cierre manual. Oportunidades: reporte automático al cerrar rutas, bono calculado por destino/km, cierre automático al confirmar entrega + pago, trazabilidad cotización → cierre en una vista.

**M-06 Producción de botellones.** Contenedor de preforma llega de China → descarga en Mirador → ingreso manual a Bsale (bodega virtual "contenedor") → asignación a Mirador y Coquimbo (las que soplan) → ~600 botellones diarios por soplador → hoja de producción en papel con desglose 1ª/2ª/malos (ej. 580/10/10) → Víctor lo ingresa a Bsale (o Pedro si no está) → descuento de preforma del stock. Problemas: triple ingreso manual; hoja en papel; cuello en Víctor/Pedro; sin indicadores de mermas por soplador. Oportunidades: captura digital del soplador, descuento automático de preforma, indicadores %1ª/%2ª/%malos, tablero diario con metas.

**M-07 Recepción para servicio técnico.** Cliente llega con su máquina → Pedro lo recibe → toma datos (nombre, RUT, teléfono, email) → si es garantía pide boleta/factura con fecha → captura datos de la máquina (código, serie, modelo, falla) → ingresa a OneDrive → imprime folio correlativo en dos copias firmadas (cliente + taller). Problemas: OneDrive sin integración; folio en papel duplicado; falla en texto libre; sin notificaciones al cliente. Oportunidades: formulario digital firmado desde el celular del cliente, folio con QR, categorías estándar de falla, notificaciones automáticas.

**M-08 Diagnóstico y cobro técnico.** Fernando revisa la máquina → análisis y cotización **de puño y letra a Pedro** → si garantía: arreglo sin cobro; si reparación: Pedro contacta al cliente con el monto → si no aprueba, se cobra solo la hora de servicio; si aprueba, arreglo y cobro hora + repuesto → folio con detalle → pago en sala de ventas (si no es garantía). Problemas: cotización manuscrita; aprobación sin canal único; pago separado del retiro; sin historial por máquina. Oportunidades: cotización digital, aprobación por link de WhatsApp, pago integrado o anticipado por Webpay, historial técnico.

**M-09 Garantía y bodegaje post-reparación.** Máquina lista → notificar cliente → garantía 3 meses → si no retira a los 6 meses: cobro de bodegaje → al año: queda para DALI (desarme para repuestos / reventa / donación). Problemas: ningún plazo genera alarma automática; cobro manual caso a caso; máquinas olvidadas ocupan bodega; sin trazabilidad del destino final. Oportunidades: alertas a 3/6/12 meses, cobro configurado, tablero de máquinas próximas a plazo, registro del destino con foto.

> Síntesis Mirador: *"que la factura se mueva digitalmente desde la cotización del vendedor hasta el cierre del ciclo por Scarleth, sin que ningún documento físico cruce manualmente los escritorios."*

### 8.2 Coquimbo (piloto original de la spec; 6 personas, 8 procesos)

Equipo: Luis (jefe), Paulina (ventas/oficina), Julio (bodega), 2 choferes, 1 operador-chofer (también opera la sopladora), 1 chofer rotativo para rutas largas. Diferencia clave: **logística regional propia**, sin marketplaces. Venta descrita por Luis como "súper rápida".

**C-01 Abastecimiento con camión propio a Santiago.** Ricardo manda hoja de pedidos → Luis revisa lo que falta (a ojo, según lo que ve y lo que Paulina escucha) → define cantidades → confirma con Ricardo por WhatsApp → sale el camión **Niño 500** a Mirador (semana por medio, insumos de bajo peso: tapas, soportes, bombas, sellos, máquinas) → vuelve con guías de traslado. Problemas: pedido a ojo, sin punto de reorden, negociación por WhatsApp. Oportunidades: sugerencia automática por consumo histórico, punto de reorden por SKU, orden de transferencia formal con aprobación digital.

**C-02 Recepción y control de mercadería.** Julio revisa cada guía contra lo recibido → si cuadra, confirma al grupo de WhatsApp e ingresa stock; si no, reporta diferencia a Ricardo; faltantes quedan anotados para el próximo viaje. Problemas: guía en papel, conteo manual, pendientes como nota mental, si Julio falta nadie sabe el procedimiento. Oportunidades: recepción con scanner/código de barras, diferencias notificadas automáticamente, lista formal de pendientes, foto obligatoria si hay daño.

**C-03 Venta presencial y entrega en bodega.** Paulina atiende y factura → cliente baja a bodega con la factura → Julio hace pre-entrega código por código → entrega total o parcial → timbra la factura. Problemas: bodega no ve lo que se factura arriba (riesgo de doble entrega o factura adulterada); timbre depende de memoria; parciales sin estado formal. Oportunidades: **PC + impresora en bodega (aspiración explícita de Luis), pantalla tipo McDonald's con facturas en tiempo real**, estados por factura, QR de validación.

**C-04 Despacho urbano diario.** Despacho a domicilio **sin costo, diferenciador competitivo local**. Tres sectores: Coquimbo, La Serena centro, Las Compañías. Cada día se cubren 1–2 sectores (nunca 3, tramos muy largos). Problemas: decisión de sector manual, sin confirmación digital ni tracking. Oportunidades: optimizador de ruta, agrupador por sector/prioridad, firma/foto desde PWA, notificación de ventana horaria.

**C-05 Ruta semanal al norte (Atacama).** Camión grande **Ruta del Vino 500** semanal a Copiapó y Caldera. Todo facturado antes de salir; si queda espacio se cargan productos extra para venta directa en ruta (excepcional). Problemas: venta en ruta sin sistema (facturación posterior manual); "qué echar de más" a criterio; sin tracking; si un cliente cancela, la mercadería queda flotando. Oportunidades: **catálogo offline + facturación posterior en la PWA**, sugerencia de qué llevar según demanda regional, tracking, plan B de cliente alternativo.

**C-06 Ruta a los valles.** Ruta corta (1–1,5 h), sale cuando los pedidos acumulados justifican el camión grande (típico: semana por medio). Problemas: decisión "¿salimos hoy?" manual de Luis, sin SLA ni priorización, cliente no sabe cuándo llega. Oportunidades: **umbral configurable "X pedidos o Y días"**, estimación de fecha al confirmar pedido, notificación "su pedido sale el miércoles", marca de urgente.

**C-07 Producción de botellones en Coquimbo.** Un solo operador designado. 8:00 prende máquina, 15 min de calentamiento; si cambió el tipo de preforma, recalibra; produce hasta ~17:00 (pausa 13:30–14:00 salvo modo "constructivo" urgente, que no entra al sistema). Hoja de producción en papel (códigos, cantidades 1ª/2ª/malos), firmada, foto al grupo de WhatsApp; Luis la ingresa al sistema **al día siguiente** y asocia a Guía de Producción (GP). Problemas: papel, códigos a veces equivocados, foto que se pierde, desfase de hasta 24h. Oportunidades: PWA en el celular del operador, catálogo de códigos conocidos, envío en tiempo real, auditoría del recalibrado.

**C-08 Solicitud de stock entre sucursales.** Coquimbo ya puede VER stock de Mirador y Abate (gran avance según Luis), pero conseguirlo es manual: llamar/escribir a Gonzalo (Abate) o Ricardo (Stgo), negociar por WhatsApp, esperar autorización, incluir en el próximo viaje del camión propio. Gonzalo compra a China sin considerar la demanda de Coquimbo. Problemas: negociación caso a caso, respuesta lenta (el cliente se va), sin histórico de transferencias. Oportunidades: reglas configurables de solicitud, botón "solicitar transferencia" con aprobación digital, pronóstico de compra a China con demanda de las 3 sucursales, **reserva en línea que bloquea stock en la otra sucursal**.

### 8.3 Abate Molina (12 procesos — canales digitales)

Vende por canales mixtos: presencial, Mercado Libre, Falabella y web propia (WordPress 2016). ~22 facturas/día (~7 presenciales).

**A-01 Venta presencial.** El mismo vendedor cotiza, cobra y entrega (cuello de botella con alta afluencia). Factura: ingreso manual de RUT y razón social ≈ 5 min. Un solo computador; Wi-Fi inestable afecta impresoras y POS. Oportunidades: lector de RUT (tipo Sodimac), roles separados cotizador/cajero/entregador, base de clientes precargada, internet cableado en puntos críticos.

**A-02 Facturación electrónica.** Buscar cliente por RUT o crear nuevo → cargar productos → emitir → si tiene correo, envío automático (prueba con Víctor ya exitosa, con confirmación de lectura); si no, imprimir. Problemas: se imprime aunque no haga falta, carga repetitiva, sin confirmación de recepción. Oportunidades: envío automático + confirmación de lectura, eliminar impresión, QR de descarga.

**A-03 Venta por Mercado Libre.** Órdenes → imprimir etiquetas desde la plataforma → filtrar canceladas A MANO → anotar en notita → pegar en Excel maestro → ingresar a sistema de boleta → generar boleta → volver al Excel a cargar el número → pegar etiqueta → despachar. **Lunes/martes: +50 órdenes acumuladas del fin de semana.** Problemas: triple registro (notita → Excel → sistema), riesgo de imprimir canceladas. Oportunidades: integración API ML, filtrado automático de canceladas, boleta vinculada al ID, impresora térmica dedicada.

**A-04 Venta por Falabella.** Plataforma propia, precios distintos a ML por comisiones. Identificar orden → cargar productos manualmente con precio Falabella → generar boleta → subirla manualmente a Falabella → etiqueta → despacho. Reclamos sin canal directo (correo, WhatsApp). Oportunidades: lista de precios por canal, bandeja única de reclamos, subida de boleta vía API, categorización automática de tickets.

**A-05 Venta por página web (WordPress).** Orden llega con datos → retiro en tienda (coordinar fecha) o envío externo (si no especificó transportista, contactar al cliente) → cotizar transportista → boleta con productos cargados uno por uno con precio web → formulario de despacho → coordinar entrega. Problemas: WordPress 2016 desactualizado; servidor compartido con el correo (si cae, cae todo); carga uno a uno. Oportunidades: carga automática desde la orden, selector obligatorio de transportista en checkout, migración a servidor independiente, sincronización en tiempo real.

**A-06 Cotización y despacho con transportistas.** Por cada despacho: pesar/medir, entrar a la página de Chilexpress, Starken y Cruz del Sur, cotizar en cada una; si el cliente prefiere uno, usarlo aunque sea más caro; generar guía y entregar. Problemas: cotización manual triple, despachos a regiones que cuestan más que el producto, sin trazabilidad centralizada. Oportunidades: cotizador por APIs con sugerencia automática, default si el cliente no elige, tablero de despachos en curso.

**A-07 Retiro de mercadería con factura (anti-fraude).** Encargado revisa el documento → ¿figura en sistema? → ¿estado? si ya retirada: alerta de posible fraude → si ≥ $1.000.000: firma de Héctor o Luis → entrega → marcar como entregada. **Incidente real: casi se entrega un pedido millonario con factura adulterada/duplicada.** Hoy la verificación es por memoria visual. Oportunidades: QR único escaneable, estados automáticos emitida → pagada → retirada, alerta de doble retiro, aprobación remota de Luis por app.

**A-08 Recepción para servicio técnico.** Las máquinas se reciben en Abate aunque deberían ir a Mirador: ingreso manual, almacenaje en bodega ya saturada, coordinación de envío a Mirador, diagnóstico allá, notificación y retiro. Oportunidades: formulario online previo del cliente, envío directo a Mirador (validar con Gonzalo), trazabilidad en tiempo real, notificaciones automáticas.

**A-09 Reposición de productos.** Revisión visual de bodega → el vendedor avisa verbalmente → encargado consolida lista mental → propone compra a Luis → si es importado, China demora ~3 meses. **Si la persona a cargo se va de vacaciones, el sistema se rompe.** Oportunidades: stock virtual en tiempo real, alertas de punto de reorden, sugerencia por rotación histórica, reportes para Luis.

**A-10 Stock y reservas entre sucursales.** Mercadería importada se reparte por % histórico (Mirador ~75%, Abate ~25%). Cada sucursal define reservas; el conflicto: una sucursal "saca" lo reservado por otra. Oportunidades: reservas por usuario/sucursal con permisos, aprobación digital del dueño de la reserva, visibilidad cruzada, reportes de movimientos para Luis.

**A-11 Aprobación de descuentos.** ≤30%: el vendedor aplica directo. >30%: aprobación verbal de Luis; si no está, el cliente espera; sin registro de la decisión. Oportunidades: solicitud digital con notificación, aprobación remota desde celular, reportes de descuento con margen resultante, histórico por vendedor.

**A-12 Devoluciones de marketplaces.** Recepción → inspección física (muchos llegan mojados o con embalaje destruido) → si daño de transporte: reclamo al transportista; si no: evaluar con cliente → reembolso o reingreso a stock → notificar. **Una sola encargada concentra todo sin apoyo.** Oportunidades: formulario estándar que llena el cliente, fotos obligatorias, reportes por causa, reglas automáticas por tipo de daño y origen.

---

## 9. Cómo usar este documento al programar

- **Empezar por el Bloque A en el orden del Gantt:** M01 → M02/M03 → M15 → M14 → (M16 iterativo). Respetar el mapa de dependencias de la sección 4.
- **Antes de construir cualquier funcionalidad**, revisar la corrección #18: si ya está en Bsale, integrarse, no duplicar.
- **Toda decisión de esquema** debe pasar el filtro MySQL 5.7 (sección 5) y las restricciones de hosting (sección 2).
- **Los parámetros de negocio son configurables, no hardcodeados:** umbral de aprobación remota, % de distribución por sucursal, vencimiento de cotizaciones, rangos de descuento, umbrales de salida de ruta, plazos 3/6/12 meses de servicio técnico, metas de producción.
- **Trazabilidad y auditoría en todo:** el proyecto existe porque hoy nada queda registrado. Cada acción relevante necesita quién/qué/cuándo.
- **Prototipar temprano la PWA offline** (M08/M11): es el mayor riesgo técnico.
- Los flujos de la sección 8 son el AS-IS: el TO-BE es digitalizarlos según los módulos de la sección 4.


