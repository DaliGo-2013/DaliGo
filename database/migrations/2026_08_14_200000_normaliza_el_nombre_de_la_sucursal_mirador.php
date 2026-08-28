<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La casa matriz se llama «Mirador», no «El Mirador».
 *
 * Dueño, 14-08-2026: «si se llama MIRADOR en realidad es su nombre oficial, mi compañero se
 * equivoco en el momento de crear y le puso EL MIRADOR». El nombre se ve en el correo de
 * ingreso («Sucursal»), en el de retiro («Donde») y en la pantalla del QR, o sea que el error
 * de tipeo le llega al cliente.
 *
 * POR QUE UNA MIGRACION Y NO EL SEEDER: SucursalSeeder es `firstOrCreate` por codigo — no pisa
 * lo editado desde la UI, y eso es a proposito (nadie quiere que un deploy le deshaga una
 * correccion). Con lo cual un nombre ya guardado en produccion no se arregla solo: hay que
 * pedirlo una vez.
 *
 * SOLO TOCA `nombre`, Y SOLO DE LA FILA CON CODIGO MIRADOR. Es un rotulo: nada en la app busca
 * sucursales por nombre — el plazo de reparacion y el selector del QR van por `codigo`
 * (config/servicio_tecnico.php) y las bases de la flota son texto propio (Vehiculo::BASES).
 *
 * Y NO RENOMBRA una fila que se llame «El Mirador» con OTRO codigo: si existe una segunda
 * ficha duplicada, renombrarla dejaria dos «Mirador» y taparia el problema de fondo — que es
 * justo el que hace que sus ordenes prometan 15 dias habiles en vez de 10
 * (docs/reglas/plazo-de-reparacion.md). Ese caso se resuelve mirando el listado, no a ciegas.
 *
 * One-shot e idempotente: escribe el mismo valor si ya esta bien. Sin `where` sobre el nombre
 * a proposito: en MySQL la comparacion de texto es case-insensitive por defecto, asi que un
 * `!= 'Mirador'` no distinguiria un «MIRADOR» en mayusculas y lo dejaria pasar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sucursales')->where('codigo', 'MIRADOR')->update(['nombre' => 'Mirador']);
    }

    public function down(): void
    {
        // No se revierte: volver a «El Mirador» seria reponer el error de tipeo.
    }
};
