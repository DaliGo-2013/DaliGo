<?php

use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ProfileController;
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
    });

require __DIR__.'/auth.php';
