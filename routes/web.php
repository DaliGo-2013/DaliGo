<?php

use App\Http\Controllers\Admin\ProduccionController;
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
