# Anexos para el lote DECISIONES.md — recopilados por el Director 13-07
> Fuente única para que Max-2 cierre el lote sin depender de pastes. Todo verificado por el
> Director. Cada bloque se anexa a su ficha en docs/DECISIONES.md.

## D-002 · Matriz módulo×rol (propuesta de referencia del Investigador, 08-07)
> Estado de la decisión: TOMADA como estrategia (Mauricio 08-07): los accesos se definen al
> CERRAR cada módulo. Esta matriz se archiva como PROPUESTA DE REFERENCIA a consultar en cada
> cierre — no es vinculante. Celdas con * = suposición pendiente de confirmar con Luis.

Leyenda: — sin acceso · Ver · Gestionar · Aprobar.

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

Preguntas abiertas para Luis (al cerrar cada módulo): (1) M13 ¿vendedor registra pagos de su
boleta? (2) M14 ¿todos crean solicitudes o solo jefaturas/vendedor/técnico? (3) M07/M08 alcance
exacto del conductor.

## D-003 · Bodegas — RICARDO RESPONDIÓ (Excel del dueño, 13-07); Luis pendiente
Catastro real 16 bodegas. Columna Ricardo (verbatim del Excel "Bodegas Bsale.xlsx"):

| Bodega | ¿Se usa? | Aclaración de Ricardo |
|---|---|---|
| MIRADOR | SÍ | Física — central |
| COQUIMBO | SÍ | Física |
| ABATE MOLINA | SÍ | Física |
| BUZETA | SÍ | "ALMACENAMIENTO MASIVO (mercancía que no tiene mucha rotación)" |
| BODEGA SANTA ROSA | PENDIENTE | (Ricardo no aclaró — pregunta a Luis) |
| BODEGA SERVICIO TECNICO | PENDIENTE | "SERVICIO TECNICO MAQUINARIA (CARLOS TABLANTE)" |
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

Hallazgo del Director: las 2 filas de servicio técnico NO son duplicadas — son DISTINTAS
("MAQUINARIA/Carlos Tablante" vs "MÁQUINAS Y HERRAMIENTAS"). Falta de Luis: propósito/sucursal
de Santa Rosa y Reserva Sucursales, y confirmar el mapeo de las 2 de ST. Estado: MEDIA-RESPUESTA.

## D-006 · Zonas de vendedores (info de Héctor, charla 08-07) + decisión del Director
Info de Héctor: zonas de Santiago = Norte y Sur. Zona Norte incluye Los Andes y San Felipe.
Zona Sur incluye Melipilla. 6ª región = Rancagua hasta Teno. 7ª región = Curicó y Talca. Un
vendedor por zona; el jefe reemplaza en vacaciones.
Decisión del Director (registrable): las zonas entran como CATÁLOGO SIMPLE (no CRM) — tabla
`zonas` (nombre + comunas/regiones) + `zona_id` en el vendedor; la zona del cliente se deriva de
su `vendedor_id` (ya existe de M03). Suplencia = reasignación temporal. Alimenta DESPACHOS-v1
(hoja de ruta por zona) y M16 (venta por zona). Confirmar con Luis en la reunión: límites
exactos de zonas, comisiones, si la zona define la ruta de despacho.

## D-004 · Boleta rápida / flujo tributario — MELISA respondió (foto del dueño, 13-07)
> Bonus: no estaba pedido en el lote pero llegó. Anexar a la ficha D-004 (sigue ABIERTA;
> faltan Scarlett y Héctor).
Respuestas de Melisa (cajera, sala de ventas), verbatim resumido:
- Documento que entrega hoy: boleta o factura, generada por **Bsale web**.
- Medios de pago: efectivo, débito, crédito, transferencia, Webpay, cheques, depósitos; el más
  usado es **débito/crédito (tarjeta)**. Bsale registra medio de pago + N° de comprobante.
- Factura: **la hacen ellas** en Bsale (opción de registrar empresa: razón social, giro,
  dirección).
- Cierre de caja: **sí**, por Bsale (detalla todos los movimientos del día). Cuadran contra el
  informe de las máquinas **Getnet** para los pagos con tarjeta. Hay **2 cajas**; cada una
  cuadra su efectivo contra el total de Bsale al cierre.
- Lo más molesto hoy: **NO el cobro** (ese fluye) sino (a) validar pagos por transferencia/
  Webpay manualmente, y (b) cuando venden repuestos que están en OTRA sucursal (ej. servicio
  técnico) y deben pedir que los pasen a Mirador — dependen de que otros ayuden.
Lectura del Director para M05/M13: el cobro NO es el dolor → el MVP de ventas debe atacar
(1) conciliación de transferencia/Webpay y (2) el traspaso entre sucursales (cruza con M04).
Getnet como fuente de cuadre de tarjeta es dato nuevo para el diseño de caja (M13).
