<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeders base de la app (todos idempotentes; seguro re-ejecutar en cada deploy).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SucursalSeeder::class,
            ConfiguracionSeeder::class,
            TipoBotellonSeeder::class,
            MaquinaSeeder::class,
            // Despues de TipoBotellonSeeder: enlaza los tipos a sus productos.
            ProduccionTesteoSeeder::class,
        ]);
    }
}
