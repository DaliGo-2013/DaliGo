# PLAN-M13 · Devoluciones — plan fino de la unidad E6
> **Estado: PROPUESTA (sin código) — sellado contra el código el 2026-07-30 (main @ `b57c672`)**

> **Unidad:** E6 · M13 Devoluciones (`docs/RUTA-MAESTRA.md` §E6) · **Rama:** `feature/m13-devoluciones` · **Stream:** 1 (Max-1)
> **Objetivo:** que una devolución deje de vivir en la cabeza de una sola persona — el cliente la declara desde su celular con evidencia, bodega la recibe y la categoriza, y el reembolso pasa por el motor de aprobaciones en vez de por un acuerdo verbal.
> **Hecho cuando:** flujo A-12 completo en staging desde el link del cliente hasta el cierre (reembolso aprobado **o** movimiento de reingreso registrado), con tests verdes y límites de upload verificados.
> **Gate previo al código:** **visto bueno de Mauricio sobre este plan ANTES de la primera migración** (misma regla que M14).

> **📌 Tres decisiones de alcance ya selladas por el dueño (2026-07-30), incorporadas abajo:**
> 1. El reingreso a stock se construye como **kardex local propio** (patrón M11), no se pospone.
> 2. Las fotos obligatorias van en **los dos momentos**: cliente y bodega.
> 3. La **vinculación al transportista entra**, en su forma mínima (dato, no flujo de reclamo).

---

## 0. Verificación de vigencia (qué se revisó del código)

| Área | Archivo verificado | Estado hoy (2026-07-30, main `b57c672`) |
|---|---|---|
| Código de M13 | `git grep -i devoluc -- app/ database/` | **NO existe ninguna entidad de M13**: cero tablas, modelos, rutas y permisos. Los 5 hits son de OTROS sentidos y confirman la colisión de vocabulario: `ProduccionController.php:642` y `MiProduccionController.php:329` (el `devolver` de M11), `DteController.php:147` (glosa de guía de despacho) y `BsaleEmisor.php:93,119` (**Bsale llama «devolución» a la nota de crédito**). Se crea todo de cero. |
| Motor de aprobaciones | `app/Services/Aprobaciones/Aprobaciones.php:51` | `solicitar()` es el único punto de entrada. **Si la regla no matchea, aplica el efecto INLINE en la misma request** (`:89-100`) — el llamador nunca aplica. Solo las pendientes notifican (`:103-106`). |
| Umbral monetario | `database/seeders/ConfiguracionSeeder.php:18` · `docs/planes/PLAN-M14.md:164` | `umbral_aprobacion_clp` = `1000000`, ya sembrado y **reservado textualmente «para las reglas monetarias (M04/M05/M07/M13)»**. M13 no crea clave nueva: siembra su *regla* apuntando a esta. |
| Registro de un tipo de acción | `app/Models/Aprobacion.php:40-50` · `Aprobaciones.php:36-43` · `ReglasAprobacionSeeder.php:21` | Tres puntos, los tres comentados «los consumidores futuros (M04/M05/M07/**M13**) agregan aquí». Falta el handler ⇒ excepción en tiempo de request (`Aprobaciones.php:60-64`). |
| Contrato del handler | `app/Services/Aprobaciones/AccionAprobable.php:7-23` | `aplicar()` corre DENTRO de la transacción: debe tomar **su propio `lockForUpdate`** y re-validar `datos['objetivo_updated_at']`. Referencia: `Acciones/AjusteReporteProduccion.php:17-50`. |
| Motor de notificaciones | `app/Models/Notificacion.php:39-63` | `EVENTOS` es fuente única; un evento no registrado **lanza**. Comentario: «los módulos consumidores (M14/M12/**M13**) agregan aquí sus eventos». |
| Plantillas nuevas | `database/seeders/ConfiguracionSeeder.php:108-110` | «Clave nueva → el `firstOrCreate` del seeder la crea en el deploy; **no requiere migración**». La one-shot solo hace falta al EDITAR un texto ya entregado. |
| Enlace de la campanita | `app/Models/Notificacion.php:114-135` y `:174-193` | Hay que tocar **las dos**: `urlDestino()` da el destino y `urlDestinoPara()` lo suprime si el usuario no puede entrar (`default => false`). |
| Ruta pública endurecida | `routes/web.php:491-508` | La variante **nueva** (cotización / confirmación-visita) firma **GET y POST**. La vieja del QR dejó el POST sin firmar y `RUTA-MAESTRA.md:276` (`P-F3-01`) ya lista esa deuda **nombrando a M13**. |
| Anti-enumeración | `app/Models/OrdenServicioCotizacion.php:132-136`, `:227` | `getRouteKeyName() => 'token'` con `Str::random(64)`: el id nunca viaja en el link. |
| Honeypot | `app/Http/Controllers/Publico/IngresoTallerPublicoController.php:252-258` | Campo `sitio_web`; si viene lleno responde **idéntico al éxito** para no dar pistas. |
| Fotos | `IngresoTallerPublicoController.php:296-302` · `app/Support/ImagenComprimida.php:10-61` | **Nunca la regla `image`** (revienta con HEIC de iPhone): `mimetypes:` + `max:8192` + `size:N`. `ImagenComprimida::guardar()` re-encoda con GD (**sanea** el archivo), nombre `Str::random(40)`, disco **privado** `local`. |
| Compresión en el navegador | `resources/js/app.js:943-990` | `optimizarFotoInput()` achica a 1600px/JPEG 0.8 **antes** de subir. No es un lujo: una foto de 12MP consume ~104 MB en GD y produce un 500 (bitácora 2026-07-10). |
| Límites de upload | `public/.user.ini` | `upload_max_filesize=12M`, `post_max_size=30M`, `memory_limit=256M`. **`max_file_uploads` NO está fijado** ⇒ default de PHP: 20 archivos. Falta confirmar que LiteSpeed los honre (§5). |
| Espejo de clientes | `app/Models/Cliente.php:11-16`, `:91-100` | Espejo de Bsale **con enriquecimiento local**. `es_de_bsale` decide si se puede actualizar contacto: la sync horaria pisa lo de Bsale. |
| Espejo de ventas | `app/Models/DocumentoVenta.php:11-21` | **Existe** y trae `folio`, `cliente_id`, `total`. Su propio docblock advierte: solo es fresco ~1 día, y **todo consumidor que ACTÚE sobre un documento debe re-verificarlo contra Bsale**. |
| Kardex local (precedente) | `HANDOFF.md:376` | `produccion_movimientos` **«NUNCA toca `stocks`/`bodegas`»**; es la verdad local, lista para empujar a Bsale en una fase futura. Es el patrón que M13 copia. |
| Bloqueo real del stock | `docs/DECISIONES.md:137-144` | **D-005**, no D-003, es la que define si se escribe stock en Bsale — y dice textual: «Mientras tanto: M05-F1, **M13**, diseño de M07 **no dependen**». |

> **Regla de re-sellado** (`docs/planes/README.md`): si pasan >7 días o entran commits que toquen estas áreas, re-verificar esta tabla antes de seguir construyendo.

---

## 1. Diseño

### 1.1 Arquitectura (capas GUIA-DALIGO)

```
CLIENTE (celular, sin login)                    BODEGA / VENTAS (con sesión)
        │                                                │
  link firmado                                           │
  (GET y POST firmados ─ variante cotización,             │
   NO la del QR que dejó el POST abierto)                 │
        │                                                │
        ▼                                                │
  ┌──────────────────────────┐                           │
  │ solicitada               │  fotos del CLIENTE         │
  │ folio DEV-… + token(64)  │  (evidencia de lo que      │
  │ ip + user_agent          │   reclama)                 │
  └──────────┬───────────────┘                           │
             │  aviso M15 (try/catch: un aviso que        │
             │  falle NO tumba el envío del cliente)      │
             ▼                                            ▼
        ┌────────────────────────────────────────────────────┐
        │ recibida   ← bodega confirma + fotos de BODEGA      │
        │              (evidencia del estado REAL al llegar)  │
        └──────────┬─────────────────────────────────────────┘
                   │  categorización: transporte │ fábrica │ otro
                   ▼
        ┌────────────────────────────────────────────────────┐
        │ evaluada   → si transporte: transportista + N° seg. │
        └──────┬──────────────────────────────┬──────────────┘
               │                              │
   monto ≥ umbral → M14                  producto apto
               │                              │
               ▼                              ▼
     ┌───────────────────┐          ┌─────────────────────┐
     │ reembolsada       │          │ reingresada         │
     │ (M14 aplica el    │          │ movimiento en       │
     │  efecto; M13 NO   │          │ devolucion_         │
     │  emite nota de    │          │ movimientos         │
     │  crédito — M05)   │          │ (JAMÁS toca stocks) │
     └───────────────────┘          └─────────────────────┘
                    └──────── rechazada ────────┘
```

Decisiones estructurales:

- **El reingreso se registra en un kardex LOCAL propio, no en `stocks`.** Es el patrón que M11 ya sancionó (`produccion_movimientos`) y lo habilita D-005, que dice explícitamente que M13 no depende de ella. *Descartado:* escribir `stocks` directamente (corrompe el espejo de Bsale, que es read-only por construcción) y *descartado:* posponer el reingreso entero (dejaba E6 sin poder cerrarse y era la mitad del valor del módulo).
- **Fotos en dos momentos, con `origen` en la fila.** La foto del cliente prueba lo que reclama; la de bodega prueba en qué estado llegó. Para un reclamo al transportista **hacen falta las dos** — con una sola no se puede distinguir daño de origen de daño en tránsito. *Descartado:* una sola tabla sin `origen` (obligaría a inferir el momento por `created_at`, frágil).
- **GET y POST firmados.** `P-F3-01` ya arrastra «revisión de seguridad de rutas públicas (M12/M13)» como deuda; este módulo nace sin agregarle. *Descartado:* copiar la variante del QR (POST sin firma) por simetría con M12.
- **`documento_venta_id` al espejo + `folio_referencia` de respaldo.** El espejo de ventas ya existe, así que cuando el folio matchea se enlaza el documento real; cuando no (venta vieja fuera de la ventana de sync, u orden de marketplace sin DTE) queda el texto. *Descartado:* solo texto libre (desperdicia un espejo que ya está) y *descartado:* exigir el documento (dejaría fuera justo el caso de marketplace, que es el principal).
- **Vocabulario propio.** M11 ya usa `devolver`/`devuelto` para «el jefe devuelve el reporte al soplador» y Bsale llama `returns` a las notas de crédito. M13 usa `Devolucion`, `registrar()`, `recibir()`, `resolver()` — nunca `devolver`.

Contrato del servicio (`app/Services/Devoluciones/`):

```php
class Devoluciones
{
    public function registrar(array $datos, array $fotos): Devolucion;   // público, del cliente
    public function recibir(Devolucion $d, User $u, array $fotos): void; // bodega
    public function resolver(Devolucion $d, User $u, string $salida): Devolucion; // reembolso|reingreso|rechazo
}
```

### 1.2 Esquema (MySQL 5.7: `VARCHAR(191)` en índices vía `defaultStringLength(191)`, estados `string(32)`, `decimal(14,4)` montos, TEXT + cast para JSON)

**`devoluciones`** (auditable — es el registro que hoy no existe y del que se va a discutir):

| Columna | Tipo | Nota |
|---|---|---|
| id | id | |
| folio | string(32) unique | `DEV-00001`. Lo que el cliente muestra y bodega busca. |
| token | string(64) unique | route key del link público; el id nunca viaja. |
| estado | string(32) default `solicitada` | `solicitada \| recibida \| evaluada \| reembolsada \| reingresada \| rechazada` |
| canal | string(32) | `mercado_libre \| falabella \| wordpress \| mostrador \| otro`. **A mano: M09 no existe.** |
| causa | string(32) nullable | `transporte \| fabrica \| otro`. Null hasta la evaluación. |
| cliente_id | FK nullable → clientes | enlace blando al espejo M03. |
| cliente_rut / cliente_nombre / cliente_email / cliente_telefono | string | **denormalizados** (idioma de la casa: `OrdenServicio`, `AgendaTrabajo`, `Instalacion`). |
| documento_venta_id | FK nullable → documentos_venta | cuando el folio matchea el espejo. |
| folio_referencia | string(64) nullable | respaldo cuando no matchea. |
| transportista | string(64) nullable | solo si `causa = transporte`. |
| seguimiento | string(64) nullable | N° de seguimiento del transportista. |
| conductor_id | FK nullable → conductores | si el traslado fue propio. |
| monto_reembolso | decimal(14,4) nullable | magnitud que va a M14. |
| motivo | text | lo que escribe el cliente. |
| resolucion_motivo | text nullable | lo que escribe quien cierra. |
| sucursal_id | FK → sucursales | |
| recibida_at / recibida_por | datetime / FK nullable → users | |
| resuelta_at / resuelta_por | datetime / FK nullable → users | |
| ip / user_agent | string(45) / string(255) | del envío público (mismo rastro que la respuesta a cotización). |
| timestamps | | |

Índices compuestos por query real: `(estado, created_at)` → la bandeja de bodega ordenada por antigüedad · `(canal, causa)` → los reportes de P-M13-04 · `cliente_rut` → búsqueda desde el mostrador.

**`devolucion_items`**: `devolucion_id` FK cascade · `producto_id` FK nullable → productos (espejo M02) · `descripcion` string(191) (si no matchea un producto) · `cantidad` int · `estado_producto` string(32) (`apto | dañado | incompleto`) — es lo que decide si la línea puede reingresar.

**`devolucion_fotos`**: `devolucion_id` FK cascade · `ruta` string (relativa al disco privado) · **`origen` string(32)** (`cliente | bodega`) · timestamps. Mínima a propósito, como `orden_servicio_fotos`.

**`devolucion_movimientos`** (el kardex local): `devolucion_id` FK · `devolucion_item_id` FK nullable · `producto_id` FK nullable · `cantidad` decimal(14,4) · `tipo` string(32) (`reingreso | merma`) · `bodega_destino` string(64) nullable · `observacion` string(191) · timestamps.
> **JAMÁS escribe `stocks` ni `bodegas`** (espejo de Bsale). Es la verdad local, lista para empujar cuando exista M04 — mismo contrato que `produccion_movimientos`.

### 1.3 Reglas, configuración y eventos

- **Claves nuevas en `ConfiguracionSeeder`** (grupo `devoluciones`): `devolucion_bodega_reingreso` (a qué bodega entra lo apto — **queda parametrizable justamente porque D-003 está abierta**) y `devolucion_fotos_min` (default `2`, mismo estándar que el ingreso QR). Ojo `descripcion` ≤ 191 (candado `ConfiguracionSeedLongitudTest`).
- **Umbral de reembolso:** NO se crea clave; la regla apunta a `umbral_aprobacion_clp`, ya sembrada y reservada para esto.
- **Regla M14** en `ReglasAprobacionSeeder`: `tipo_accion = devoluciones.reembolso`, `umbral_config = umbral_aprobacion_clp`, `rol_aprobador = jefe_ventas`, `activa = true`.
- **Eventos M15** (3): `devolucion.solicitada` (a bodega+ventas), `devolucion.recibida` (al solicitante interno), `devolucion.resuelta` (**al cliente**, canal mail por destinatario externo). Plantillas nuevas en el seeder → **sin migración one-shot**. Todo placeholder con default `'—'`.
- **Permisos nuevos** (2): `view devoluciones`, `manage devoluciones`. La mitad de aprobación **no necesita permiso nuevo**: `aprobar solicitudes` ya lo tienen `jefe_ventas`, `jefe_bodega`, `jefe_sucursal` y `admin`.

---

## 2. Pasos (mapa 1:1 con `docs/RUTA-MAESTRA.md` §E6)

> Commits chicos con suite verde; los `[x]` se marcan **SOLO** en RUTA-MAESTRA (regla de estado único del `README` de esta carpeta).

| Paso | Alcance | Archivos nuevos/tocados | Hecho cuando |
|---|---|---|---|
| **P-M13-01** | Formulario público del cliente: link firmado (GET+POST), honeypot, token de 64, fotos del cliente, pantalla «gracias» con el folio. Migraciones de las 4 tablas. | `database/migrations/*_create_devoluciones*`, `app/Models/Devolucion*.php`, `app/Http/Controllers/Publico/DevolucionPublicoController.php`, `resources/views/publico/devolucion/{create,gracias}.blade.php`, `routes/web.php` (+1 grupo) | Un cliente envía desde el celular con 2 fotos y recibe su folio; sin firma → 403; honeypot → respuesta idéntica al éxito. |
| **P-M13-02** | Recepción en bodega (**fotos de bodega**) + categorización transporte/fábrica/otro + transportista y N° de seguimiento cuando aplica + reglas automáticas por tipo y origen. | `app/Http/Controllers/Admin/DevolucionController.php`, `app/Services/Devoluciones/Devoluciones.php`, vistas `admin/devoluciones/*`, `MenuPrincipal.php` (+1 línea) | Bodega recibe, sube sus fotos y categoriza; la causa `transporte` exige transportista. |
| **P-M13-03** | Reembolso vía M14 (**siempre pasando `monto`**) + reingreso como movimiento del kardex local. | `Aprobacion.php`, `Aprobaciones.php`, `Acciones/ReembolsoDevolucion.php`, `ReglasAprobacionSeeder.php` | Bajo el umbral se auto-aprueba y aplica; sobre el umbral queda pendiente y **la devolución no cambia** hasta que la aprueben. Un producto apto genera su movimiento y **`stocks` no se toca** (candado). |
| **P-M13-04** | Reportes por causa y canal + QA staging desde celular. | — | **FUERA de este lote** (orden del dictado): segundo lote. |

---

## 3. Integración con archivos compartidos (anti-colisión — cambios MÍNIMOS)

| Archivo | Cambio único |
|---|---|
| `routes/web.php` | +1 grupo admin y +1 bloque público **al final**, dentro del grupo `throttle` (ver §4: se propone throttle propio). |
| `database/seeders/RolesAndPermissionsSeeder.php` | +2 strings al array (aditivo; `givePermissionTo`, nunca `syncPermissions`). |
| `config/permissions.php` | +2 labels y **+1 grupo `'Devoluciones' => ['devoluciones']`**, o los permisos caen en «Generales». |
| `database/seeders/ConfiguracionSeeder.php` | +2 claves de negocio y +3 plantillas de notificación. |
| `app/Models/Aprobacion.php` · `app/Services/Aprobaciones/Aprobaciones.php` | +1 constante, +1 entrada en `TIPOS_ACCION`, +1 par en `HANDLERS`, +1 rama en `describirObjeto()`. |
| `app/Models/Notificacion.php` | +3 eventos, +1 rama en `urlDestino()` **y** en `urlDestinoPara()`. |
| `app/Support/MenuPrincipal.php` | +1 ítem bajo `operacion`. Lo validan solos `MenuPermisoRutaTest` y `SidebarTest` (gate P-NAV-05). |
| `resources/views/aprobaciones/index.blade.php` | +1 rama en el bloque de diff, que hoy está cableado a los campos de producción. |

---

## 4. Decisiones, riesgos y fuera de alcance

- **El «Hecho cuando» de E6 hay que enmendarlo.** Dice «hasta el **reingreso a stock**». Con el kardex local se cumple en términos **locales**, pero no hay escritura real en Bsale y no la habrá hasta M04/D-005. **Propuesta a aprobar en el visto bueno:** redactarlo «…hasta el cierre de la devolución (reembolso aprobado o movimiento de reingreso registrado en el kardex local)».
- **`throttle:6,1` es compartido por las 15 rutas públicas y la firma del limitador no incluye la ruta**: un GET→POST→GET ya gasta 3 de 6. Con fotos y un reintento el cliente se queda fuera con un 429. **Propuesta:** throttle propio (`throttle:12,1`) para el grupo de M13.
- **`post_max_size = 30M` es el techo del request COMPLETO**, y `max_file_uploads` no está fijado (default 20). Pasarse produce un **419 con el mensaje falso «Tu sesión expiró»** (trampa documentada y sin arreglar). Por eso `devolucion_fotos_min` es configurable y la compresión en el navegador es obligatoria, no opcional.
- **[B:D-003]** A qué bodega reingresa lo apto no está definido (existen `BODEGA MERMAS` y `CONTENEDORES` sin clasificar) → sale por configuración y se ajusta cuando cierre D-003. **No bloquea** este lote.
- **M09 no existe.** La biblia describe A-12 como «devoluciones **de marketplaces**» y ese canal no tiene integración: el `canal` se captura a mano. Es el hueco más grande entre lo que el módulo promete y lo que puede hacer solo.
- **M05 no emite.** M13 registra la **decisión** de reembolso; **no emite nota de crédito**. Además Bsale llama «devoluciones» (`returns.json`) a las notas de crédito, con `type 0` (devuelve stock) vs `type 1` (solo anula, que es el cableado hoy). **Territorio activo de Marcos — no se toca.**
- **Trampa de numeración, para quien venga después:** en la matriz de D-002 y en las notas de D-004 (`docs/DECISIONES.md:241`, `:132`) **«M13» significa caja/tesorería**, no Devoluciones — esa tabla desplaza varios números. Quien busque ahí los permisos de este módulo va a leer la fila equivocada.
- **Fuera de alcance de E6 en este lote:** P-M13-04 (reportes), el flujo de reclamo al transportista (solo se guarda el dato), y cualquier escritura de stock en Bsale.

---

## 5. Delegaciones a redactar

**IA-cPanel — verificación de límites de upload** (lo exige el «Hecho cuando» de E6; plantilla `docs/delegacion/plantillas/VERIFICACION-CPANEL.md`, evidencia a `docs/qa/INFRA/`).

Qué pedir, exacto: valores **efectivos** de `upload_max_filesize`, `post_max_size`, `max_file_uploads` y `memory_limit` para el dominio (`phpinfo()` o `php -i`), para confirmar que LiteSpeed **honra** el `public/.user.ini` commiteado y no lo pisa una config de vhost. Es de solo lectura y no toca nada.

Por qué importa antes de fijar el validador: si el techo real es menor que lo que valida la app, un envío con varias fotos **se pierde en silencio** — PHP descarta el body entero (incluido el `_token`) y el cliente lee «Tu sesión expiró», que es mentira.
