# DaliGo — Historias de usuario

> **v1 · 2026-09-08.** Derivadas de tres fuentes que ya existían: los **procesos levantados** (biblia §8), los **permisos por rol** (`RolesAndPermissionsSeeder`) y las **pantallas del menú** (`MenuPrincipal`). Cada historia dice **quién**, **qué** y **para qué**, y trae **criterios de aceptación** que son, uno a uno, el test que la protege (o el que falta).
>
> **Estado:** ✅ construida y en producción · ◐ parcial · ⚪ pendiente (con la unidad de RUTA-MAESTRA que la trae). **Se verifica en:** la clase de test que la candadea; «—» = sin candado conocido → hueco a cerrar.
>
> **Cómo se usa hacia adelante:** una feature nueva entra con su historia y sus criterios **antes** de programar (ficha R-10 del recetario, sección «Aceptación» del plan o descripción de la tarjeta). El QA del dueño consiste en marcar los criterios.

## Roles (actores)

| Actor | Rol spatie | Quién es en DALI |
|---|---|---|
| Soplador | `soplador` | operario de la sopladora, celular en planta |
| Jefe de bodega | `jefe_bodega` | asigna y aprueba producción; carga camiones; llave 3 de la ruta |
| Vendedor | `vendedor` | cartera propia; agenda terreno; simula carga |
| Jefe de ventas | `jefe_ventas` | supervisa taller y terreno; descuentos; llave 1 de la ruta; nota de crédito |
| Técnico de taller | `tecnico` | ingreso, diagnóstico, cotización y reparación en Mirador |
| Técnico industrial | `tecnico_industrial` | visitas en la planta del cliente; instalaciones |
| Conductor | `conductor` | entregas con firma y foto; lotes de ingreso en ruta |
| Jefe de logística / despacho | `jefe_logistica` | flota; arma hojas de ruta; llave 2 |
| Jefe de sucursal | `jefe_sucursal` | Coquimbo / Abate: aprueba, despacha traslados, anula DTE |
| Admin (gerencia) | `admin` | todo; única entrada a Administración |
| **Cliente** | *sin cuenta* | llega por QR o por link firmado |

---

## Soplador (M11 · Mi producción)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-11-01 | Como **soplador** quiero ver mis producciones del día ya precargadas (fecha, preforma, tipo, meta) para **solo ingresar cantidades** | · lista de producciones del día al entrar · fecha y asignación no se tipean · varias producciones por día | ✅ | `ProduccionTest` |
| HU-11-02 | Como soplador quiero registrar cada tanda **tocando** (−1/+1/+10/+100) con 1ª, 2ª, malos y dañadas, para no tipear con guantes | · objetivos táctiles ≥ 48 px · el input central es atajo, no obligatorio · totales visibles siempre | ✅ | `ProduccionTest`, `ParametrosMiProduccionTest` |
| HU-11-03 | Como soplador quiero elegir el **motivo** de defecto o diferencia con chips, con «Otro» como escape, para no escribir | · listas centralizadas en el modelo · «Otro» revela texto libre · «Otro» sin texto se rechaza | ✅ | `ProduccionTest` |
| HU-11-04 | Como soplador quiero registrar tandas **sin señal** y que se sincronicen solas **sin duplicar** | · cola local con UUID · doble envío = una tanda · 422 no reintenta y se ve; red sí reintenta | ✅ | `ProduccionTest` (idempotencia), `PwaTest` |
| HU-11-05 | Como soplador quiero **enviar** el reporte al jefe y saber si fue **aprobado o devuelto** (y por qué) | · enviar bloquea edición · devuelto vuelve editable con motivo · aviso en campanita/correo | ✅ | `ProduccionTest`, `tests/Feature/Notificaciones/*` |
| HU-11-06 | Como soplador quiero ver **mi historial** (últimos 45 días / semana) para saber cómo voy | · filtro por rango incluye el día «hasta» · métricas 1ª/2ª/merma | ✅ | `DashboardSopladorSemanaTest`, `ProduccionTest` |

## Jefe de bodega (M11 · Producción)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-11-07 | Como **jefe de bodega** quiero **asignar** producción diaria a un soplador (preforma, tipo, meta), incluso varias por día | · siempre crea una nueva; nunca pisa un reporte aprobado · preforma debe estar activa · tope anti-dedazo | ✅ | `ProduccionTest`, `ProduccionKardexTest` |
| HU-11-08 | Como jefe quiero **aprobar, devolver o ajustar** reportes y que al aprobar se genere el **kardex** una sola vez | · doble toque = un kardex · consumo desde las tandas · devolver/ajustar con lock | ✅ | `ProduccionKardexTest` |
| HU-11-09 | Como jefe quiero un **panel** con lo que requiere atención, el hoy, el periodo y los pendientes de otros días | · alerta = ítems que se pueden ver y actuar · pendientes de otros días con fecha · drill-down día/máquina/tipo/soplador | ✅ | `ProduccionTest` (panel) |
| HU-11-10 | Como jefe quiero ver el **kardex** de movimientos | · consumo, producción 1ª/2ª, merma por reporte | ✅ | `ProduccionKardexTest` |
| HU-11-11 | Como jefe quiero **OEE**, paradas con duración, moldes y recetas para medir la máquina y no solo al operario | · paradas con causa y duración · OEE del día · semáforo de preformas | ✅ (F1 PLAN-M11-FINAL) | `ProduccionOeeTest`, `ProduccionMoldeTest`, `ProduccionCorteSicTest` |
| HU-11-12 | Como jefe quiero **eliminar** una producción vacía en borrador para deshacer una asignación equivocada | · solo borrador y sin tandas | ✅ | `ProduccionTest` |

## Cliente sin cuenta (QR y links firmados)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-12-01 | Como **cliente** quiero **ingresar mi equipo** al taller desde el QR del mostrador, con mis datos y la falla, sin crear cuenta | · link firmado con la sucursal · garantía pide documento y fecha; reparación no · RUT con K válido · honeypot y throttle | ✅ | `IngresoTallerPublicoTest`, `IngresoTallerCoherenciaFormulariosTest`, `RutConKTest` |
| HU-12-02 | Como cliente quiero recibir **correo de ingreso** con el plazo de entrega según la sucursal | · plazo por sucursal (10 días Mirador) · garantía sin precios | ✅ | `CorreosAlClienteSinAccesoTest`, `PlazoSinFechaPrometidaTest` |
| HU-12-03 | Como cliente quiero **ver la cotización** por link y **aceptar o rechazar** | · link firmado, no enumerable · sin valores UF internos · respuesta avisa a taller y ventas | ✅ | `CotizacionPublicoTest` |
| HU-17-01 | Como cliente quiero **pedir una visita técnica** industrial con fecha preferida, solo en días que se atiende | · sin fecha → «por coordinar» y aviso a ventas · L–V y feriados cerrados · no elige el tipo (lo fija el servidor) | ✅ | `VisitaIndustrialTest`, `DisponibilidadVisitaTest`, `HorarioVisitaTest` |
| HU-17-02 | Como cliente quiero **confirmar, reprogramar o anular** la visita por link con token | · un token por cita · motivo al rechazar · vendedor avisado | ✅ | `ConfirmacionVisitaTest` |
| HU-13-01 | Como cliente quiero **registrar una devolución** con fotos desde un link | · fotos obligatorias · throttle propio · éxito por link firmado | ✅ | `tests/Feature/Devoluciones/*` |
| HU-08-05 | Como cliente o conductor quiero **ver el plan de carga** compartido por link | · solo lectura · «lo que quepa» se lee como frase, no como 0 | ✅ | `tests/Feature/Carga/*` |

## Técnico de taller (M12)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-12-04 | Como **técnico** quiero **confirmar la recepción** de lo que llegó por QR | · un solo correo aunque toquen dos veces · queda `confirmada_at` | ✅ | `ServicioTecnicoManagementTest` |
| HU-12-05 | Como técnico quiero llenar el **parte**: causa de la falla, trabajo, repuestos, mano de obra por catálogo de horas | · mano de obra = horas × valor hora, nunca tipeada · valor histórico fuera de lista muestra «Sin determinar» · presupuesto guarda en UNA acción | ✅ | `CotizacionGuardarTest`, `ServicioTecnicoManagementTest` |
| HU-12-06 | Como técnico quiero **enviar la cotización** al cliente, y que se bloquee si el catálogo no puede calcular la mano de obra | · bloquea por «no se puede calcular», no por $0 · 0 h fijadas por jefatura sí se envía · garantía sin precios | ✅ | `CotizacionEnviarTest`, `CotizacionVistaPreviaTest` |
| HU-12-07 | Como técnico quiero **ingresar por lote** varias máquinas (y el conductor desde la ruta) | · permiso acotado `crear lote servicio` · link público firmado para el lote | ✅ | `IngresoTallerLotePublicoTest` |
| HU-12-08 | Como técnico quiero **recibir traslados** de máquinas que la sucursal despachó a la matriz | · dos llaves: despacha la sucursal, recibe el taller · nadie cierra ambas | ✅ | `TrasladoServicioTest` |
| HU-12-09 | Como técnico o jefe quiero el **informe del taller** con Excel de datos (tabla plana, fechas como fecha) | · sin top-15 en el archivo · hoja de repuestos con contexto del padre · notas de qué no tiene el dato | ✅ | `InformeServicioTecnicoExcelTest`, `ServicioTecnicoInformeTest` |
| HU-12-10 | Como técnico quiero ver **solo las órdenes de mi dominio** (taller vs. industrial) | · informe dispensadores ≠ informe industrial por permiso | ✅ | `ServicioTecnicoVisibilidadTest` |
| HU-12-11 | Como taller quiero **alertas a 3/6/12 meses** (fin garantía, bodegaje, pasa a DALI) | · aviso automático por plazo · tablero de próximas a plazo | ⚪ E9 (M12 resto) | — |

## Vendedor y jefe de ventas (M03 · M12 · M17 · M14 · simulador)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-03-01 | Como **vendedor** quiero gestionar **mi cartera** de clientes (espejo + propios) | · regla #2: gestión por vendedor · búsqueda por RUT | ✅ | `tests/Feature/Admin/*Cliente*` |
| HU-12-12 | Como vendedor quiero ver el estado de las máquinas **de mi cartera** en el taller, solo lectura | · `view` sin `ver todo` = solo mi cartera (+ equipo si jefatura) | ✅ | `ServicioTecnicoVisibilidadTest` |
| HU-12-13 | Como vendedor quiero **autorizar la reparación** (coordinar el pago) sin editar el taller | · permiso `autorizar reparacion`; el técnico no lo tiene | ✅ | `CotizacionAutorizarTest` |
| HU-12-14 | Como **jefe de ventas** quiero **aplicar descuento** en la cotización (decisión comercial) | · solo jefe_ventas/admin · el técnico no ve el selector · garantía no muestra precios | ✅ | `CotizacionGuardarTest` |
| HU-17-03 | Como vendedor quiero **agendar y reprogramar** visitas en un calendario mensual con franjas de 2 h y técnico por defecto | · franja ocupada bloquea · trabajos multi-día · cierres de agenda respetados | ✅ | `AgendaTerrenoTest`, `CierresAgendaTest` |
| HU-17-04 | Como vendedor quiero **«Confirmar y avisar al cliente»** con un botón que mande el correo con fecha, franja, técnico y garantías | · un solo correo · recuadro de garantías recuerda plazos | ✅ (#23, 2026-09-05) | `AgendaTerrenoTest`, `GarantiasIndustrialTest` |
| HU-17-05 | Como jefe de ventas quiero **gestionar el tarifario en UF** y los **cierres de agenda** (feriados, vacaciones) | · editar tarifario separado de agendar · cierre apaga el formulario público, no el interno | ✅ | `TarifarioTerrenoVistaTest`, `CierresAgendaPantallaTest` |
| HU-17-06 | Como vendedor quiero el **historial de terreno del cliente** al agendar | · visitas previas y repuestos | ✅ | `HistorialClienteTerrenoTest` |
| HU-SIM-01 | Como vendedor quiero **simular la carga** de un camión: elegir camión y productos, pedir «lo que quepa», ver el plano y bajar el Excel | · orden de líneas = orden del usuario · «lo que quepa» = celda vacía en Excel, frase en pantalla · respuesta grande sin scroll | ✅ | `tests/Feature/Carga/*` (20 clases) |
| HU-SIM-02 | Como vendedor quiero **cubicar** bultos a medida y mezclarlos con el catálogo para despachos especiales | · medir largo/ancho/alto · sumar un producto del catálogo desde Cubicar | ✅ | `tests/Feature/Carga/*` |
| HU-14-01 | Como **jefe** quiero una **bandeja de aprobaciones** para resolver lo pendiente de mi rol desde el celular, con escalamiento si no respondo | · solo el rol aprobador resuelve · historial con motivo · escalamiento por cron | ✅ | `tests/Feature/Aprobaciones/*` |

## Técnico industrial (M17)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-17-07 | Como **técnico industrial** quiero ver **mi agenda** y marcar cada trabajo **realizado / no realizado** con los repuestos usados | · cierre avisa a ventas y al vendedor del cliente · repuestos por visita | ✅ | `CierreTrabajoTerrenoTest`, `RepuestosTerrenoTest` |
| HU-17-08 | Como técnico industrial quiero **recibir aviso** cuando me agendan, reagendan o cancelan un trabajo | · tres eventos · sin técnico asignado → a todos los industriales · al reasignar, también al anterior | ✅ | `AvisosAlTecnicoTerrenoTest` |
| HU-17-09 | Como técnico industrial quiero llevar **mi registro de instalaciones** (mi planilla) y bajarlo a Excel | · solo yo lo gestiono (dueño, 02-09) | ✅ | `InstalacionManagementTest`, `InstalacionesExcelTest` |
| HU-17-10 | Como técnico industrial quiero **ver el tarifario** para responder precios en la planta del cliente | · solo lectura | ✅ | `TarifarioTerrenoVistaTest` |

## Conductor (M08 · M12 · M18)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-08-01 | Como **conductor** quiero ver **mis entregas** del día y **confirmarlas con firma y foto** desde el celular | · firma + foto + hora · entrega parcial posible | ✅ | `tests/Feature/Despachos/*` |
| HU-08-02 | Como conductor quiero **mostrar los documentos del vehículo** (permiso, SOAP) desde el teléfono si me controlan | · `ver vehiculos` solo lectura | ✅ | `VehiculoDocumentosTest` |
| HU-12-15 | Como conductor quiero **ingresar un lote** de máquinas a reparar recogidas en ruta | · permiso acotado; no edita el taller | ✅ | `IngresoTallerLotePublicoTest` |

## Logística y bodega (M08 · M18 · M04)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-08-03 | Como **jefe de despacho** quiero **armar la hoja de ruta** eligiendo documentos, y que salga con **tres llaves** (pagos → ruta → carga) | · cada llave es un permiso distinto · nadie da dos · orden secuencial | ✅ | `HojaRutaTest`, `HojaRutaHttpTest`, `HojaRutaScopingTest`, `HojaRutaServiceTest` |
| HU-08-04 | Como jefe de bodega quiero **crear despachos y validar retiros** con QR | · estados preparado → retirado → en ruta → entregado / parcial | ✅ | `tests/Feature/Despachos/*` |
| HU-18-01 | Como **jefe de logística** quiero la **flota**: vehículos, documentos con vencimiento, avisos automáticos y respaldos en foto | · aviso por hito (30 días, vencido) · una persona por sucursal carga respaldos sin gestionar la flota | ✅ | `VehiculoTest`, `VehiculoAvisoVencimientoTest`, `VehiculoCargarRespaldosPermisoTest` |
| HU-04-01 | Como jefe de bodega quiero **ver el stock por bodega** (espejo de Bsale) y las bodegas paramétricas | · espejo de solo lectura · baja de bodegas con wizard | ✅ (M04 40 %) | `tests/Feature/Bsale/*`, `TrasladoBodegaExcelTest` |
| HU-04-02 | Como vendedor quiero **reservar stock** y **solicitar transferencias** entre sucursales con aprobación | · reserva con dueño y vencimiento · transferencia consume M14 | ⚪ E4 | — |
| HU-04-03 | Como jefe de bodega quiero **punto de reorden** por SKU con sugerencia por rotación | — | ⚪ E3/E4 | — |

## Devoluciones (M13)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-13-02 | Como **encargada** quiero **recibir, categorizar y resolver** devoluciones; el reembolso pasa por aprobación | · daño transporte / fábrica / otro · reingreso a stock si está bien · informe por causa | ✅ (85 %) | `tests/Feature/Devoluciones/*`, `DevolucionInformeTest` |

## Administración y transversales (M01 · M15 · M16 · MSG · PLAN)

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-01-01 | Como **admin** quiero gestionar **usuarios, roles y permisos** desde la UI sin deploy | · seeder aditivo no pisa la UI · Administración solo admin | ✅ | `tests/Feature/Admin/*`, `MenuPermisoRutaTest` |
| HU-01-02 | Como admin quiero el **registro de auditoría** (quién, qué, cuándo) | · modelos del negocio auditados · cambio de rol como audit | ✅ | `tests/Feature/Admin/*Audit*` |
| HU-01-03 | Como admin quiero **configurar** plantillas, audiencias de avisos y parámetros del negocio sin programador | · one-shots encadenadas · tests de parámetros por módulo | ✅ | `Parametros*Test`, `ConfiguracionSeedLongitudTest` |
| HU-01-04 | Como usuario quiero que un **403/404** me explique y me devuelva al Inicio, y que un 500 tenga código de incidente | · sin bucles de `back()` · páginas sin dependencias · consumidores de máquina reciben el status crudo | ✅ | `ManejoErroresTest`, `ErroresServidorTest` |
| HU-15-01 | Como usuario quiero **avisos** por campanita y correo según **mi preferencia**, y que el dueño decida las audiencias | · una fila por canal · reintento cada 15 min · preferencias solo Luis + TI | ✅ | `tests/Feature/Notificaciones/*` |
| HU-16-01 | Como usuario quiero un **Inicio** con el pulso del día y **accesos** que puedo personalizar | · excepciones arriba · squircles con color opt-in | ✅ | `DashboardTest`, `DashboardColoresTest` |
| HU-MSG-01 | Como usuario quiero **chat interno** con todos | · permiso propio apagable por rol | ✅ | `tests/Feature/Mensajes/*` |
| HU-PLAN-01 | Como gerencia quiero ver el **plan del proyecto** (Gantt, tracker, hitos) y bajarlo a Excel | · Excel válido para Excel de verdad | ✅ | `PlanProyectoTest`, `PlanCartaGanttExcelTest` |
| HU-PWA-01 | Como usuario de celular quiero **instalar** la app y que el login no se rompa sin señal | · scope `/` · fallback offline solo en `catch` | ✅ | `PwaTest` |

## Facturación (M05) — el ciclo que falta

| ID | Historia | Criterios de aceptación | Estado | Se verifica en |
|---|---|---|---|---|
| HU-05-01 | Como **quien atiende** quiero **emitir boleta o factura** desde una orden de servicio vía Bsale | · boleta/factura la elige la persona · una línea por repuesto + mano de obra · desde la sucursal que repara · pago registrado al emitir · doble clic = un folio | ◐ diseñado · 0 emitidos | `tests/Feature/Dte/*`, `EmisionDteTest`, `CandadoDeEmisionTest` |
| HU-05-02 | Como jefe quiero **anular con nota de crédito** un documento emitido | · solo gerente, jefe de ventas, jefes de sucursal | ⚪ E5 (shape no verificado) | — |
| HU-05-03 | Como taller quiero **guía de despacho** por garantía (traslado que no es venta) | · plazo 1-nov-2026 | ⚪ E5 | — |
| HU-05-04 | Como vendedor quiero **cotizar con vencimiento** y que el sistema **valide el stock asignado** antes de emitir | — | ⚪ E5 | — |
| HU-07-01 | Como bodega quiero **escanear el QR** del documento al retiro y que alerte si ya fue retirado | · alerta de doble retiro · aprobación remota sobre umbral | ◐ (escaneo existe en despachos) | `tests/Feature/Despachos/*` |

---

## Lectura rápida

- **58 historias**: 47 ✅ · 4 ◐ · 7 ⚪. Las siete pendientes están todas en el **ciclo de la factura** (M04, M05, M07) y en las alertas de plazo del taller — exactamente lo que RUTA-MAESTRA §0 marca como lo que bloquea el go-live.
- **Huecos de candado** (historias ✅ con «—» o cobertura genérica): ninguna hoy sin test conocido; las genéricas (`tests/Feature/Admin/*`) merecen nombrar la clase exacta cuando se cierre la matriz de trazabilidad.
- **Lo que este documento agrega** frente a la biblia: la biblia describe procesos y módulos; acá cada capacidad tiene **actor, valor y criterio verificable** — el formato con el que se acepta trabajo nuevo.
