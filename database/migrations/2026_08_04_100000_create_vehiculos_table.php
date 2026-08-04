<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flota de vehículos (módulo LOGÍSTICA, pedido del dueño 04-08-2026).
 *
 * Reemplaza la planilla «Control vehiculos» (Vehiculos 2026.xlsx). Dos cosas
 * que la planilla mezclaba y acá quedan separadas a propósito:
 *
 * 1. ESTADO vs CONDUCTOR. En el Excel la columna "CONDUCTOR ASIGNADO" hacía
 *    doble uso: a veces el chofer ("PEDRO CASTILLO") y a veces el estado del
 *    vehículo ("PERDIDA TOTAL", "VENTA FEBRERO 2023", "NO ASIGNADO"). Con eso
 *    no se puede contar la flota ni filtrar: acá van `estado` + `baja_motivo`
 *    por un lado y `conductor_nombre` por otro.
 * 2. BASE vs SUCURSAL. La planilla usa 7 valores en su columna "SUCURSAL"
 *    (Mirador, Coquimbo, Abate Molina, Concepción, Damimed, Jefaturas,
 *    Antofagasta) y solo 3 son sucursales de DaliGo. `base` es texto con lista
 *    sugerida y NO una FK: enlazarla obligaría a inventar sucursales falsas
 *    que aparecerían en Servicio Técnico, Producción y Despachos, donde no
 *    operan (decisión del dueño 04-08).
 *
 * Las 4 fechas de documentos son el corazón del registro: son las celdas que
 * en el Excel están pintadas a mano. Nullable = sin dato (no alerta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('ppu', 12)->unique();          // patente
            $table->string('alias', 191)->nullable();     // "RAM PEDRO", "HD35 COQ" — como lo nombran en la operación
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 120)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('tipo', 20)->default('camioneta');
            $table->string('combustible', 20)->nullable();
            $table->string('vin', 40)->nullable();        // VIN / chasis
            $table->string('numero_motor', 40)->nullable();

            // Dimensiones y capacidades
            $table->unsignedInteger('cilindrada')->nullable();
            $table->unsignedInteger('pbv_kg')->nullable();
            $table->unsignedInteger('capacidad_carga_kg')->nullable();
            $table->unsignedSmallInteger('presion_psi')->nullable();

            // Asignación
            $table->string('base', 40)->nullable()->index();
            $table->string('conductor_nombre', 191)->nullable();

            // Estado del vehículo (activo / vendido / baja), separado del chofer
            $table->string('estado', 12)->default('activo')->index();
            $table->date('baja_at')->nullable();
            $table->string('baja_motivo', 191)->nullable();

            // Documentos con vencimiento — lo que la planilla pinta a mano
            $table->date('rt_vence')->nullable();
            $table->date('emisiones_vence')->nullable();
            $table->date('permiso_circulacion_vence')->nullable();
            $table->date('soap_vence')->nullable();

            // Extintor (mantención y capacidad)
            $table->date('extintor_vence')->nullable();
            $table->decimal('extintor_capacidad_kg', 4, 1)->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
