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
            // Despues de ConfiguracionSeeder: las reglas apuntan a claves de config.
            ReglasAprobacionSeeder::class,
            TipoBotellonSeeder::class,
            MaquinaSeeder::class,
            // Catálogo de servicios de terreno (solo crea lo que falte: es
            // editable desde la app y el deploy no debe pisar las ediciones).
            ServiciosTerrenoSeeder::class,
            // Tiempos estándar de reparación (horas por trabajo): mano de obra
            // fija que el técnico no edita. Editable por jefatura; no se pisa.
            TiemposReparacionSeeder::class,
            // Conductores iniciales (idempotente; editable desde la app).
            ConductoresSeeder::class,
            // Despues de TipoBotellonSeeder: enlaza los tipos a sus productos.
            ProduccionTesteoSeeder::class,
        ]);
    }
}
