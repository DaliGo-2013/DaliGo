<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ConfiguracionController;
use App\Http\Controllers\Admin\ListaPrecioController;
use App\Http\Controllers\Admin\ProduccionController;
use App\Http\Controllers\Admin\ProductoController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SucursalController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Produccion\MiProduccionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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

        // Catalogo de productos (nivel SKU) + import/export CSV.
        // Las rutas literales van ANTES del resource para no chocar con productos/{producto}.
        Route::middleware('permission:manage productos')->group(function () {
            Route::get('productos/importar', [ProductoController::class, 'importForm'])->name('productos.import.form');
            Route::post('productos/importar', [ProductoController::class, 'import'])->name('productos.import');
            Route::get('productos/exportar', [ProductoController::class, 'export'])->name('productos.export');
            Route::get('productos/plantilla', [ProductoController::class, 'template'])->name('productos.template');
            Route::get('productos/plantilla-medidas', [ProductoController::class, 'plantillaMedidas'])->name('productos.plantilla.medidas');
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

        // Produccion (Jefe de Bodega): asignar y revisar reportes.
        Route::middleware('permission:manage production')->group(function () {
            Route::get('produccion', [ProduccionController::class, 'index'])->name('produccion.index');
            Route::get('produccion/asignar', [ProduccionController::class, 'asignar'])->name('produccion.asignar');
            Route::post('produccion/asignar', [ProduccionController::class, 'asignarStore'])->name('produccion.asignar.store');
            Route::get('produccion/reporte/{reporte}', [ProduccionController::class, 'reporteShow'])->name('produccion.reporte.show');
            Route::post('produccion/reporte/{reporte}/aprobar', [ProduccionController::class, 'aprobar'])->name('produccion.reporte.aprobar');
            Route::post('produccion/reporte/{reporte}/devolver', [ProduccionController::class, 'devolver'])->name('produccion.reporte.devolver');
            Route::post('produccion/reporte/{reporte}/ajustar', [ProduccionController::class, 'ajustar'])->name('produccion.reporte.ajustar');
        });
    });

// Mi produccion (Soplador): su reporte del dia.
Route::middleware(['auth', 'permission:report production'])
    ->prefix('produccion')
    ->name('produccion.')
    ->group(function () {
        Route::get('mi-reporte', [MiProduccionController::class, 'index'])->name('mi.index');
        Route::patch('mi-reporte/{reporte}', [MiProduccionController::class, 'update'])->name('mi.update');
    });

require __DIR__.'/auth.php';
