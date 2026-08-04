<?php

use App\Http\Controllers\Admin\AgendaTrabajoController;
use App\Http\Controllers\Admin\ConductorController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DespachoController;
use App\Http\Controllers\Admin\InstalacionController;
use App\Http\Controllers\Admin\ListaPrecioController;
use App\Http\Controllers\Admin\LoteServicioController;
use App\Http\Controllers\Admin\MaquinaController;
use App\Http\Controllers\Admin\NotificacionController;
use App\Http\Controllers\Admin\ProduccionController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServicioTecnicoController;
use App\Http\Controllers\Admin\ServicioTerrenoController;
use App\Http\Controllers\Admin\SucursalController;
use App\Http\Controllers\Admin\TipoBotellonController;
use App\Http\Controllers\Admin\TrasladoServicioController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehiculoController;
use App\Http\Controllers\AprobacionController;
use App\Http\Controllers\DashboardColoresController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificacionPreferenciaController;
use App\Http\Controllers\NotificacionUsuarioController;
use App\Http\Controllers\PlanProyectoController;
use App\Http\Controllers\Produccion\MiProduccionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Publico\CotizacionPublicoController;
use App\Http\Controllers\Publico\IngresoTallerPublicoController;
use App\Http\Controllers\Publico\VisitaConfirmacionController;
use App\Http\Controllers\Publico\VisitaIndustrialPublicoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Preferencias de notificación del usuario (M15): opt-out por evento×canal.
    // Solo Luis + administradores TI las gestionan (pedido del jefe): gate por
    // permiso; el resto del perfil sigue abierto a cualquier autenticado.
    Route::put('/perfil/notificaciones', [NotificacionPreferenciaController::class, 'update'])
        ->middleware('permission:gestionar notificaciones')
        ->name('perfil.notificaciones.update');

    // Color de las cards de accesos del Inicio (M16, D-013): preferencia
    // personal, guardada por fetch desde el modo "Personalizar" del dashboard.
    Route::patch('/dashboard/colores', DashboardColoresController::class)->name('dashboard.colores.update');

    // Campanita (M15): bandeja personal in-app; cualquier usuario gestiona LO SUYO.
    Route::get('/notificaciones', [NotificacionUsuarioController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/leer-todas', [NotificacionUsuarioController::class, 'leerTodas'])->name('notificaciones.leer-todas');
    Route::post('/notificaciones/{notificacion}/leer', [NotificacionUsuarioController::class, 'leer'])->name('notificaciones.leer');

    // Mis solicitudes de aprobacion (M14): el solicitante ve LO SUYO (patron
    // /notificaciones). Literal ANTES del grupo {aprobacion} de abajo.
    Route::get('/aprobaciones/mias', [AprobacionController::class, 'mias'])->name('aprobaciones.mias');

    // Plan del proyecto (carta Gantt transicional): el plan oficial se LEE de
    // docs/RUTA-MAESTRA.md (push a main = deploy = pagina al dia); solo los
    // "trabajos extras en paralelo" se editan aqui, con su permiso propio.
    Route::get('/plan', [PlanProyectoController::class, 'index'])
        ->middleware('permission:ver plan proyecto')->name('plan.index');
    // Descarga de la carta Gantt como Excel: se GENERA en el momento desde la
    // misma fuente que la pagina, asi el archivo que circula en las reuniones
    // sale siempre al dia (pedido del dueño 03-08).
    Route::get('/plan/excel', [PlanProyectoController::class, 'excel'])
        ->middleware('permission:ver plan proyecto')->name('plan.excel');
    Route::post('/plan/extras', [PlanProyectoController::class, 'extraStore'])
        ->middleware('permission:gestionar plan proyecto')->name('plan.extras.store');
    Route::patch('/plan/extras/{extra}', [PlanProyectoController::class, 'extraUpdate'])
        ->middleware('permission:gestionar plan proyecto')->name('plan.extras.update');
    Route::delete('/plan/extras/{extra}', [PlanProyectoController::class, 'extraDestroy'])
        ->middleware('permission:gestionar plan proyecto')->name('plan.extras.destroy');
});

// Bandeja movil del aprobador (M14): pendientes del rol vigente, resolver
// desde el celular. Permiso propio; ademas el servicio exige portar el
// rol_aprobador de la solicitud (o admin) — defensa en profundidad.
Route::middleware(['auth', 'permission:aprobar solicitudes'])
    ->prefix('aprobaciones')
    ->name('aprobaciones.')
    ->group(function () {
        Route::get('/', [AprobacionController::class, 'index'])->name('index');
        Route::post('{aprobacion}/aprobar', [AprobacionController::class, 'aprobar'])->name('aprobar');
        Route::post('{aprobacion}/rechazar', [AprobacionController::class, 'rechazar'])->name('rechazar');
    });

// Administracion: cada ruta declara su permiso especifico (granular).
Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Cuentas de usuario.
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:view users')->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])
            ->middleware('permission:create users')->name('users.create');
        Route::post('users', [UserController::class, 'store'])
            ->middleware('permission:create users')->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:edit users')->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])
            ->middleware('permission:edit users')->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:delete users')->name('users.destroy');

        // Roles y permisos.
        Route::resource('roles', RoleController::class)
            ->middleware('permission:manage roles')
            ->except(['show']);

        // Sucursales / bodegas.
        Route::resource('sucursales', SucursalController::class)
            ->parameters(['sucursales' => 'sucursal'])
            ->middleware('permission:manage sucursales')
            ->except(['show']);

        // Configuracion global: parametros tipados (solo ver y editar).
        Route::resource('configuracion', ConfiguracionController::class)
            ->only(['index', 'edit', 'update'])
            ->middleware('permission:manage settings');

        // Auditoria: historial de cambios (solo lectura).
        Route::get('audits', [AuditController::class, 'index'])
            ->middleware('permission:view audit')->name('audits.index');

        // Aprobaciones (M14): historial completo del motor (solo lectura, admin).
        // La bandeja del aprobador vive en /aprobaciones (permiso 'aprobar solicitudes').
        Route::get('aprobaciones', [AprobacionController::class, 'historial'])
            ->middleware('permission:view aprobaciones')->name('aprobaciones.index');

        // Notificaciones (M15): panel de todas las notificaciones + envio de prueba.
        Route::get('notificaciones', [NotificacionController::class, 'index'])
            ->middleware('permission:view notificaciones')->name('notificaciones.index');
        Route::post('notificaciones/prueba', [NotificacionController::class, 'prueba'])
            ->middleware('permission:view notificaciones')->name('notificaciones.prueba');

        // Catalogo de productos (nivel SKU) + import/export CSV.
        // Las rutas literales van ANTES del resource para no chocar con productos/{producto}.
        Route::middleware('permission:manage productos')->group(function () {
            Route::get('productos/importar', [ProductoController::class, 'importForm'])->name('productos.import.form');
            Route::post('productos/importar', [ProductoController::class, 'import'])->name('productos.import');
            Route::get('productos/exportar', [ProductoController::class, 'export'])->name('productos.export');
            Route::get('productos/plantilla', [ProductoController::class, 'template'])->name('productos.template');
            Route::get('productos/plantilla-medidas', [ProductoController::class, 'plantillaMedidas'])->name('productos.plantilla.medidas');
            // Asignacion masiva de categoria INTERNA (propia de DaliGo; no toca Bsale).
            Route::post('productos/clasificacion-interna', [ProductoController::class, 'clasificacionInterna'])->name('productos.clasificacion-interna');
        });
        Route::resource('productos', ProductoController::class)
            ->middleware('permission:manage productos')
            ->except(['show']);

        // Listas de precios (M02.2): espejo de Bsale, solo lectura de valores;
        // lo unico editable es el campo local `canal`.
        Route::resource('listas-precios', ListaPrecioController::class)
            ->parameters(['listas-precios' => 'listaPrecio'])
            ->middleware('permission:manage productos')
            ->only(['index', 'show', 'update']);

        // Inventario (M04): bodegas + stock espejados desde Bsale, solo lectura.
        Route::resource('bodegas', BodegaController::class)
            ->middleware('permission:manage productos')
            ->only(['index', 'show']);

        // Clientes (M03): ficha local espejada desde Bsale + cartera por vendedor.
        Route::resource('clientes', ClienteController::class)
            ->middleware('permission:manage clientes')
            ->except(['show']);

        // Servicio Tecnico (taller). Lectura (listado + detalle) para los roles
        // internos que solo consultan el estado; la gestion (crear/editar/borrar
        // + etapa de taller) queda solo para tecnico/admin. whereNumber en {orden}
        // evita que `show` choque con las rutas literales (buscar-*, create).
        Route::middleware('permission:view servicio tecnico|manage servicio tecnico')->group(function () {
            Route::get('servicio-tecnico', [ServicioTecnicoController::class, 'index'])
                ->name('servicio-tecnico.index');
            // BOCETO interno: vista de seguimiento (estilo Blue Express) del estado
            // de un equipo. Sin conexion a datos; solo un adelanto del diseño.
            Route::get('servicio-tecnico/seguimiento-demo', [ServicioTecnicoController::class, 'seguimientoDemo'])
                ->name('servicio-tecnico.seguimiento-demo');
            // Foto de recepcion (disco privado, servida con sesion). ANTES del show
            // {orden} literalmente "foto/..." son 2 segmentos, no chocan con {orden}.
            Route::get('servicio-tecnico/foto/{foto}', [ServicioTecnicoController::class, 'foto'])
                ->whereNumber('foto')->name('servicio-tecnico.foto');
            // Comprobante de pago de una cotización (disco privado, con sesión):
            // lo ve todo el equipo con acceso al ST (transparencia del pago).
            Route::get('servicio-tecnico/cotizacion/{cotizacion}/comprobante', [ServicioTecnicoController::class, 'comprobanteCotizacion'])
                ->name('servicio-tecnico.cotizacion.comprobante');
            Route::get('servicio-tecnico/{orden}', [ServicioTecnicoController::class, 'show'])
                ->whereNumber('orden')->name('servicio-tecnico.show');
        });

        // Informes de Servicio Técnico con permiso POR DOMINIO: el técnico de
        // taller ve solo Dispensadores; el técnico industrial solo Industrial;
        // jefes/admin ambos. El landing entra si tiene al menos uno (y redirige
        // al único informe si solo tiene ese).
        Route::middleware('permission:ver informe dispensadores|ver informe industrial')->group(function () {
            Route::get('servicio-tecnico/informe', [ServicioTecnicoController::class, 'informes'])
                ->name('servicio-tecnico.informe');
        });
        Route::middleware('permission:ver informe dispensadores')->group(function () {
            Route::get('servicio-tecnico/informe/dispensadores', [ServicioTecnicoController::class, 'informeDispensadores'])
                ->name('servicio-tecnico.informe.dispensadores');
        });
        Route::middleware('permission:ver informe industrial')->group(function () {
            Route::get('servicio-tecnico/informe/industrial', [ServicioTecnicoController::class, 'informeIndustrial'])
                ->name('servicio-tecnico.informe.industrial');
        });

        // Autorizar la reparación tras coordinar el pago: vendedor/jefe_ventas/
        // tecnico/admin (permiso propio; NO exige 'manage', que es solo del taller).
        Route::middleware('permission:autorizar reparacion')->group(function () {
            Route::post('servicio-tecnico/{orden}/cotizacion/autorizar', [ServicioTecnicoController::class, 'autorizarReparacion'])
                ->whereNumber('orden')->name('servicio-tecnico.cotizacion.autorizar');
        });

        // Confirmar la recepcion de lo que llego por QR: lo AUTORIZA el jefe de
        // bodega (revisa que los datos esten bien) o el tecnico. Setea
        // confirmada_at + manda el correo al cliente. Permiso propio, separado de
        // 'manage' (el jefe de bodega no ingresa/edita, solo autoriza).
        Route::middleware('permission:confirmar servicio tecnico')->group(function () {
            Route::post('servicio-tecnico/{orden}/confirmar', [ServicioTecnicoController::class, 'confirmar'])
                ->whereNumber('orden')->name('servicio-tecnico.confirmar');
            // Conteo liviano (JSON) de "por confirmar" para el aviso suave del listado
            // (poll sin recargar la pagina).
            Route::get('servicio-tecnico/por-confirmar/conteo', [ServicioTecnicoController::class, 'porConfirmarConteo'])
                ->name('servicio-tecnico.por-confirmar.conteo');
        });

        // Agenda de terreno (tecnico industrial): quien VE la agenda (tecnico
        // industrial) puede ademas marcar el estado de un trabajo; AGENDAR y
        // editar el catalogo de servicios queda para jefe/vendedores.
        Route::middleware('permission:ver agenda terreno|agendar servicio terreno')->group(function () {
            Route::get('agenda-terreno', [AgendaTrabajoController::class, 'index'])
                ->name('agenda-terreno.index');
            Route::get('agenda-terreno/calendario', [AgendaTrabajoController::class, 'calendario'])
                ->name('agenda-terreno.calendario');
            Route::patch('agenda-terreno/{trabajo}/estado', [AgendaTrabajoController::class, 'estado'])
                ->whereNumber('trabajo')->name('agenda-terreno.estado');
        });
        Route::middleware('permission:agendar servicio terreno')->group(function () {
            Route::get('agenda-terreno/crear', [AgendaTrabajoController::class, 'create'])
                ->name('agenda-terreno.create');
            Route::post('agenda-terreno', [AgendaTrabajoController::class, 'store'])
                ->name('agenda-terreno.store');
            Route::get('agenda-terreno/buscar-cliente', [AgendaTrabajoController::class, 'buscarCliente'])
                ->name('agenda-terreno.buscar-cliente');
            Route::get('agenda-terreno/{trabajo}/editar', [AgendaTrabajoController::class, 'edit'])
                ->whereNumber('trabajo')->name('agenda-terreno.edit');
            Route::put('agenda-terreno/{trabajo}', [AgendaTrabajoController::class, 'update'])
                ->whereNumber('trabajo')->name('agenda-terreno.update');
            Route::post('agenda-terreno/{trabajo}/rechazar', [AgendaTrabajoController::class, 'rechazar'])
                ->whereNumber('trabajo')->name('agenda-terreno.rechazar');
            Route::delete('agenda-terreno/{trabajo}', [AgendaTrabajoController::class, 'destroy'])
                ->whereNumber('trabajo')->name('agenda-terreno.destroy');

            // Catalogo de servicios de terreno (tarifario UF, editable).
            Route::resource('servicios-terreno', ServicioTerrenoController::class)
                ->parameters(['servicios-terreno' => 'servicio'])
                ->only(['index', 'create', 'store', 'edit', 'update']);
        });

        // "Costos generales de reparación": catálogo de tiempos estándar por
        // trabajo (jefatura). Fija la mano de obra que el técnico no puede editar.
        // Modulo Facturacion (M05). Existe antes de poder emitir: `index` muestra
        // lo emitido y de donde se puede emitir; `estado` es el checklist de lo que
        // falta, que es la informacion util mientras no se emite.
        Route::middleware('permission:emitir documentos tributarios')->group(function () {
            Route::get('documentos-tributarios', [\App\Http\Controllers\Admin\DteController::class, 'index'])
                ->name('dte.index');
            Route::get('documentos-tributarios/estado', [\App\Http\Controllers\Admin\DteController::class, 'estado'])
                ->name('dte.estado');
        });

        Route::middleware('permission:gestionar tiempos reparacion')->group(function () {
            Route::resource('tiempos-reparacion', \App\Http\Controllers\Admin\TiempoReparacionController::class)
                ->parameters(['tiempos-reparacion' => 'tiempo'])
                ->only(['index', 'create', 'store', 'edit', 'update']);
        });

        // Registro de INSTALACIONES del tecnico industrial (Excel de Carlos
        // Tablante): ledger editable. Lo gestionan el tecnico industrial, jefes
        // de venta y admin (buscar-cliente ANTES del resource para no chocar con
        // instalaciones/{instalacion}).
        Route::middleware('permission:gestionar instalaciones')->group(function () {
            Route::get('instalaciones/buscar-cliente', [InstalacionController::class, 'buscarCliente'])
                ->name('instalaciones.buscar-cliente');
            Route::resource('instalaciones', InstalacionController::class)
                ->parameters(['instalaciones' => 'instalacion'])
                ->except(['show']);
        });

        // Ingreso por LOTE (conductor en ruta): permiso acotado, NO gestiona el
        // taller. Rutas literales 'servicio-tecnico/lote...' (no chocan con el
        // show {orden} que exige whereNumber).
        Route::middleware('permission:crear lote servicio')->group(function () {
            Route::get('servicio-tecnico/lote', [LoteServicioController::class, 'create'])
                ->name('servicio-tecnico.lote.create');
            Route::post('servicio-tecnico/lote', [LoteServicioController::class, 'store'])
                ->name('servicio-tecnico.lote.store');
            Route::get('servicio-tecnico/lote/buscar-cliente', [LoteServicioController::class, 'buscarCliente'])
                ->name('servicio-tecnico.lote.buscar-cliente');
            Route::get('servicio-tecnico/lote/buscar-producto', [LoteServicioController::class, 'buscarProducto'])
                ->name('servicio-tecnico.lote.buscar-producto');
        });

        // TRASLADO de maquinas a reparar: sucursal -> casa matriz (decision del
        // dueño 03-08-2026). Las dos puntas van con permisos DISTINTOS a proposito
        // —despacha la sucursal, recibe el taller—: si una sola persona pudiera
        // cerrar ambas, la cadena de custodia no probaria nada. Ver el listado con
        // cualquiera de los dos.
        Route::middleware('permission:despachar traslado servicio|recibir traslado servicio')->group(function () {
            Route::get('traslados', [TrasladoServicioController::class, 'index'])->name('traslados.index');
            Route::get('traslados/{traslado}', [TrasladoServicioController::class, 'show'])
                ->whereNumber('traslado')->name('traslados.show');
        });
        Route::middleware('permission:despachar traslado servicio')->group(function () {
            Route::get('traslados/nuevo', [TrasladoServicioController::class, 'create'])->name('traslados.create');
            Route::post('traslados', [TrasladoServicioController::class, 'store'])->name('traslados.store');
        });
        Route::put('traslados/{traslado}/recibir', [TrasladoServicioController::class, 'recibir'])
            ->whereNumber('traslado')
            ->middleware('permission:recibir traslado servicio')
            ->name('traslados.recibir');

        Route::middleware('permission:manage servicio tecnico')->group(function () {
            Route::get('servicio-tecnico/buscar-cliente', [ServicioTecnicoController::class, 'buscarCliente'])
                ->name('servicio-tecnico.buscar-cliente');
            Route::get('servicio-tecnico/buscar-producto', [ServicioTecnicoController::class, 'buscarProducto'])
                ->name('servicio-tecnico.buscar-producto');
            Route::get('servicio-tecnico/buscar-repuesto', [ServicioTecnicoController::class, 'buscarRepuesto'])
                ->name('servicio-tecnico.buscar-repuesto');

            // QR por sucursal (link firmado imprimible para el mostrador).
            Route::get('servicio-tecnico/qr', [ServicioTecnicoController::class, 'qr'])
                ->name('servicio-tecnico.qr');

            // Documento tributario de la orden (M05 · B8). Hoy es un ENSAYO EN SECO:
            // arma el documento y lo muestra, pero el candado impide emitir. Gateada
            // por el permiso de emision aunque todavia no emita, para no tener que
            // acordarse de gatearla despues.
            Route::get('servicio-tecnico/{orden}/documento', [\App\Http\Controllers\Admin\DocumentoTributarioController::class, 'show'])
                ->middleware('permission:emitir documentos tributarios')
                ->whereNumber('orden')->name('servicio-tecnico.documento');

            // Etapa de taller (tecnico): registrar el arreglo, repuestos y fechas.
            Route::get('servicio-tecnico/{orden}/reparacion', [ServicioTecnicoController::class, 'reparacion'])
                ->name('servicio-tecnico.reparacion');
            Route::put('servicio-tecnico/{orden}/reparacion', [ServicioTecnicoController::class, 'guardarReparacion'])
                ->name('servicio-tecnico.reparacion.guardar');

            // Pestaña Cotización (ver + enviar): desglose guardado + envío al
            // cliente. GET propio; el POST de abajo (mismo path) es el envío.
            Route::get('servicio-tecnico/{orden}/cotizacion', [ServicioTecnicoController::class, 'cotizacion'])
                ->whereNumber('orden')->name('servicio-tecnico.cotizacion');

            // Cotización al cliente (P-M12-02): enviar la carta / reintentar el
            // correo si el SMTP falló. {cotizacion:id} porque el binding por
            // defecto del modelo es el token (para el link público).
            Route::post('servicio-tecnico/{orden}/cotizacion', [ServicioTecnicoController::class, 'enviarCotizacion'])
                ->name('servicio-tecnico.cotizacion.enviar');
            // Guardar el desglose de precios (repuestos, mano de obra, descuento)
            // que arma la cotización. PUT sobre el mismo path (POST = enviar).
            Route::put('servicio-tecnico/{orden}/cotizacion', [ServicioTecnicoController::class, 'guardarCotizacion'])
                ->whereNumber('orden')->name('servicio-tecnico.cotizacion.guardar');
            Route::post('servicio-tecnico/{orden}/cotizacion/{cotizacionId}/reintentar', [ServicioTecnicoController::class, 'reintentarCorreoCotizacion'])
                ->whereNumber('cotizacionId')->name('servicio-tecnico.cotizacion.reintentar');
            // Garantía: enviar al cliente el DETALLE del trabajo (sin cobro).
            Route::post('servicio-tecnico/{orden}/detalle-trabajo', [ServicioTecnicoController::class, 'enviarDetalleTrabajo'])
                ->whereNumber('orden')->name('servicio-tecnico.detalle-trabajo.enviar');

            // Registrar ingreso (crear) queda en 'manage': el técnico SÍ registra
            // equipos. Editar la recepción y eliminar se separan abajo.
            Route::resource('servicio-tecnico', ServicioTecnicoController::class)
                ->parameters(['servicio-tecnico' => 'orden'])
                ->only(['create', 'store']);

        });

        // Conductores (choferes) — administrables desde la app. Vive en LOGÍSTICA
        // desde el 04-08 (pedido del dueño): quien administra la flota administra
        // quién la maneja. El permiso es canAny y NO se cambió por 'manage
        // vehiculos' a secas porque el catálogo alimenta el selector del ingreso
        // por lote y el del traslado al taller: si el técnico lo perdiera, el
        // conductor que retira máquinas en ruta dejaría de existir para él.
        // El gate de la RUTA y el del ítem del menú son el MISMO (D-014): si acá
        // se agrega o se quita un permiso, hay que espejarlo en MenuPrincipal, o
        // el menú ofrece una pantalla que devuelve 403.
        Route::middleware('permission:manage servicio tecnico|manage vehiculos')->group(function () {
            Route::resource('conductores', ConductorController::class)
                ->parameters(['conductores' => 'conductor'])
                ->only(['index', 'create', 'store', 'edit', 'update']);
        });

        // EDITAR la recepción de una orden (datos de ingreso) y ELIMINARLA: permiso
        // aparte para poder limitárselo al técnico (pedido de gerencia). El técnico
        // conserva registrar ingreso + parte del técnico + cotización.
        Route::middleware('permission:editar recepcion servicio tecnico')->group(function () {
            Route::get('servicio-tecnico/{orden}/edit', [ServicioTecnicoController::class, 'edit'])
                ->whereNumber('orden')->name('servicio-tecnico.edit');
            Route::put('servicio-tecnico/{orden}', [ServicioTecnicoController::class, 'update'])
                ->whereNumber('orden')->name('servicio-tecnico.update');
            Route::delete('servicio-tecnico/{orden}', [ServicioTecnicoController::class, 'destroy'])
                ->whereNumber('orden')->name('servicio-tecnico.destroy');
        });

        // Produccion (Jefe de Bodega): asignar y revisar reportes.
        Route::middleware('permission:manage production')->group(function () {
            Route::get('produccion', [ProduccionController::class, 'index'])->name('produccion.index');
            Route::get('produccion/dia', [ProduccionController::class, 'diaDetalle'])->name('produccion.dia');
            Route::get('produccion/maquina/{maquina}', [ProduccionController::class, 'maquinaRendimiento'])->name('produccion.maquina');
            Route::get('produccion/tipo/{tipoBotellon}', [ProduccionController::class, 'tipoRendimiento'])->name('produccion.tipo');
            Route::get('produccion/sopladores', [ProduccionController::class, 'sopladores'])->name('produccion.sopladores');
            Route::get('produccion/movimientos', [ProduccionController::class, 'movimientos'])->name('produccion.movimientos');
            Route::get('produccion/soplador/{soplador}', [ProduccionController::class, 'sopladorHistorial'])->name('produccion.soplador');
            Route::get('produccion/asignar', [ProduccionController::class, 'asignar'])->name('produccion.asignar');
            Route::post('produccion/asignar', [ProduccionController::class, 'asignarStore'])->name('produccion.asignar.store');
            Route::get('produccion/reporte/{reporte}', [ProduccionController::class, 'reporteShow'])->name('produccion.reporte.show');
            Route::post('produccion/reporte/{reporte}/aprobar', [ProduccionController::class, 'aprobar'])->name('produccion.reporte.aprobar');
            Route::post('produccion/reporte/{reporte}/devolver', [ProduccionController::class, 'devolver'])->name('produccion.reporte.devolver');
            Route::post('produccion/reporte/{reporte}/ajustar', [ProduccionController::class, 'ajustar'])->name('produccion.reporte.ajustar');
            Route::delete('produccion/reporte/{reporte}', [ProduccionController::class, 'destroyReporte'])->name('produccion.reporte.destroy');

            // Catalogos de produccion: maquinas sopladoras y tipos de botellon.
            Route::resource('maquinas', MaquinaController::class)
                ->parameters(['maquinas' => 'maquina'])
                ->except(['show']);
            Route::resource('tipos-botellon', TipoBotellonController::class)
                ->parameters(['tipos-botellon' => 'tipoBotellon'])
                ->except(['show']);
        });

        // Despachos (Jefe de Bodega): crear despacho desde un documento
        // espejado + listado. Escaneo QR (P-DSP-04) y entrega (P-DSP-05)
        // llegan en pasos siguientes. Bloque AL FINAL del grupo (anti-colision).
        Route::middleware('permission:manage despachos')->group(function () {
            Route::get('despachos', [DespachoController::class, 'index'])->name('despachos.index');
            Route::get('despachos/nuevo', [DespachoController::class, 'create'])->name('despachos.create');
            Route::post('despachos', [DespachoController::class, 'store'])->name('despachos.store');

            // Cola de bodega (monitor) + su conteo liviano para el poll. Van
            // ANTES de las rutas con parámetro para que 'cola' no se coma como
            // {despacho}.
            Route::get('despachos/cola', [DespachoController::class, 'cola'])->name('despachos.cola');
            Route::get('despachos/cola/conteo', [DespachoController::class, 'colaConteo'])
                ->name('despachos.cola.conteo');

            // QR imprimible del despacho (P-DSP-04).
            Route::get('despachos/{despacho}/qr', [DespachoController::class, 'qr'])
                ->whereNumber('despacho')->name('despachos.qr');

            // Escaneo del QR en bodega. El GET exige FIRMA además del permiso
            // (ver la nota de superficie en DespachoController): la firma da
            // integridad al código del QR, el permiso da responsabilidad.
            Route::middleware('signed')
                ->get('despachos/escanear/{codigo}', [DespachoController::class, 'escanear'])
                ->name('despachos.escanear');
            // El POST no va firmado a propósito: nace del form de la pantalla
            // anterior (que ya exigió firma + permiso) y lleva CSRF. Firmar un
            // POST obligaría a incrustar la firma en el form sin ganar nada:
            // quien tiene 'manage despachos' ve todos los códigos en el panel,
            // así que la firma no es el control de acceso aquí.
            Route::post('despachos/escanear/{codigo}', [DespachoController::class, 'retiro'])
                ->name('despachos.retiro');

            // Cierre del despacho: entrega total o parcial con saldo.
            Route::post('despachos/{despacho}/entrega', [DespachoController::class, 'entrega'])
                ->whereNumber('despacho')->name('despachos.entrega');
        });

        // LOGISTICA · flota de vehiculos (pedido del dueño 04-08-2026).
        // Ver y editar van con permisos DISTINTOS: consultar los vencimientos es
        // lo que mañana necesita cobranzas (paga permisos de circulacion y SOAP)
        // sin poder cambiar una fecha. 'nuevo' se declara ANTES del show para que
        // no se lo coma como {vehiculo} (el whereNumber ya lo evita; el orden lo
        // deja explicito).
        Route::middleware('permission:manage vehiculos')->group(function () {
            Route::get('vehiculos/nuevo', [VehiculoController::class, 'create'])->name('vehiculos.create');
            Route::post('vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');
            Route::get('vehiculos/{vehiculo}/editar', [VehiculoController::class, 'edit'])
                ->whereNumber('vehiculo')->name('vehiculos.edit');
            Route::put('vehiculos/{vehiculo}', [VehiculoController::class, 'update'])
                ->whereNumber('vehiculo')->name('vehiculos.update');
            Route::delete('vehiculos/{vehiculo}', [VehiculoController::class, 'destroy'])
                ->whereNumber('vehiculo')->name('vehiculos.destroy');
        });
        Route::middleware('permission:ver vehiculos|manage vehiculos')->group(function () {
            Route::get('vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
            Route::get('vehiculos/{vehiculo}', [VehiculoController::class, 'show'])
                ->whereNumber('vehiculo')->name('vehiculos.show');
        });
    });

// Mi produccion (Soplador): su reporte del dia + registros (tandas) por maquina/tipo.
Route::middleware(['auth', 'permission:report production'])
    ->prefix('produccion')
    ->name('produccion.')
    ->group(function () {
        Route::get('mi-reporte', [MiProduccionController::class, 'index'])->name('mi.index');
        // Historial propio (ultimos 45 dias por defecto, filtro desde/hasta).
        // Path HERMANO a proposito, NO 'mi-reporte/historial': el {reporte} de
        // abajo captura cualquier segmento y el binding buscaria un reporte con
        // id "historial" (404). Peor: el resultado depende del matcher (sin
        // cache manda el orden de registro; con route:cache —que corre en cada
        // deploy— manda el mapa estatico de Symfony) => divergencia local/prod.
        Route::get('mi-historial', [MiProduccionController::class, 'historial'])->name('mi.historial');
        // whereNumber: cinturon para el proximo que cuelgue un path bajo mi-reporte/.
        Route::get('mi-reporte/{reporte}', [MiProduccionController::class, 'show'])->whereNumber('reporte')->name('mi.show');
        Route::patch('mi-reporte/{reporte}', [MiProduccionController::class, 'update'])->whereNumber('reporte')->name('mi.update');
        Route::post('mi-reporte/{reporte}/registros', [MiProduccionController::class, 'registroStore'])->whereNumber('reporte')->name('mi.registros.store');
        Route::delete('mi-reporte/{reporte}/registros/{registro}', [MiProduccionController::class, 'registroDestroy'])->whereNumber(['reporte', 'registro'])->name('mi.registros.destroy');
    });

// Mis entregas (Conductor, P-DSP-05): SU hoja de ruta del dia y la confirmacion
// de entrega con firma+foto+hora. Grupo de OPERARIO (patron produccion.mi.*),
// fuera de /admin: el conductor no ve el panel del jefe. El POST es el destino
// de la cola offline (multipart + entrega_uuid idempotente).
Route::middleware(['auth', 'permission:confirmar entrega'])
    ->prefix('entregas')
    ->name('entregas.')
    ->group(function () {
        Route::get('', [\App\Http\Controllers\Entregas\EntregaConductorController::class, 'index'])->name('index');
        Route::post('{despacho}/confirmar', [\App\Http\Controllers\Entregas\EntregaConductorController::class, 'confirmar'])
            ->whereNumber('despacho')->name('confirmar');
    });

// Fallback offline de la PWA (sin auth: el service worker la precachea en su
// install, antes de cualquier login). Ver public/sw.js.
Route::get('offline', fn () => view('offline'))->name('offline');

// Ingreso PUBLICO a servicio tecnico por QR (P-M12-01, piloto). Sin login: el
// cliente escanea el QR del mostrador y llena el formulario en su celular. El
// GET (link del QR) va firmado (lleva la sucursal); throttle en todo el grupo.
// Ver App\Http\Controllers\Publico\IngresoTallerPublicoController.
Route::middleware('throttle:6,1')->group(function () {
    Route::get('ingreso-taller', [IngresoTallerPublicoController::class, 'create'])
        ->middleware('signed')->name('ingreso-taller.create');
    Route::post('ingreso-taller', [IngresoTallerPublicoController::class, 'store'])
        ->name('ingreso-taller.store');
    Route::get('ingreso-taller/listo/{orden}', [IngresoTallerPublicoController::class, 'gracias'])
        ->middleware('signed')->name('ingreso-taller.gracias');

    // Ingreso por CANTIDAD (varias máquinas de una vez, datos del cliente una
    // sola vez; cada máquina queda con su propio folio). Mismo esquema: GET y
    // "gracias" firmados, POST con honeypot.
    Route::get('ingreso-taller/lote', [IngresoTallerPublicoController::class, 'createLote'])
        ->middleware('signed')->name('ingreso-taller.lote.create');
    Route::post('ingreso-taller/lote', [IngresoTallerPublicoController::class, 'storeLote'])
        ->name('ingreso-taller.lote.store');
    Route::get('ingreso-taller/lote/listo/{lote}', [IngresoTallerPublicoController::class, 'graciasLote'])
        ->middleware('signed')->name('ingreso-taller.lote.gracias');

    // Solicitud de visita/revision INDUSTRIAL (el tecnico va donde el cliente):
    // entra a la Agenda de terreno como 'solicitado' y el staff la coordina.
    Route::get('visita-industrial', [VisitaIndustrialPublicoController::class, 'create'])
        ->middleware('signed')->name('visita-industrial.create');
    Route::post('visita-industrial', [VisitaIndustrialPublicoController::class, 'store'])
        ->name('visita-industrial.store');
    Route::get('visita-industrial/listo/{trabajo}', [VisitaIndustrialPublicoController::class, 'gracias'])
        ->middleware('signed')->name('visita-industrial.gracias');

    // Respuesta del cliente a una COTIZACION del taller (P-M12-02): link firmado
    // del correo. El POST tambien va firmado (autorizacion comercial: no espera
    // al endurecimiento P-F3-01 del QR). Binding por token (no enumerable).
    Route::get('cotizacion/{cotizacion}', [CotizacionPublicoController::class, 'mostrar'])
        ->middleware('signed')->name('cotizacion.mostrar');
    Route::post('cotizacion/{cotizacion}/respuesta', [CotizacionPublicoController::class, 'responder'])
        ->middleware('signed')->name('cotizacion.responder');
    Route::get('cotizacion/{cotizacion}/gracias', [CotizacionPublicoController::class, 'gracias'])
        ->middleware('signed')->name('cotizacion.gracias');

    // Confirmación del cliente a una visita de terreno agendada (link firmado).
    // Token propio (no enumerable); el POST también va firmado. Confirma que
    // puede ese día o avisa que no, con un comentario libre corto.
    Route::get('confirmacion-visita/{token}', [VisitaConfirmacionController::class, 'mostrar'])
        ->middleware('signed')->name('confirmacion-visita.mostrar');
    Route::post('confirmacion-visita/{token}/respuesta', [VisitaConfirmacionController::class, 'responder'])
        ->middleware('signed')->name('confirmacion-visita.responder');
    Route::get('confirmacion-visita/{token}/gracias', [VisitaConfirmacionController::class, 'gracias'])
        ->middleware('signed')->name('confirmacion-visita.gracias');
});

require __DIR__.'/auth.php';
