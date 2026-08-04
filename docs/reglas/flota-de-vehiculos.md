# Flota de vehículos (módulo LOGÍSTICA)

> Pedido del dueño, 04-08-2026. Reemplaza la planilla `Vehiculos 2026.xlsx`
> (hoja «Control vehiculos»): **42 vehículos** — 31 activos, 9 vendidos y 2 con
> pérdida total. La planilla tiene 44 filas: dos (`KBWC66`, `KBBC73`) no son
> vehículos sino notas sobre cobros de TAG, y no se cargan.

## 1. Qué problema resuelve

La planilla funciona como inventario, pero tiene tres defectos que ningún
esfuerzo de disciplina arregla:

1. **El estado del vehículo vive en la columna del chofer.** «CONDUCTOR
   ASIGNADO» dice a veces el nombre del conductor (`PEDRO CASTILLO`) y a veces
   qué pasó con el vehículo (`PERDIDA TOTAL`, `VENTA FEBRERO 2023`, `NO
   ASIGNADO`). Con eso no se puede contar la flota ni filtrar por estado.
2. **El semáforo es manual.** El rojo y el amarillo de las celdas los pinta una
   persona. Un vencimiento que nadie pintó no existe.
3. **No avisa.** Hay que abrir el archivo para saber que el SOAP vence el
   martes.

El módulo separa lo primero, calcula lo segundo y empuja lo tercero.

## 2. Modelo

Una tabla, `vehiculos` (`App\Models\Vehiculo`), con cuatro grupos:

| Grupo | Campos |
|---|---|
| Identificación | `ppu`, `alias`, `marca`, `modelo`, `anio`, `tipo`, `combustible`, `vin`, `numero_motor` |
| Dimensiones | `cilindrada`, `pbv_kg`, `capacidad_carga_kg`, `presion_psi` |
| Asignación | `base`, `conductor_nombre` |
| Estado | `estado`, `baja_at`, `baja_motivo` |
| Documentos | `rt_vence`, `emisiones_vence`, `permiso_circulacion_vence`, `soap_vence`, `extintor_vence`, `extintor_capacidad_kg` |

### 2.1 `ppu` se normaliza siempre

Se guarda en **mayúsculas y sin espacios**. En la planilla convivían `TJGW-15` y
`TJGW15`; sin normalizar, el `unique` no sirve y entran dos filas del mismo
camión. La búsqueda del listado encuentra la patente escrita de las dos formas.

### 2.2 `base` es texto, NO una FK a `sucursales`

La planilla usa 7 valores en su columna «SUCURSAL» y solo 3 son sucursales de
DaliGo (Mirador, Coquimbo, Abate Molina). Las otras —Concepción, Damimed,
Jefaturas, Antofagasta— **no son sucursales y no van a serlo**: crearlas para
poder enlazarlas las haría aparecer en Servicio Técnico, Producción y Despachos,
donde no operan. `Vehiculo::BASES` es una **lista sugerida** (`datalist` en el
formulario), no un enum: agregar una base no necesita un deploy.

Decisión del dueño, 04-08-2026 (AskUserQuestion).

### 2.3 El estado va aparte del conductor

`estado` ∈ `activo` · `vendido` · `baja`. **Sacar un vehículo de la flota exige
`baja_motivo`** (validación, no convención): el motivo es el dato que después
nadie recuerda. Volver a `activo` limpia `baja_motivo` y `baja_at`, para que un
vehículo reincorporado no arrastre para siempre un «Venta febrero 2023».

Un vehículo que no está activo **no tiene estado documental**: un permiso de
circulación vencido en un camión vendido en 2022 no es problema de nadie y no
puede aparecer en el semáforo de la flota.

## 3. Semáforo de documentos

`Vehiculo::documentos()` es la **fuente única**: la usan el listado, la ficha, el
formulario y el comando de avisos. Cada documento resuelve a un estado:

| Estado | Cuándo | Color |
|---|---|---|
| `vencido` | la fecha ya pasó | **rojo** (`danger`) |
| `por_vencer` | faltan ≤ 30 días (`Vehiculo::DIAS_AVISO`) | **naranjo de marca** (`brand`) |
| `sin_registro` | no hay fecha cargada | neutro |
| `al_dia` | falta más de 30 días | neutro |
| `no_aplica` | el documento no le corresponde | neutro |

El estado del **vehículo** es el peor de sus documentos, con la prioridad
`vencido > por_vencer > sin_registro > al_dia`. **`sin_registro` va antes que
`al_dia` a propósito:** con cuatro documentos vigentes y el SOAP sin fecha, decir
«Al día» es exactamente la mentira que la planilla permite (una celda vacía se
lee como si estuviera bien). La etiqueta es **«Sin fecha»**, no «Sin
documentos»: el papel puede existir en la carpeta, lo que falta es el dato.

### 3.1 Los colores del Excel no se copian

La planilla usa rojo / amarillo / verde. La app tiene una **paleta estricta de 4**
(ver CLAUDE.md → Reglas de diseño) donde el rojo es solo para lo negativo y no
existe el verde. La traducción es: **vencido → rojo, por vencer → naranjo de
marca, al día → gris**. El significado lo lleva el relleno y el peso, no un
color nuevo. Candado: `VehiculoTest::test_los_colores_respetan_la_paleta_de_la_app`.

### 3.2 `no_aplica` es una regla, no un dato

El semirremolque **no rinde certificado de emisiones** (no tiene motor). En la
planilla eso está escrito a mano como `NO APLICA` en la celda. Vive en
`Vehiculo::documentoAplica()`: modelado como regla, nadie lo persigue como dato
faltante. Si aparece otra excepción de este tipo, va ahí.

## 4. Aviso automático

Comando `vehiculos:avisar-vencimientos`, agendado **todos los días a las 08:00**
(`routes/console.php`). El minuto `:00` está en la grilla `*/15` obligatoria de
HostGator (I-01); un `dailyAt('08:05')` no correría jamás.

Dos eventos de notificación (M15):

- `vehiculo.documento_por_vencer` — faltan ≤ 30 días.
- `vehiculo.documento_vencido` — ya venció.

**Destinatarios: quien tenga `ver vehiculos` o `manage vehiculos`.** Sigue al
permiso y no a un rol cableado: el día que exista el perfil de **cobranzas**
(pendiente, pedido del dueño) recibe los avisos con solo darle `ver vehiculos`
desde Administración → Roles.

### 4.1 Un aviso por vehículo, no por documento

RT y emisiones casi siempre vencen el mismo día, y permiso de circulación con
SOAP también. Avisar por documento dejaría cuatro notificaciones del mismo camión
la misma mañana, así que el aviso se **agrupa por vehículo y por hito** y lista
los documentos dentro. La bitácora `vehiculo_avisos`, en cambio, registra uno por
documento: si mañana se renueva solo uno, los otros siguen sabiendo que ya se
avisaron.

### 4.2 No se repite (y vuelve a avisar al renovar)

La clave única de `vehiculo_avisos` es `(vehiculo_id, documento, hito, vence)`.
**`vence` está en la clave a propósito:** al renovar el documento la fecha cambia
y el próximo vencimiento vuelve a ser avisable. Sin eso, renovar un SOAP dejaría
al vehículo mudo para siempre.

### 4.3 La deuda histórica no inunda la campanita

El hito `vencido` solo avisa lo que venció en los **últimos 30 días**. La
planilla trae revisiones técnicas vencidas hace años: avisarlas todas habría
disparado un centenar de notificaciones en la primera corrida y enterrado justo
lo que vence esta semana. Esa deuda **se ve en rojo en el listado**, permanente y
sin caducar, que es su lugar.

### 4.4 Sin destinatarios no se marca nada

Si nadie tiene el permiso todavía, el comando **no registra** los avisos. Marcarlos
como enviados perdería la novedad para siempre y el día que exista el perfil
nadie se enteraría. Mismo criterio que el traslado de máquinas con las cuentas de
sucursal que aún no existen.

## 5. Permisos

| Permiso | Quién |
|---|---|
| `ver vehiculos` | consultar la flota y los vencimientos |
| `manage vehiculos` | crear, editar y dar de baja |

Los tiene el **gerente** (rol `admin`) y el **jefe de logística** (rol
`jefe_logistica`, creado con el módulo). **Están separados a propósito:**
consultar los vencimientos es lo que mañana necesita cobranzas —paga permisos de
circulación y SOAP— sin poder mover una fecha.

**Pendiente (04-08-2026):** el perfil de **cobranzas** no existe todavía. No se
creó un rol vacío; cuando exista, recibe `ver vehiculos` desde la UI de Roles.

## 6. Fuera de alcance (decidido, no olvidado)

La planilla tiene tres bloques a medio usar que **no** se trajeron (decisión del
dueño, 04-08):

- **Recorrido en kms por semana** — las columnas están con `#REF!`; sería empezar
  de cero.
- **Mantenciones (taller / status / pago)** — bloque suelto en las filas 67-83 de
  la hoja, sin relación con las filas de vehículos.
- **TAG** — dos filas (`KBWC66`, `KBBC73`) que no son vehículos, sino notas sobre
  cobros de autopista. No se cargan.

## 6bis. Carga de la flota real

**Los datos de la flota NO están en el repositorio y no pueden estar:** el repo es
público y la planilla lleva patentes, VIN, números de motor y **nombres de
conductores** — dato personal bajo la Ley 21.719 (en vigor 1-dic-2026). Tampoco
va un seeder con esos datos.

La carga se hace igual que las otras cargas masivas de DaliGo: un `.sql` generado
en local y ejecutado por **phpMyAdmin** (cPanel → base `impdali_daligo` → pestaña
SQL). El archivo usa `INSERT IGNORE`, así que **correrlo dos veces es seguro**: no
duplica patentes ni pisa ediciones hechas desde la app.

Al traducir la planilla, el `.sql` aplica las reglas de este documento: separa el
estado del conductor (`PERDIDA TOTAL` → baja, `VENTA FEBRERO 2023` → vendido con
su motivo y su fecha al día 1 del mes declarado), descarta `NO ASIGNADO` y
`MIRADOR` como nombres de chofer (el segundo es la sucursal escrita en la columna
equivocada), convierte los seriales de fecha de Excel, y mueve a
`observaciones` las notas que vivían dentro de las celdas (`SIN EXTINTOR`,
indemnizaciones, fechas de entrega).

## 7. Gotchas encontrados construyendo esto

1. **Comparar la fecha del negocio con una columna `date` pierde un día.**
   `FechaNegocio::ahora()` vive en hora de Chile (−04) y una columna `date` se
   hidrata en la timezone de la app (UTC). Restar esos dos instantes deja 20
   horas de resto y el `(int)` las convierte en un día MENOS: un documento a 31
   días daba 30 y entraba en la franja de aviso. **Se comparan fechas parseadas
   desde `'Y-m-d'`**, nunca instantes de zonas distintas.
2. **Un cast `date` escribe `'Y-m-d H:i:s'`.** Un `firstOrCreate` que busca
   `'2026-08-09'` no encuentra la fila que él mismo escribió como
   `'2026-08-09 00:00:00'` y revienta contra el unique. Se pasa un **Carbon a
   medianoche**, así lectura y escritura usan el mismo formato.
