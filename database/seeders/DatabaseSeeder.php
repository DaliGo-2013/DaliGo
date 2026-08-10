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
            // Despues de SucursalSeeder: la pre-carga D-003 asigna bodegas a
            // sucursales por codigo. Solo clasifica lo que el sync ya espejo
            // y JAMAS pisa una fila confirmada desde la UI (M04-F1).
            ClasificacionBodegasSeeder::class,
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
            // Despues de ProduccionTesteoSeeder: la hipotesis [B] de recetas
            // (1 preforma + 1 tapa por botellon) para los botellones ya
            // enlazados. JAMAS pisa una fila existente (editable via UI, D-003).
            RecetaSeeder::class,
            // DESPACHOS-v1: catalogo de zonas comerciales (D-006).
            ZonaSeeder::class,
            // Bultos del simulador de carga (medidas reales, idempotente).
            TiposBultoSeeder::class,
            // Camiones del simulador (catálogo PROPIO, separado de la flota —
            // decisión del dueño 05-08): el deploy los crea solo, sin pasos
            // manuales por phpMyAdmin.
            CamionesSimulacionSeeder::class,
        ]);
    }
}
