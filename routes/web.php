<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Diagnostico de despliegue: comprueba la conexion a la base de datos
    // sin romper la pagina si la BD aun no esta configurada.
    $db = ['ok' => false, 'name' => config('database.default'), 'detail' => null];

    try {
        $connection = DB::connection();
        $connection->getPdo();
        $db['ok'] = true;
        $db['detail'] = $connection->getDatabaseName();
    } catch (\Throwable $e) {
        $db['detail'] = $e->getMessage();
    }

    return view('welcome', [
        'phpVersion' => PHP_VERSION,
        'laravelVersion' => app()->version(),
        'appEnv' => app()->environment(),
        'db' => $db,
    ]);
});
