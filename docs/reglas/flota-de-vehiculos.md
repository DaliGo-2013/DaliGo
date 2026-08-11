# Flota de vehículos (módulo LOGÍSTICA)

> Pedido del dueño, 04-08-2026. Reemplaza la planilla `Vehiculos 2026.xlsx`
> (hoja «Control vehiculos»). **La flota son 17 vehículos** (Mirador 13,
> Coquimbo 3, Abate Molina 1).

## 0. La planilla tiene 44 filas y la flota son 17 — cómo distinguirlas

**Esto costó un error en producción, así que va primero.** La hoja acumula
historia: vehículos vendidos, con pérdida total, de sucursales cerradas, y hasta
dos filas (`KBWC66`, `KBBC73`) que no son vehículos sino notas sobre cobros de
TAG. La flota **actual** son 17 filas.

**El nombre del conductor NO indica que el vehículo esté en la flota.** Ese fue
el error: al cargar los datos por primera vez se tomó «tiene nombre de chofer en
su columna» como «está activo», y entraron **31 vehículos en vez de 17**. Catorce
de los que sobraban tienen nombre de conductor en la hoja (Pedro Castillo, Camilo
Toro, los Lazcano…) y aun así no son de la flota de hoy: se venden o se dan de
baja sin limpiar esa columna.

**La flota actual no es deducible del archivo** — vive en el filtro con el que el
dueño mira la hoja, no en los datos. **Antes de cargar, hay que preguntarle la
lista de patentes.** Deducirla lleva a borrar en producción para arreglarlo.

Cómo se reconocen los que NO van, cuando ya se tiene la lista: las 25 filas de
más se identifican por patente, y su rastro se ve en las fechas — sus documentos
vencieron en 2024 y 2025, mientras que los 17 de la flota tienen todo en 2026,
2027 y 2028.

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
DaliGo. Damimed y Jefaturas **no son sucursales y no van a serlo**: crearlas para
poder enlazarlas las haría aparecer en Servicio Técnico, Producción y Despachos,
donde no operan. `Vehiculo::BASES` es una **lista sugerida** (`datalist` en el
formulario), no un enum: agregar una base no necesita un deploy.

Decisión del dueño, 04-08-2026 (AskUserQuestion).

### 2.2.1 Solo operan tres sucursales

Dato del dueño, 04-08-2026: **quedan Mirador, Abate Molina y Coquimbo**.
**Concepción, Antofagasta y Viña del Mar cerraron.**

| Base | Estado | Vehículos de la flota |
|---|---|---|
| Mirador | opera (central) | **13** |
| Coquimbo | opera | **3** |
| Abate Molina | opera | **1** |
| Concepción | **cerrada** | 0 — `PSJW47` pasó a Mirador |
| Antofagasta | **cerrada** | 0 |
| Viña del Mar | **cerrada** | 0 (nunca tuvo; solo figuraba en una lista de la planilla) |
| Damimed · Jefaturas | valores de la planilla, no sucursales | 0 — los vehículos que los usaban no son de la flota actual |

`Vehiculo::BASES` sugiere **solo las tres que operan**. Sacar un valor de ahí no
rompe una ficha que ya lo tenga: el campo es texto libre justamente para eso, y
por eso es una lista **sugerida** y no un enum.

**Buzeta es una BODEGA de mercadería, no una sucursal** (dato del dueño,
04-08-2026): ahí se deja mercadería y **no se dejan vehículos**. Por eso no se
sugiere como base. Está sembrada en la tabla `sucursales` (`SucursalSeeder`)
porque es una ubicación del negocio, pero no opera como sucursal.

> **Corrección de una afirmación anterior de este documento** (commit `be2b82f`,
> corregido el mismo día): se dejó anotado como «deuda abierta» que Servicio
> Técnico podía recibir un equipo en Buzeta. **Es falso** — la exclusión ya
> existía desde antes de este módulo, y en tres capas:
> `config/servicio_tecnico.php` → `sucursales_recepcion` lista solo
> `MIRADOR`/`COQUIMBO`/`ABATE-MOLINA`; el scope `Sucursal::recepcionServicioTecnico()`
> filtra por ahí y lo usan **tanto** la página de códigos QR **como** el
> formulario interno de ingreso y el lote del conductor; y el formulario público
> del QR va por **link firmado** con el `sucursal_id` embebido, así que la
> sucursal no se puede cambiar por URL. La lección: antes de declarar una deuda,
> hay que buscar el control — «no lo vi» no es «no existe».

Ojo con los **alias**: `RVBD32` se llama «HD35 CONCE» y `PSJW47` «RAM MIRADOR»,
y ninguno de los dos nombres describe su base. Son apodos de la operación y **se
conservan tal cual** — la base es el dato, el alias es cómo le dicen.

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

## 4bis. Ítems que llegaron de otros módulos

### Conductores

El catálogo de **Conductores** vive en **LOGÍSTICA** desde el 04-08-2026 (pedido
del dueño): quien administra la flota administra quién la maneja. Antes estaba en
Servicio Técnico.

Su permiso es **canAny** —`manage servicio tecnico|manage vehiculos`— y conserva
el de Servicio Técnico a propósito: el catálogo alimenta el selector del **ingreso
por lote** y el del **traslado al taller**, así que si el técnico lo perdiera, el
conductor que retira máquinas en ruta dejaría de existir para él.

### Despachos

**Despachos** (M07) vive en **LOGÍSTICA** desde el 05-08-2026 (pedido del dueño).
Antes estaba en Operación.

Va **primero** en el módulo porque es el principio del flujo: de los despachos
salen los documentos con los que se arma la **hoja de ruta**, que después se
asigna a un **vehículo** y a un **conductor** — el orden del menú es el orden en
que se usa.

A diferencia de Conductores, acá **el permiso no se tocó**: sigue siendo `manage
despachos`, el mismo que ya gateaba su ruta. El traslado es de **orden**, no de
**acceso** — nadie gana ni pierde la pantalla, y hay un candado que lo fija
(`test_el_traslado_no_le_dio_ni_le_quito_la_pantalla_a_nadie`) justamente porque
«ordenar el menú» es una puerta de entrada silenciosa a ampliar permisos.

### Las dos reglas al mover un ítem de módulo

1. **El gate de la ruta y el del ítem del menú son el mismo** (D-014). Cambiar
   uno solo deja el menú ofreciendo una pantalla que devuelve 403.
2. **No se duplica el ítem** en el módulo viejo: dos ítems con la misma ruta
   rompen el candado de `SidebarTest` (una ruta resalta exactamente un ítem). Es
   el error realista —copiar en vez de mover— y está verificado por mutación:
   duplicando el ítem caen **dos** candados a la vez.

## 4ter. Descarga en Excel

Botón **Descargar Excel** en el listado (pedido del dueño 04-08-2026): la planilla
que circula sale **al día desde la app** y no se mantiene a mano.
`App\Services\Logistica\FlotaExcel` arma el `.xlsx` sin librerías (un xlsx es un
ZIP de XMLs y `ZipArchive` viene con PHP), igual que `CartaGanttExcel`. **El
esqueleto del formato es compartido** (`App\Services\Excel\EscritorXlsx` arma el
paquete; `FilasXlsx` escribe las filas): `FlotaExcel` solo aporta sus columnas,
su contenido y su tabla de estilos. Candados propios del escritor compartido en
`tests/Unit/Excel/`.

Espeja la forma de «Control vehiculos» —identificación · dimensiones ·
documentos— y agrega las **dos columnas que la planilla a mano no puede tener**:
*Estado documental* y *Qué vence*, con el plazo en palabras.

Tres decisiones que lo hacen usable como planilla y no como volcado:

1. **Las fechas van como fechas de Excel** (serial + formato `dd-mm-yyyy`), no
   como texto: si viajaran como texto no se podría ordenar ni filtrar por
   vencimiento, que es para lo que se descarga. Candado que lo fija:
   `FlotaExcelTest::test_las_fechas_viajan_como_fechas_de_excel_y_no_como_texto`.
2. **Autofiltro + cabecera congelada**: son 25 columnas; sin eso es inmanejable.
   Así se usa la planilla original.
3. **Respeta los filtros de la pantalla** («descargar lo que estoy viendo») **y
   escribe adentro cuál se aplicó**. Sin esa línea, un Excel de 10 filas circula
   por correo como si fuera la flota completa y nadie puede saberlo mirándolo. El
   resumen del encabezado cuenta **lo que el archivo trae**, no la flota entera.

El listado y la descarga filtran por el MISMO método privado
(`VehiculoController::filtrada`). Si cada uno armara su query, la descarga
empezaría a diferir de lo que se ve — el defecto clásico de este tipo de botón.

**Colores:** acá sí se usa el semáforo rojo/ámbar/verde y no la paleta de 4 de la
app. Es un archivo que se abre FUERA de DaliGo, donde el verde de «al día» es el
idioma que la gente espera de una planilla; mismo criterio que el Excel de la
carta Gantt.

**Verificación que no se salta:** XML bien formado **no** garantiza que Excel
abra el archivo (Excel exige además las celdas en orden de columna y sin refs
repetidas, y rechaza el archivo entero sin decir por qué). Los candados cubren el
XML; la prueba final es abrirlo con Excel de verdad — se hizo vía COM y abrió sin
reparaciones, con las fechas como número y el autofiltro puesto. **Repetida el
04-08 tras extraer el escritor compartido**, y esa vez con el control que la
vuelve creíble: reordenando a mano las celdas de una fila, Excel se niega a abrir
el archivo mientras `simplexml_load_string` lo sigue dando por bueno.

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

**El rol `conductor` gana `ver vehiculos` (11-08-2026)** con el respaldo digital de
los documentos (§4quater): el módulo existe para que él muestre el permiso en un
control de ruta. Es consulta y nada más — subir sigue siendo de `manage vehiculos`.

## 4quater. Respaldo digital de los documentos (11-08-2026)

Pedido del dueño: *«que tenga un botón con la opción de cargar documentos, que se
pueda ver todas las veces que uno quiera como respaldo, que pese lo más liviano en
KB si se puede, y que sea cómodo de ver en móvil iPhone y Android para los
conductores, por si los controlan en un reparto de ruta»*.

**La escena manda el diseño:** el conductor parado en un control, con el teléfono
y la señal que haya. De ahí sale todo lo demás.

### Lo liviano lo garantiza el SERVIDOR

`CompresorDeDocumentos` recomprime **todo** lo que se suba a un JPEG de 1600 px de
lado y calidad 72 (~100-250 KB desde una foto de teléfono de 3-8 MB). Nadie tiene
que saber comprimir: el que sube saca la foto y listo.

Tres cosas que el compresor resuelve y que no son obvias:

1. **El reencode borra el EXIF**, y con él la coordenada **GPS** que el teléfono
   graba en cada foto. En un archivo que lleva la patente y se le muestra a un
   tercero, esa coordenada no puede viajar (Ley 21.719).
2. **Fondo blanco explícito** bajo transparencias: JPEG no tiene canal alfa, y sin
   esto una captura de pantalla en PNG sale con el fondo **negro** — el documento
   queda ilegible. Con candado.
3. **PDF necesita Imagick, que puede no estar en el hosting.** Si no está, el PDF
   se **rechaza diciendo qué hacer** («sacale una foto o una captura») en vez de
   guardarse tal cual: 5 MB con visor distinto en cada teléfono es exactamente lo
   que este servicio existe para evitar. Con candado.

Siempre **JPEG** y no WebP/AVIF: es lo que cualquier teléfono de los conductores
abre sin sorpresas, y a esta calidad la diferencia de peso no cambia nada.

### El archivo NO tiene URL pública

Vive en `storage/app/private/` —fuera del docroot y fuera del repo (D-012)— y se
sirve **solo** por una ruta autenticada. Sin login redirige al login; sin permiso
los bytes no salen. Va con `Cache-Control: private, max-age=86400`: lo cachea el
teléfono del conductor (la segunda vez abre al toque aunque la señal sea mala) y
ningún proxy intermedio.

### Nada se pisa

Cada subida es una fila. El **vigente** es el más nuevo por (vehículo, documento) y
lo anterior queda como historial — es lo que el dueño pidió con «como respaldo».
En la pantalla el historial va **plegado**: no es parte de la escena del control.

### La pantalla del control

Arriba, en un renglón: **documento · patente · vence dd-mm-aaaa · estado**. Después
la foto a todo el ancho, y **tocarla abre el JPEG pelado**, así el pellizco para
agrandar es el **nativo** del teléfono en vez de JS propio que pueda trabarse.
Verificado a 375 px: sin scroll horizontal.

**Gotcha del build:** el visor usa `max-h-[80vh]` —la misma clase del visor 3D— y
no un valor nuevo, justo para no obligar a reconstruir `public/build` por una
pantalla (R-33). Al escribir esta vista salió una clase nueva (`max-h-[75vh]`) que
el CSS commiteado no tenía; se cambió por la que ya existía.

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

**PERO el `.sql` no decide qué vehículos van: eso se pregunta.** La primera carga
metió 31 en vez de 17 por deducir la flota del archivo (ver §0), y hubo que
corregirlo con un `DELETE` en producción. El orden correcto es: pedir la lista de
patentes → filtrar por ella → cargar. Y el `.sql` de carga conviene entregarlo
con un `SELECT` **antes** del `DELETE`/`INSERT`, para que el dueño vea la lista de
lo que se va a tocar antes de aceptarla.

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
