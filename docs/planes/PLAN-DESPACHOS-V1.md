# PLAN-DESPACHOS-V1 · Despachos — plan fino (carve-out M05-parcial + M07 + M08-MVP)
> **Estado: VIGENTE — verificado contra el código el 2026-07-13 (commit fcb9466)**

> **Unidad:** DESPACHOS-v1 (carve-out; RUTA-MAESTRA §5 — reemplaza el arranque de M04, pospuesto por el dueño) · **Rama:** feature/despachos-v1 · **Stream:** 2
> **Objetivo:** que un pedido ya facturado en Bsale se **retire sin fraude** (QR único validado en bodega, con alerta de doble retiro) y se **entregue con prueba** (conductor confirma con firma + foto + hora, offline-first). La emisión del documento sigue 100% en Bsale.
> **Hecho cuando:** en staging — (1) un documento de venta real de Bsale aparece espejado; (2) su QR se valida en la cola de bodega y un segundo escaneo dispara ALERTA de doble retiro; (3) el conductor marca la entrega desde el celular con firma+foto+hora y sobrevive un corte de señal; con tests verdes.
> **Gate previo al código:** visto bueno de Mauricio sobre este plan ANTES de la primera migración.

## 0. Verificación de vigencia (qué se revisó del código)

| Área | Archivo verificado | Estado hoy (2026-07-13, main fcb9466) |
|---|---|---|
| Cliente HTTP Bsale | `app/Services/Bsale/BsaleClient.php` | Read-only: solo `hasToken/get/each`; auth header `access_token`; sobre `{count,limit,offset,items,next}`, 50/pág, backoff 429. SIN métodos de escritura. Reutilizable sin cambios. |
| Patrón de sync | `app/Services/Bsale/{CatalogSync,ClientSync,PriceListSync,StockSync}.php` + `app/Console/Commands/BsaleSync*.php` | 4 syncs: comando resuelve `BsaleClient` por DI → `run(): array` de stats → `Modelo::withoutAuditing(...)`. Upsert por id de Bsale; match por mapas pre-cargados (`pluck('id','bsale_*_id')`). |
| Guard delete | `CLAUDE.md` bitácora [2026-06-12]; `PriceListSync:140`, `StockSync:88`, `CatalogSync:92` | `whereNotIn('col', [])` = `1=1` → borra todo. Regla: nunca borrar si "lo visto" quedó vacío habiendo recorrido. **Documentos NO borran (inmutables) → esquivamos el footgun.** |
| Scheduler | `routes/console.php`; `tests/Feature/ScheduleBsaleTest.php` | Grilla `*/15` obligatoria (I-01): cron real `*/15 … schedule:run`, slots :00/:15/:30/:45 ocupados por las 4 syncs; `notificaciones:reintentar` cada 15. Toda tarea nueva cae EXACTO en la grilla. |
| Endpoint documentos | `docs/BSALE_API.md` §F + §Huecos | `GET documents.json?emissiondaterange=[a,b]` CONFIRMADO (docs); cabecera `number/totalAmount/netAmount/taxAmount/state/informedSii/urlPdf` + nodos `client/office/details/references`. ⚠️ shape del nodo `details` en la RESPUESTA GET es doc-only (nunca explorado); NO hay filtro modified-since. |
| Cartera/vendedor | `app/Models/Cliente.php`; migración `2026_06_10_135029` + `2026_06_16_140000` | `clientes.vendedor_id` → `users` (nullOnDelete) + `vendedor_nombre` texto libre. Sin `zona`. |
| Sucursales | `app/Models/Sucursal.php`; `config/servicio_tecnico.php` | `sucursales` (codigo unique, es_central); scope `recepcionServicioTecnico`. `users.sucursal_id` existe; **users.zona_id NO existe**. |
| Zonas / documentos locales | grep `database/migrations`, `app/Models` | **NO existe** tabla `zonas`, `zona_id`, ni espejo local de ventas/documentos/DTE. Todo se crea de cero. |
| Patrón QR | `app/Http/Controllers/Admin/ServicioTecnicoController.php:277` (`qr()`); `resources/js/app.js:373` (`dibujarQrsMostrador`); `resources/views/admin/servicio-tecnico/qr.blade.php` | `URL::signedRoute` + `canvas[data-qr]` + `import('qrcode')` (chunk, dep `qrcode@1.5.4` ya instalada) + página imprimible. Reutilizable. |
| Código único / estados | `app/Models/OrdenServicio.php:27` (`booted::creating` + `generarCodigoUnico`), `:330` (`getFolioAttribute`), `:349` (`scopePorConfirmar`), `$table` fijado | Plantilla para `Despacho` (prefijo `DSP-`, anti-colisión por reintento, `$table='despachos'` a mano). |
| PWA offline | `docs/SPIKE-PWA.md` §2/§4; `public/sw.js`; `resources/js/offline-queue.js`; `tests/Feature/PwaTest.php` | Memo VIGENTE. 5 reglas SW + cola IndexedDB idempotente por UUID + drenado en `online`/`load` (iOS sin Background Sync). §4 = checklist de adopción M08. |
| Superficie compartida | `routes/web.php`, `resources/views/layouts/navigation.blade.php`, `config/permissions.php`, `database/seeders/RolesAndPermissionsSeeder.php`, `app/Http/Controllers/Admin/AuditController.php` | M14 (rama Max-1) y M12 (Marcos, `crear lote servicio` recién en main) los tocan. Solo bloques nuevos al final de cada grupo. |

> **Regla de re-sellado** (`docs/planes/README.md`): si pasan >7 días o entran commits que toquen estas áreas, re-verificar esta tabla antes de seguir construyendo.

## 1. Diseño

### 1.1 Arquitectura (capas GUIA-DALIGO)

```
Bsale (fuente de la venta)                       DaliGo (orquesta el retiro/entrega)
────────────────────────                         ─────────────────────────────────────
GET documents.json ──sync #5 (*/15, read-only)──▶ documentos_venta + _detalles (espejo)
                                                          │  (jefe de bodega crea el despacho)
                                                          ▼
                                                   despacho (estado, zona, transportista, QR DSP-XXXX)
                                                          │
                              ┌───────────── QR firmado (URL::signedRoute) ─────────────┐
                              ▼                                                          ▼
                    BODEGA (retiro)                                          CONDUCTOR (entrega, PWA)
                    escanea QR → valida contra el                           hoja de ruta del día por zona
                    documento (estado/monto/items)                         confirma: firma + foto + hora
                    ┌─ 1er escaneo → registra retiro                       (offline → cola IndexedDB → drena)
                    └─ 2º escaneo → ⚠️ ALERTA doble retiro (bloquea)       server = fuente de verdad
                    cola "McDonald's" (polling)
```

**Contrato de servicios (capa Service, patrón GUIA-DALIGO):**
```php
// Espejo (read-only, sin escritura a Bsale — regla de la biblia)
DocumentSync::run(?int $desde = null, ?int $hasta = null): array  // {creados,actualizados,omitidos,errores}

// Dominio despacho
DespachoService::crearDesdeDocumento(DocumentoVenta $doc, array $datos): Despacho   // estado inicial
DespachoService::validarRetiro(Despacho $d, User $operador): ResultadoEscaneo       // 1er OK / 2º ALERTA (lockForUpdate)
DespachoService::confirmarEntrega(Despacho $d, array $prueba): void                 // firma+foto+hora (idempotente por uuid)
```

### 1.2 Esquema (MySQL 5.7: VARCHAR(191) en índices vía `defaultStringLength(191)`, `decimal(14,4)` montos/cantidades, estados `string(32)`, fechas Bsale epoch→datetime)

**`zonas`** (catálogo D-006; vendedor↔zona) — **IMPLEMENTADO en P-DSP-02**

| Columna | Tipo | Nota |
|---|---|---|
| id | bigIncrements | |
| nombre | string(191) | "Santiago Norte", "Santiago Sur", "6ª Región", "7ª Región" |
| descripcion | string(191) null | comunas/regiones (texto libre inicial) |
| activa | boolean default true | |
| timestamps | | |

- `users.zona_id` → nullable + FK `zonas` `nullOnDelete` (patrón `add_sucursal_id_to_users`).
- **`clientes.zona_id` → nullable + FK `zonas` `nullOnDelete` (AJUSTE OBLIGATORIO del dueño 2026-07-13):** el cliente puede tener una zona EXPLÍCITA que **sobreescribe** la heredada del vendedor. Campo LOCAL (la sync Bsale nunca lo pisa).
- **Regla de precedencia (`Cliente::zonaEfectiva()`):** (1) si `clientes.zona_id` está seteado → gana; (2) si no → hereda `vendedor->zona`; (3) si no hay vendedor/zona → null. Cubierta por `tests/Feature/Despachos/ZonaTest.php`.

**`documentos_venta`** (espejo read-only del DTE de Bsale)

| Columna | Tipo | Nota |
|---|---|---|
| id | bigIncrements | |
| bsale_document_id | unsignedBigInteger **unique** | identidad del espejo |
| folio | unsignedBigInteger index | = `number` de Bsale |
| bsale_document_type_id | unsignedBigInteger null index | tipo DTE |
| emitido_at | datetime index | epoch→datetime |
| neto / iva / total | decimal(14,4) | |
| state | string(32) | estado Bsale |
| informed_sii | tinyInteger null | 0 ok / 1 enviado / 2 rechazado |
| url_pdf / url_public | string(191) null | |
| cliente_id | foreignId null | match por `bsale_client_id` (nullOnDelete) |
| bodega_id | foreignId null | match por `bsale_office_id` (nullOnDelete) |
| timestamps | | |

**`documento_venta_detalles`**

| Columna | Tipo | Nota |
|---|---|---|
| id | bigIncrements | |
| documento_venta_id | foreignId **cascadeOnDelete** | |
| bsale_detail_id | unsignedBigInteger null index | |
| producto_id | foreignId null | match por `bsale_variant_id` (nullOnDelete) |
| descripcion | string(191) | fallback si el producto no está espejado |
| cantidad / precio_neto / descuento | decimal(14,4) | |
| — | | **unique(`documento_venta_id`, `bsale_detail_id`)** (idempotencia del upsert de detalle) |

**`despachos`** (`$table='despachos'` fijado a mano — el pluralizador inglés falla, como `ordenes_servicio`)

| Columna | Tipo | Nota |
|---|---|---|
| id | bigIncrements | |
| codigo | string(32) **unique** | `DSP-XXXXXXXX` impredecible (hook `booted::creating` + reintento) — anti-fraude, no enumerable |
| documento_venta_id | foreignId | el pedido facturado que se despacha |
| zona_id | foreignId null | de la zona del cliente (derivada) o asignada |
| estado | string(32) index | `preparado → retirado/en_ruta → entregado` (+ `entrega_parcial`) |
| transportista | string(191) null | nombre del conductor/transporte |
| conductor_id | foreignId null | `users` (nullOnDelete) — para la hoja de ruta |
| retirado_at | datetime null | 1er escaneo válido |
| entregado_at | datetime null | confirmación del conductor (hora recibida en server) |
| capturado_at | datetime null | hora del DISPOSITIVO al confirmar (offline-safe, SPIKE §4.2) |
| entrega_uuid | string(191) null index | idempotencia de la confirmación offline (patrón SPIKE §2.2) |
| firma_path / foto_path | string(191) null | storage de la prueba de entrega |
| timestamps | | |

- Índice compuesto `[estado, zona_id]` (query real: cola de bodega y hoja de ruta filtran por estado dentro de zona).

**`escaneos_despacho`** (log anti-fraude — cada lectura del QR, base de la alerta de doble retiro)

| Columna | Tipo | Nota |
|---|---|---|
| id | bigIncrements | |
| despacho_id | foreignId cascadeOnDelete | |
| user_id | foreignId null | operador de bodega que escaneó |
| resultado | string(32) | `valido` / `doble_retiro` / `estado_invalido` |
| detalle | string(191) null | motivo/observación |
| created_at | datetime index | el `updated_at` no aplica (append-only) |

### 1.3 Reglas, configuración y eventos

- **Estados** como constantes en `Despacho` (patrón `OrdenServicio::ESTADOS` + `getEstadoVarianteAttribute`). `x-despacho.estado-badge` NUEVO (copia de `produccion/estado-badge.blade.php`; NO `x-badge` directo — solo tiene 3 variantes y `info/warning/success` caen a `brand`).
- **Anti-fraude (núcleo):** `validarRetiro` corre bajo `lockForUpdate` sobre el despacho; si `retirado_at` ya está → registra escaneo `doble_retiro` + **NO** cambia estado + devuelve ALERTA. Todo escaneo (válido o no) deja fila en `escaneos_despacho`.
- **Cola de bodega "McDonald's":** polling liviano tipo `ServicioTecnicoController::porConfirmarConteo()` (endpoint JSON de conteo/lista, refresco suave sin recargar).
- **Permiso nuevo:** `manage despachos` (bodega valida QR + crea despacho) y `confirmar entrega` (conductor). Aditivos en seeder + label + gate (3 puntos, §3). Asignar `manage despachos` a `jefe_bodega`, `confirmar entrega` a `conductor`.
- **Config:** `config/despachos.php` — umbral de aprobación para despacho de alto monto (consume M14), estados. Sin secretos.
- **Eventos M15:** `despacho.retirado` / `despacho.entregado` vía `NotificacionDispatcher` (M15 ya en producción). Canal WhatsApp queda en **stub** (D-007 APLAZADA) — solo database/mail.
- **Aprobación sobre umbral:** un despacho de monto alto **solicita aprobación M14** antes de salir (integración con el motor de la rama de Max-1 — dependencia cruzada, §3).

## 2. Pasos (mapa 1:1 con RUTA-MAESTRA §5 · unidad DESPACHOS-v1)

| Paso | Alcance | Archivos nuevos/tocados | ¿Dep. M14? | Hecho cuando |
|---|---|---|---|---|
| **P-DSP-00** | Exploración read-only del shape real de `documents.json?limit=3&expand=[details,client,office]` (fija el nodo `details`) — vía `bsale:explore` extendido o GET manual. NO migración. | (evidencia a `docs/qa/INFRA/`) | no | shape documentado; recién ahí se congela la migración de P-DSP-01 |
| **P-DSP-01** | Espejo: migraciones `documentos_venta`+`_detalles`, modelos auditables, `DocumentSync` + `bsale:sync-documents` (upsert por `bsale_document_id`, **sin delete**), agenda `hourlyAt(45)` `withoutOverlapping(15)`, extender `ScheduleBsaleTest` | `database/migrations/*`, `app/Models/DocumentoVenta*.php`, `app/Services/Bsale/DocumentSync.php`, `app/Console/Commands/BsaleSyncDocuments.php`, `routes/console.php`, tests | no | `migrate:fresh --seed` verde; sync con `Http::fake` crea/actualiza sin duplicar; grilla `*/15` fijada por test |
| **P-DSP-02** | Catálogo `zonas` + `users.zona_id` + seeder de zonas (Norte/Sur/6ª/7ª de D-006); derivación zona-del-cliente | `database/migrations/*`, `app/Models/Zona.php`, `database/seeders/ZonaSeeder.php`, `app/Models/User.php` | no | seeder idempotente 2×; relación `User::zona` + `Cliente::zona` (derivada) con test |
| **P-DSP-03** | Entidad `Despacho` + `escaneos_despacho`: migraciones, modelo (código `DSP-`, estados, scopes, auditable), `DespachoService::crearDesdeDocumento`, panel admin crear/listar | `database/migrations/*`, `app/Models/Despacho.php`, `app/Models/EscaneoDespacho.php`, `app/Services/Despachos/DespachoService.php`, `app/Http/Controllers/Admin/DespachoController.php`, vistas, `x-despacho.estado-badge` | no | crear despacho desde un documento espejado; estados; 375/768/1280 sin scroll horizontal |
| **P-DSP-04** | **QR anti-fraude** (M07): QR firmado por despacho, página imprimible, escaneo en bodega → `validarRetiro` (lock + doble-retiro), cola "McDonald's" (polling), entrega total/parcial | `routes/web.php` (grupo admin + escaneo), vistas (copia de `qr.blade.php`), `app.js` (reusa `dibujarQrsMostrador`), controlador escaneo | no | 2º escaneo dispara ALERTA + fila `doble_retiro`; cola refresca sin recargar; tests del lock |
| **P-DSP-05** | **PWA conductor** (M08-MVP): hoja de ruta del día por zona (lectura offline precargada), confirmación entrega firma+foto+hora, cola IndexedDB `entregas` (FormData, foto comprimida, `capturado_at`), UI de rechazados | `resources/js/offline-queue.js` (store `entregas`), vistas PWA, endpoint idempotente `confirmarEntrega` (`entrega_uuid` + unique + `lockForUpdate` + `ValidationException` + rama `expectsJson()`), `public/sw.js` (bump CACHE si toca `offline.blade`) | no | entrega confirmada sobrevive modo avión y drena al volver señal, sin duplicar; foto ≤ tope; hora del device guardada |
| **P-DSP-06** | Integración M14: despacho sobre umbral solicita aprobación antes de salir; notifica por M15 | `config/despachos.php`, `DespachoService`, gancho a `Aprobaciones::solicitar` | **sí** | tras merge de M14 a main; solicitud→aprueba→libera el despacho con test |
| **P-DSP-07** | Gate `/pre-merge` (R-31) + merge coordinado doble llave + QA staging (correo/campanita + escaneo + entrega desde celular) | — | sí | suite verde post-merge + QA APROBADO archivado en `docs/qa/` |

**Orden:** P-DSP-00 (explora) → 01 (espejo) → 02 (zonas) → 03 (despacho) → 04 (QR/M07) → 05 (PWA/M08) → 06 (M14, tras su merge) → 07 (merge+QA). Commits pequeños por paso, **suite completa verde antes de cada commit**; `npm run build` + grep del bundle en los que toquen Blade/JS/CSS.

## 3. Integración con archivos compartidos (anti-colisión — cambios MÍNIMOS, coordinados con el Director)

| Archivo | Cambio único | Colisión |
|---|---|---|
| `routes/web.php` | Sub-grupo nuevo `permission:manage despachos` DENTRO del grupo admin (al final, antes del `require auth.php`) + rutas de escaneo junto al bloque `ingreso-taller` (throttle+signed) | ⚠️ M14 y M12 tocan este archivo — agregar al final, no reordenar |
| `resources/views/layouts/navigation.blade.php` | Un `<x-nav-link>` "Despachos" (desktop ~L81 + móvil ~L151) gated `@can('manage despachos')` | ⚠️ M12/M14 tocan nav — bloque nuevo, duplicar desktop+móvil |
| `config/permissions.php` | +2 labels: `'manage despachos'`, `'confirmar entrega'` | aditivo |
| `database/seeders/RolesAndPermissionsSeeder.php` | +2 permisos al array; `manage despachos`→jefe_bodega, `confirmar entrega`→conductor | ⚠️ Marcos acaba de agregar `crear lote servicio` — agregar al final del array y del bloque de roles |
| `app/Http/Controllers/Admin/AuditController.php` | `Despacho::class => 'Despacho'`, `DocumentoVenta::class => 'Documento de venta'` en `MODELOS` | aditivo |
| `tests/Feature/ScheduleBsaleTest.php` | +`bsale:sync-documents` a las 3 aserciones de grilla | aditivo |
| `tests/Feature/Admin/RoleMatrixSeedTest.php` | +permisos nuevos al `matrix()` esperado (admin + jefe_bodega/conductor) | ⚠️ recién tocado por `crear lote servicio` — unir |

## 4. Decisiones, riesgos y fuera de alcance

- **Riesgo #1 (alto) — shape de `details` no verificado:** todo §F de `BSALE_API.md` es doc-only; el Anexo A exploró products/variants/prices/stock/offices contra la cuenta DEMO **pero NO documentos**. Mitigación: **P-DSP-00 obligatorio** (GET real `documents.json?limit=3&expand=[details,client,office]`) ANTES de congelar la migración P-DSP-01.
- **Riesgo #2 — sin modified-since:** un documento que cambia tras emitirse (pago, rechazo→aceptación SII, anulación por nota de crédito en `references`) no se recaptura por polling de emisión. v1 acepta re-barrer una ventana móvil (ej. últimos N días) cada corrida; el fiel es webhook (fuera de v1, D-005).
- **`[B:D-006]` (zonas):** IMPLEMENTADO en P-DSP-02 (catálogo simple vendedor↔zona + override explícito `clientes.zona_id` por ajuste del dueño 2026-07-13 — "siempre hay excepciones"). Falta de Luis: límites exactos/comisiones/si la zona define la ruta — no bloquea el catálogo base.
- **`[B:D-003]` (bodega↔zona):** Ricardo respondió (16 bodegas); el mapeo bodega↔zona/sucursal afina de qué bodega sale cada despacho — no bloquea el MVP (el documento ya trae `office`).
- **Dependencia M14 (P-DSP-06/07):** el motor de aprobaciones vive en `feature/m14-aprobaciones` (Max-1, 6/7). La aprobación sobre umbral se cablea SOLO tras el merge de M14 a main — coordinar con stream 1; hasta entonces P-DSP-01..05 no dependen de M14.
- **PWA target Android** (SPIKE §5): en iOS se acepta el degradado (sin Background Sync → drenar en `online`/`load`). Los conductores operan con Android.
- **FUERA de v1:** emisión de DTE (sigue 100% en Bsale — regla de la biblia), cotizador de transportistas, venta-en-ruta/catálogo offline de venta, webhooks de Bsale, CRM de zonas.

## 5. Delegaciones a redactar (prompt listo para despachar)

- **A soporte/docs Bsale (D-005, ruta ya encaminada en `buzon/dictados/navegador.md`):** confirmar shape del nodo `details` en la respuesta GET de `documents.json`, unicidad de `number` por tipo, y semántica de `references` (nota de crédito ↔ documento). Prioridad subió por este plan. Alimenta P-DSP-00/01.
- **Ninguna de infra nueva:** el cron `*/15` ya existe (I-01); la 5ª sync entra sola al agendarse en `routes/console.php` (no requiere tocar cPanel).
