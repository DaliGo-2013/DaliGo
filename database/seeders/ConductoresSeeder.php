<?php

namespace Database\Seeders;

use App\Models\Conductor;
use Illuminate\Database\Seeder;

/**
 * Siembra los conductores iniciales desde config (los que estaban fijos antes).
 * firstOrCreate → idempotente y NO pisa ediciones hechas desde la app (si el
 * dueño renombra/desactiva uno, el deploy no lo revierte).
 */
class ConductoresSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('servicio_tecnico.conductores', []) as $nombre) {
            Conductor::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
