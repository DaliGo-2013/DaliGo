<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M04-F1 (PLAN-M04 §2, P-M04-10): la capa LOCAL de clasificación sobre el
 * espejo `bodegas`. Aditiva a propósito: StockSync::upsertBodega hace upsert
 * por campos explícitos y NO conoce estas columnas, así que el sync horario
 * jamás las pisa (mismo patrón que `listas_precios.canal`).
 *
 * `estado_baja` se crea YA aunque la usa recién F2 (wizard de baja con
 * traslado), para no migrar la misma tabla dos veces — dictado v36.
 *
 * Varchar con largo EXPLÍCITO ≤191: MySQL 5.7 + utf8mb4 (SQLite no avisa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bodegas', function (Blueprint $table) {
            // NULL = transversal (MERMAS, RESERVA SUCURSALES). restrictOnDelete
            // de cinturón: la guarda amable vive en SucursalController::destroy
            // (misma doctrina que maquinas.sucursal_id).
            $table->foreignId('sucursal_id')->nullable()->after('bsale_office_id')
                ->constrained('sucursales')->restrictOnDelete();
            // fisica · virtual_operativa · transito · insumos · taller · cerrada
            // (Bodega::PROPOSITOS). NULL = llegó del sync y nadie la clasificó.
            $table->string('proposito', 191)->nullable()->after('sucursal_id');
            // false = invisible en pantallas/selectores operativos (scope
            // enOperacion()). Distinto de `activa`, que es el espejo del state
            // de Bsale y lo escribe el sync.
            $table->boolean('en_operacion')->default(true)->after('proposito');
            // false = pre-carga del seeder o bodega recién adoptada del sync;
            // true = un humano guardó la ficha desde la app (la UI manda).
            $table->boolean('clasificacion_confirmada')->default(false)->after('en_operacion');
            // null · pendiente_traslado · dada_de_baja — la escribe F2.
            $table->string('estado_baja', 32)->nullable()->after('clasificacion_confirmada');
            // Nombre local amistoso; el `nombre` oficial sigue siendo de Bsale.
            $table->string('alias', 191)->nullable()->after('estado_baja');
        });
    }

    public function down(): void
    {
        Schema::table('bodegas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sucursal_id');
            $table->dropColumn(['proposito', 'en_operacion', 'clasificacion_confirmada', 'estado_baja', 'alias']);
        });
    }
};
