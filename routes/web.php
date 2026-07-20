<?php

use App\Http\Controllers\Admin\AgendaTrabajoController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ConfiguracionController;
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
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AprobacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificacionPreferenciaController;
use App\Http\Controllers\NotificacionUsuarioController;
use App\Http\Controllers\Produccion\MiProduccionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Publico\IngresoTallerPublicoController;
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
    Route::put('/perfil/notificaciones', [NotificacionPreferenciaController::class, 'update'])->name('perfil.notificaciones.update');

    // Campanita (M15): bandeja personal in-app; cualquier usuario gestiona LO SUYO.
    Route::get('/notificaciones', [NotificacionUsuarioController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/leer-todas', [NotificacionUsuarioController::class, 'leerTodas'])->name('notificaciones.leer-todas');
    Route::post('/notificaciones/{notificacion}/leer', [NotificacionUsuarioController::class, 'leer'])->name('notificaciones.leer');

    // Mis solicitudes de aprobacion (M14): el solicitante ve LO SUYO (patron
    // /notificaciones). Literal ANTES del grupo {aprobacion} de abajo.
    Route::get('/aprobaciones/mias', [AprobacionController::class, 'mias'])->name('aprobaciones.mias');
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
            // Informes: landing con dos "carpetas" (Dispensadores / Industrial),
            // y el informe de cada uno por periodo (año o mes) para los jefes.
            Route::get('servicio-tecnico/informe', [ServicioTecnicoController::class, 'informes'])
                ->name('servicio-tecnico.informe');
            Route::get('servicio-tecnico/informe/dispensadores', [ServicioTecnicoController::class, 'informeDispensadores'])
                ->name('servicio-tecnico.informe.dispensadores');
            Route::get('servicio-tecnico/informe/industrial', [ServicioTecnicoController::class, 'informeIndustrial'])
                ->name('servicio-tecnico.informe.industrial');
            // BOCETO interno: vista de seguimiento (estilo Blue Express) del estado
            // de un equipo. Sin conexion a datos; solo un adelanto del diseño.
            Route::get('servicio-tecnico/seguimiento-demo', [ServicioTecnicoController::class, 'seguimientoDemo'])
                ->name('servicio-tecnico.seguimiento-demo');
            // Foto de recepcion (disco privado, servida con sesion). ANTES del show
            // {orden} literalmente "foto/..." son 2 segmentos, no chocan con {orden}.
            Route::get('servicio-tecnico/foto/{foto}', [ServicioTecnicoController::class, 'foto'])
                ->whereNumber('foto')->name('servicio-tecnico.foto');
            Route::get('servicio-tecnico/{orden}', [ServicioTecnicoController::class, 'show'])
                ->whereNumber('orden')->name('servicio-tecnico.show');
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
            Route::delete('agenda-terreno/{trabajo}', [AgendaTrabajoController::class, 'destroy'])
                ->whereNumber('trabajo')->name('agenda-terreno.destroy');

            // Catalogo de servicios de terreno (tarifario UF, editable).
            Route::resource('servicios-terreno', ServicioTerrenoController::class)
                ->parameters(['servicios-terreno' => 'servicio'])
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

            // Etapa de taller (tecnico): registrar el arreglo, repuestos y fechas.
            Route::get('servicio-tecnico/{orden}/reparacion', [ServicioTecnicoController::class, 'reparacion'])
                ->name('servicio-tecnico.reparacion');
            Route::put('servicio-tecnico/{orden}/reparacion', [ServicioTecnicoController::class, 'guardarReparacion'])
                ->name('servicio-tecnico.reparacion.guardar');

            Route::resource('servicio-tecnico', ServicioTecnicoController::class)
                ->parameters(['servicio-tecnico' => 'orden'])
                ->only(['create', 'store', 'edit', 'update', 'destroy']);
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
    });

// Mi produccion (Soplador): su reporte del dia + registros (tandas) por maquina/tipo.
Route::middleware(['auth', 'permission:report production'])
    ->prefix('produccion')
    ->name('produccion.')
    ->group(function () {
        Route::get('mi-reporte', [MiProduccionController::class, 'index'])->name('mi.index');
        Route::get('mi-reporte/{reporte}', [MiProduccionController::class, 'show'])->name('mi.show');
        Route::patch('mi-reporte/{reporte}', [MiProduccionController::class, 'update'])->name('mi.update');
        Route::post('mi-reporte/{reporte}/registros', [MiProduccionController::class, 'registroStore'])->name('mi.registros.store');
        Route::delete('mi-reporte/{reporte}/registros/{registro}', [MiProduccionController::class, 'registroDestroy'])->name('mi.registros.destroy');
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
});

// Autocompletado publico del producto Dali para el formulario del QR. Throttle
// propio (mas alto que el envio) porque dispara en cada tecla; solo lee catalogo.
Route::get('ingreso-taller/buscar-producto', [IngresoTallerPublicoController::class, 'buscarProducto'])
    ->middleware('throttle:30,1')->name('ingreso-taller.buscar-producto');

require __DIR__.'/auth.php';
