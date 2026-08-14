<?php

use App\Http\Controllers\Admin\AgendaCierreController;
use App\Http\Controllers\Admin\AgendaTrabajoController;
use App\Http\Controllers\Admin\ConductorController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\DespachoController;
use App\Http\Controllers\Admin\DevolucionController;
use App\Http\Controllers\Admin\HojaRutaController;
use App\Http\Controllers\Admin\InstalacionController;
use App\Http\Controllers\Admin\ListaPrecioController;
use App\Http\Controllers\Admin\LoteServicioController;
use App\Http\Controllers\Admin\MaquinaController;
use App\Http\Controllers\Admin\MoldeController;
use App\Http\Controllers\Admin\NotificacionController;
use App\Http\Controllers\Admin\ProduccionController;
use App\Http\Controllers\Admin\ProduccionNotaController;
use App\Http\Controllers\Admin\ProduccionVivoController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\RecetaController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ServicioTecnicoController;
use App\Http\Controllers\Admin\ServicioTerrenoController;
use App\Http\Controllers\Admin\SucursalController;
use App\Http\Controllers\Admin\TipoBotellonController;
use App\Http\Controllers\Admin\TrasladoServicioController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CargaRealController;
use App\Http\Controllers\Admin\SimuladorCargaController;
use App\Http\Controllers\Admin\VehiculoController;
use App\Http\Controllers\Admin\VehiculoDocumentoController;
use App\Http\Controllers\Admin\VehiculoDocumentoTipoController;
use App\Http\Controllers\AprobacionController;
use App\Http\Controllers\DashboardColoresController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificacionPreferenciaController;
use App\Http\Controllers\NotificacionUsuarioController;
use App\Http\Controllers\PlanProyectoController;
use App\Http\Controllers\Produccion\MiProduccionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Publico\CotizacionPublicoController;
use App\Http\Controllers\Publico\DevolucionPublicoController;
use App\Http\Controllers\Publico\IngresoTallerPublicoController;
use App\Http\Controllers\Publico\PlanCargaPublicoController;
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

        // Inventario (M04): bodegas + stock espejados desde Bsale. La LECTURA
        // (index/show) va con `manage productos` (quien ve el catalogo ve su
        // stock); la CLASIFICACION local (edit/update, M04-F1) con `manage
        // sucursales` — administrar la estructura de bodegas es el mismo acto
        // que administrar sucursales, sin permiso nuevo (leccion M13: la
        // matriz de roles no se toca).
        // El wizard de baja + la orden de traslado (M04-F2, P-M04-20) van con
        // el MISMO permiso que la clasificacion. Las rutas con literal
        // ('traslados', 'baja') se declaran ANTES del resource y las
        // parametricas llevan whereNumber — doble candado idiomatico
        // (vehiculos/excel).
        Route::middleware('permission:manage sucursales')->group(function () {
            Route::get('bodegas/traslados/{traslado}', [\App\Http\Controllers\Admin\BodegaTrasladoController::class, 'show'])
                ->whereNumber('traslado')->name('bodegas.traslados.show');
            Route::get('bodegas/traslados/{traslado}/excel', [\App\Http\Controllers\Admin\BodegaTrasladoController::class, 'excel'])
                ->whereNumber('traslado')->name('bodegas.traslados.excel');
            Route::post('bodegas/traslados/{traslado}/anular', [\App\Http\Controllers\Admin\BodegaTrasladoController::class, 'anular'])
                ->whereNumber('traslado')->name('bodegas.traslados.anular');
            Route::get('bodegas/{bodega}/baja', [BodegaController::class, 'baja'])
                ->whereNumber('bodega')->name('bodegas.baja');
            Route::post('bodegas/{bodega}/baja', [BodegaController::class, 'bajaStore'])
                ->whereNumber('bodega')->name('bodegas.baja.store');
        });
        Route::resource('bodegas', BodegaController::class)
            ->middleware('permission:manage productos')
            ->only(['index', 'show']);
        Route::resource('bodegas', BodegaController::class)
            ->middleware('permission:manage sucursales')
            ->only(['edit', 'update']);

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
        // El .xlsx va DENTRO del mismo grupo de permiso que su informe: es la
        // misma informacion en otro formato, asi que no puede tener una puerta
        // mas ancha que la pantalla (el archivo trae el detalle completo, con
        // datos de clientes).
        Route::middleware('permission:ver informe dispensadores')->group(function () {
            Route::get('servicio-tecnico/informe/dispensadores', [ServicioTecnicoController::class, 'informeDispensadores'])
                ->name('servicio-tecnico.informe.dispensadores');
            Route::get('servicio-tecnico/informe/dispensadores/excel', [ServicioTecnicoController::class, 'informeDispensadoresExcel'])
                ->name('servicio-tecnico.informe.dispensadores.excel');
        });
        Route::middleware('permission:ver informe industrial')->group(function () {
            Route::get('servicio-tecnico/informe/industrial', [ServicioTecnicoController::class, 'informeIndustrial'])
                ->name('servicio-tecnico.informe.industrial');
            Route::get('servicio-tecnico/informe/industrial/excel', [ServicioTecnicoController::class, 'informeIndustrialExcel'])
                ->name('servicio-tecnico.informe.industrial.excel');
        });

        // Registrar el pago y autorizar: SOLO quien coordina plata — vendedor/
        // jefe_ventas/admin (permiso propio). El técnico ya no lo tiene (dueño
        // 07-08): el taller repara con la aceptación del cliente y el cobro es
        // en sala de ventas al retiro.
        Route::middleware('permission:autorizar reparacion')->group(function () {
            Route::post('servicio-tecnico/{orden}/cotizacion/autorizar', [ServicioTecnicoController::class, 'autorizarReparacion'])
                ->whereNumber('orden')->name('servicio-tecnico.cotizacion.autorizar');
        });

        // El otro desenlace de la respuesta del cliente: NO aceptó → avisarle por
        // correo que puede pasar a retirar sin reparar (dueño 06-08). No es plata,
        // es coordinar el retiro: lo manda el taller ('manage') o ventas
        // ('autorizar reparacion'), quien llegue primero desde la campanita.
        Route::middleware('permission:manage servicio tecnico|autorizar reparacion')->group(function () {
            Route::post('servicio-tecnico/{orden}/cotizacion/{cotizacionId}/avisar-retiro', [ServicioTecnicoController::class, 'avisarRetiroSinReparar'])
                ->whereNumber('orden')->whereNumber('cotizacionId')->name('servicio-tecnico.cotizacion.avisar-retiro');
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
        // Cuando el tecnico NO esta disponible: feriados, vacaciones, medias jornadas.
        // Permiso PROPIO ('gestionar cierres agenda', del jefe de ventas): agendar un trabajo
        // y cerrarle la agenda a todos no son la misma responsabilidad. Va ANTES del
        // `agenda-terreno/crear` porque las dos son rutas fijas y no compiten, pero se lee
        // mejor junto al resto de la agenda. El controlador exige el permiso otra vez.
        Route::middleware('permission:gestionar cierres agenda')->group(function () {
            Route::get('agenda-terreno/cierres', [AgendaCierreController::class, 'index'])
                ->name('agenda-terreno.cierres.index');
            Route::post('agenda-terreno/cierres', [AgendaCierreController::class, 'store'])
                ->name('agenda-terreno.cierres.store');
            Route::delete('agenda-terreno/cierres/{cierre}', [AgendaCierreController::class, 'destroy'])
                ->whereNumber('cierre')->name('agenda-terreno.cierres.destroy');
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
            // Excel del registro (respaldo de las horas extras del tecnico).
            // TAMBIEN antes del resource, por lo mismo que buscar-cliente: si va
            // despues, 'excel' entra como {instalacion} y da un 404 de modelo.
            Route::get('instalaciones/excel', [InstalacionController::class, 'excel'])
                ->name('instalaciones.excel');
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
            // El técnico cierra su parte: le avisa al cliente que su equipo está
            // listo y que pague en sala de ventas al retirar (dueño 07-08). Va en
            // 'manage' —es del taller—, NO en 'autorizar reparacion' (plata).
            Route::post('servicio-tecnico/{orden}/listo-para-retiro', [ServicioTecnicoController::class, 'avisarListoParaRetiro'])
                ->whereNumber('orden')->name('servicio-tecnico.listo-para-retiro');

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
            // Panel «Hoy en vivo» (P-M11-21): monitor con poll de firma. El
            // conteo va ANTES de cualquier ruta con parámetro por doctrina
            // (idioma de despachos/cola).
            Route::get('produccion/vivo', [ProduccionVivoController::class, 'vivo'])->name('produccion.vivo');
            Route::get('produccion/vivo/conteo', [ProduccionVivoController::class, 'conteo'])->name('produccion.vivo.conteo');
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

            // Kaizen (P-M11-23): decision del jefe sobre una propuesta de
            // mejora (revisada|aplicada|descartada + respuesta opcional). Solo
            // PATCH: la bandeja vive como seccion del panel (produccion.index),
            // sin pagina GET propia — por eso NO se toca MenuPrincipal.
            Route::patch('produccion/mejoras/{mejora}', [ProduccionController::class, 'mejoraUpdate'])
                ->whereNumber('mejora')->name('produccion.mejoras.update');

            // Notas del jefe (P-M11-22): mensajes operativos que se pintan en
            // mi-reporte del soplador mientras estan vigentes. Names DENTRO de
            // admin.produccion.* a proposito: notas NO tiene item de menu y el
            // patron admin.produccion.notas.* (enumerado en MenuPrincipal)
            // hace que resalte el item Produccion.
            Route::resource('produccion/notas', ProduccionNotaController::class)
                ->names('produccion.notas')
                ->parameters(['notas' => 'nota'])
                ->except(['show']);

            // Catalogos de produccion: maquinas sopladoras y tipos de botellon.
            Route::resource('maquinas', MaquinaController::class)
                ->parameters(['maquinas' => 'maquina'])
                ->except(['show']);
            Route::resource('tipos-botellon', TipoBotellonController::class)
                ->parameters(['tipos-botellon' => 'tipoBotellon'])
                ->except(['show']);

            // Recetas de botellon (P-M11-10): que componentes consume UNA
            // unidad; el backflush del kardex las lee al aprobar. Nombres
            // FUERA del prefijo admin.produccion.* que el item del menu
            // enumera (candado de doble aria-current, gate 28-07).
            Route::get('recetas', [RecetaController::class, 'index'])->name('recetas.index');
            Route::get('recetas/{producto}/edit', [RecetaController::class, 'edit'])
                ->whereNumber('producto')->name('recetas.edit');
            Route::put('recetas/{producto}', [RecetaController::class, 'update'])
                ->whereNumber('producto')->name('recetas.update');

            // Moldes (P-M11-12): ficha estilo M18 con contador de ciclos,
            // umbral de mantencion e historial. Literales ANTES de la
            // parametrica + whereNumber (doble candado idiomatico).
            Route::get('moldes', [MoldeController::class, 'index'])->name('moldes.index');
            Route::get('moldes/nuevo', [MoldeController::class, 'create'])->name('moldes.create');
            Route::post('moldes', [MoldeController::class, 'store'])->name('moldes.store');
            Route::get('moldes/{molde}', [MoldeController::class, 'show'])
                ->whereNumber('molde')->name('moldes.show');
            Route::get('moldes/{molde}/editar', [MoldeController::class, 'edit'])
                ->whereNumber('molde')->name('moldes.edit');
            Route::put('moldes/{molde}', [MoldeController::class, 'update'])
                ->whereNumber('molde')->name('moldes.update');
            Route::post('moldes/{molde}/mantencion', [MoldeController::class, 'mantencionStore'])
                ->whereNumber('molde')->name('moldes.mantencion.store');
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
            // Respaldo digital de un documento (11-08): SUBIR es de quien
            // gestiona la flota — los papeles oficiales los maneja quien los
            // renueva. El servidor comprime; el que sube no tiene que saber.
            Route::post('vehiculos/{vehiculo}/documentos/{documento}', [VehiculoDocumentoController::class, 'store'])
                ->whereNumber('vehiculo')->name('vehiculos.documentos.store');
            // QUITAR una foto subida (pedido del dueño 11-08). Borra la ÚLTIMA
            // versión y deja a la vista la anterior si existía: el caso real es
            // «subí la foto equivocada», no «este vehículo no tiene SOAP».
            Route::delete('vehiculos/documento-archivo/{doc}', [VehiculoDocumentoController::class, 'destroy'])
                ->whereNumber('doc')->name('vehiculos.documentos.destroy');
            // CREAR tipos de documento (11-08). Los cinco de la ley no se administran
            // acá: son columnas del vehículo. Esto es para los que pidan después.
            // Va con 'manage vehiculos' porque cambia el semáforo de TODA la flota.
            Route::get('vehiculos-tipos-documento', [VehiculoDocumentoTipoController::class, 'index'])
                ->name('vehiculos.tipos-documento.index');
            Route::post('vehiculos-tipos-documento', [VehiculoDocumentoTipoController::class, 'store'])
                ->name('vehiculos.tipos-documento.store');
            Route::put('vehiculos-tipos-documento/{tipo}', [VehiculoDocumentoTipoController::class, 'update'])
                ->whereNumber('tipo')->name('vehiculos.tipos-documento.update');
            Route::delete('vehiculos-tipos-documento/{tipo}', [VehiculoDocumentoTipoController::class, 'destroy'])
                ->whereNumber('tipo')->name('vehiculos.tipos-documento.destroy');
        });
        // LOGISTICA · simulador de carga. Es una CALCULADORA: no escribe nada
        // operativo, asi que va con su propio permiso (lo usa ventas, que no
        // administra la flota) y solo por GET.
        Route::middleware('permission:simular carga')->group(function () {
            Route::get('carga', [SimuladorCargaController::class, 'index'])->name('carga.index');
            // El plan de carga como .xlsx. Lleva los MISMOS parametros que la
            // pantalla en la query, asi que baja exactamente lo que se esta mirando.
            Route::get('carga/excel', [SimuladorCargaController::class, 'excel'])->name('carga.excel');
            // CARGAS REALES: lo que entro de verdad, contra lo que el simulador dijo.
            // Es lo unico que da un factor de correccion propio, y va con el MISMO
            // permiso porque calibra esta calculadora y no otra cosa. Estas si
            // escriben, asi que llevan POST/DELETE ademas del GET.
            Route::get('cargas-reales', [CargaRealController::class, 'index'])->name('cargas-reales.index');
            Route::post('cargas-reales', [CargaRealController::class, 'store'])->name('cargas-reales.store');
            Route::delete('cargas-reales/{cargasReale}', [CargaRealController::class, 'destroy'])
                ->whereNumber('cargasReale')->name('cargas-reales.destroy');
        });
        Route::middleware('permission:ver vehiculos|manage vehiculos')->group(function () {
            Route::get('vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
            // La descarga va ANTES del show: 'excel' no es numérico, así que el
            // whereNumber ya lo protege, pero el orden lo deja explícito.
            Route::get('vehiculos/excel', [VehiculoController::class, 'excel'])->name('vehiculos.excel');
            // Respaldo digital de un documento (11-08): VER es de 'ver vehiculos',
            // que ahora también lo tiene el rol conductor — el caso de uso es el
            // control en ruta, con el teléfono. El archivo se sirve SOLO por acá
            // (autenticado): lleva la patente, dato personal bajo la 21.719.
            Route::get('vehiculos/documento-archivo/{doc}', [VehiculoDocumentoController::class, 'archivo'])
                ->whereNumber('doc')->name('vehiculos.documentos.archivo');
            Route::get('vehiculos/{vehiculo}/documentos/{documento}', [VehiculoDocumentoController::class, 'show'])
                ->whereNumber('vehiculo')->name('vehiculos.documentos.show');
            Route::get('vehiculos/{vehiculo}', [VehiculoController::class, 'show'])
                ->whereNumber('vehiculo')->name('vehiculos.show');
        });

        // Devoluciones (M13, flujo A-12): consultar es distinto de operar.
        // Las MUTACIONES (recibir/evaluar/resolver) exigen manage; el listado,
        // la ficha y la foto (disco privado, con sesión) bastan con view.
        Route::middleware('permission:view devoluciones|manage devoluciones')->group(function () {
            Route::get('devoluciones', [DevolucionController::class, 'index'])->name('devoluciones.index');
            // El informe ANTES del show: 'informe' no es numérico, así que el
            // whereNumber ya lo protege, pero el orden lo deja explícito (mismo
            // idioma que vehiculos/excel). Sin permiso granular nuevo a propósito:
            // agrega la MISMA información que el listado ya muestra, agregada —
            // ST necesitó granularidad porque taller y terreno son audiencias
            // distintas; acá no hay tal división (declarado en el parte v34).
            Route::get('devoluciones/informe', [DevolucionController::class, 'informe'])->name('devoluciones.informe');
            // La foto ANTES del show: 'foto/...' son 2 segmentos, no chocan
            // con {devolucion} numérico (mismo idioma que servicio-tecnico.foto).
            Route::get('devoluciones/foto/{foto}', [DevolucionController::class, 'foto'])
                ->whereNumber('foto')->name('devoluciones.foto');
            Route::get('devoluciones/{devolucion:id}', [DevolucionController::class, 'show'])
                ->whereNumber('devolucion')->name('devoluciones.show');
        });
        Route::middleware('permission:manage devoluciones')->group(function () {
            Route::post('devoluciones/{devolucion:id}/recibir', [DevolucionController::class, 'recibir'])
                ->whereNumber('devolucion')->name('devoluciones.recibir');
            Route::post('devoluciones/{devolucion:id}/evaluar', [DevolucionController::class, 'evaluar'])
                ->whereNumber('devolucion')->name('devoluciones.evaluar');
            Route::post('devoluciones/{devolucion:id}/resolver', [DevolucionController::class, 'resolver'])
                ->whereNumber('devolucion')->name('devoluciones.resolver');
        });

        // Hoja de ruta digital (P-DSP-08, PLAN-DESPACHOS-V2). Armarla es de
        // quien gestiona ('manage hojas ruta': Ricardo); las 3 llaves de la
        // cadena R11 son PERMISOS SEPARADOS y cada una gatea SU ruta — el
        // index/show lo ven además los tres autorizadores, que necesitan la
        // hoja delante para dar su llave (D-014: el menú espeja este gate).
        Route::middleware('permission:manage hojas ruta')->group(function () {
            Route::get('hojas-ruta/nueva', [HojaRutaController::class, 'create'])->name('hojas-ruta.create');
            Route::post('hojas-ruta', [HojaRutaController::class, 'store'])->name('hojas-ruta.store');
            Route::put('hojas-ruta/{hoja}/orden', [HojaRutaController::class, 'orden'])
                ->whereNumber('hoja')->name('hojas-ruta.orden');
        });
        Route::middleware('permission:manage hojas ruta|autorizar pagos ruta|autorizar ruta|autorizar carga')->group(function () {
            Route::get('hojas-ruta', [HojaRutaController::class, 'index'])->name('hojas-ruta.index');
            Route::get('hojas-ruta/{hoja}', [HojaRutaController::class, 'show'])
                ->whereNumber('hoja')->name('hojas-ruta.show');
        });
        Route::middleware('permission:autorizar pagos ruta')
            ->post('hojas-ruta/{hoja}/autorizar-pagos', [HojaRutaController::class, 'autorizarPagos'])
            ->whereNumber('hoja')->name('hojas-ruta.autorizar-pagos');
        Route::middleware('permission:autorizar ruta')
            ->post('hojas-ruta/{hoja}/autorizar-ruta', [HojaRutaController::class, 'autorizarRuta'])
            ->whereNumber('hoja')->name('hojas-ruta.autorizar-ruta');
        Route::middleware('permission:autorizar carga')->group(function () {
            Route::post('hojas-ruta/{hoja}/autorizar-carga', [HojaRutaController::class, 'autorizarCarga'])
                ->whereNumber('hoja')->name('hojas-ruta.autorizar-carga');
            // La salida la registra bodega (ve partir el camión): misma llave 3.
            Route::post('hojas-ruta/{hoja}/salir', [HojaRutaController::class, 'salir'])
                ->whereNumber('hoja')->name('hojas-ruta.salir');
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
        // Paradas del turno (P-M11-20): que detuvo la produccion + horas.
        // Mismo contrato offline que los registros (cliente_uuid idempotente).
        Route::post('mi-reporte/{reporte}/paradas', [MiProduccionController::class, 'paradaStore'])->whereNumber('reporte')->name('mi.paradas.store');
        Route::delete('mi-reporte/{reporte}/paradas/{parada}', [MiProduccionController::class, 'paradaDestroy'])->whereNumber(['reporte', 'parada'])->name('mi.paradas.destroy');
        // Propuesta de mejora (P-M11-23, kaizen): texto libre que llega a la
        // bandeja del jefe. Path HERMANO (nada nuevo bajo mi-reporte/ — ver el
        // comentario de mi-historial); no cuelga de un reporte a proposito: se
        // puede proponer incluso sin asignacion del dia. Mismo contrato
        // offline que tandas/paradas (cliente_uuid idempotente).
        Route::post('mis-mejoras', [MiProduccionController::class, 'mejoraStore'])->name('mi.mejoras.store');
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
        // Rechazo en puerta (P-DSP-09, R15): segundo destino de la cola
        // offline, mismo permiso y mismo scoping por hoja que confirmar.
        Route::post('{despacho}/rechazar', [\App\Http\Controllers\Entregas\EntregaConductorController::class, 'rechazar'])
            ->whereNumber('despacho')->name('rechazar');
    });

// Fallback offline de la PWA (sin auth: el service worker la precachea en su
// install, antes de cualquier login). Ver public/sw.js.
Route::get('offline', fn () => view('offline'))->name('offline');

// Ingreso PUBLICO a servicio tecnico por QR (P-M12-01, piloto). Sin login: el
// cliente escanea el QR del mostrador y llena el formulario en su celular. El
// GET (link del QR) va firmado (lleva la sucursal); throttle en todo el grupo.
// Ver App\Http\Controllers\Publico\IngresoTallerPublicoController.
// Plan de carga en 3D compartible, SIN login (pedido del dueño 10-08). El link ES
// el escenario —el simulador es una funcion pura de su query— y va FIRMADO y con
// VENCIMIENTO, asi que no se puede fabricar ni retocar y no vive para siempre. Es
// solo lectura: el simulador es una calculadora y no escribe nada.
// Ver App\Http\Controllers\Publico\PlanCargaPublicoController.
Route::middleware(['throttle:30,1', 'signed'])
    ->get('plan-de-carga', [PlanCargaPublicoController::class, 'show'])
    ->name('publico.plan-carga');

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

    // ¿Esta fecha esta libre? Alimenta el cartel en vivo del campo "cuando te acomoda"
    // (pedido del dueno 13-08). Va con throttle PROPIO y mas alto que el 6/min del grupo:
    // el cliente tantea varias fechas seguidas y con 6 se queda sin respuesta a la tercera.
    // Solo lectura y sin datos de nadie: contesta booleanos y fechas (ver el controlador).
    Route::get('visita-industrial/disponibilidad', [VisitaIndustrialPublicoController::class, 'disponibilidad'])
        ->withoutMiddleware('throttle:6,1')->middleware('throttle:40,1')
        ->name('visita-industrial.disponibilidad');

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

// Devolución PÚBLICA del cliente (M13, P-M13-01). Grupo con throttle PROPIO
// (aprobado en PLAN-M13 §4): el limitador de invitados no incluye la ruta en
// su firma, así que compartir el 6,1 de arriba dejaba fuera con un 429 al
// cliente que reintenta con fotos (GET→POST→GET ya gasta 3). La variante
// ENDURECIDA: GET y POST firmados (no la del QR viejo, cuya deuda ya lista
// P-F3-01); binding por token de 64 (no enumerable).
Route::middleware('throttle:12,1')->group(function () {
    Route::get('devolucion', [DevolucionPublicoController::class, 'create'])
        ->middleware('signed')->name('devolucion.create');
    Route::post('devolucion', [DevolucionPublicoController::class, 'store'])
        ->middleware('signed')->name('devolucion.store');
    Route::get('devolucion/listo/{devolucion}', [DevolucionPublicoController::class, 'gracias'])
        ->middleware('signed')->name('devolucion.gracias');
});

require __DIR__.'/auth.php';
