<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La máquina y el tipo de botellón pasan a decidirse en la ASIGNACIÓN
     * (dueño 09-09): el jefe los fija al asignar y el soplador ya no elige.
     * Cada tanda y cada parada del reporte los heredan de acá.
     *
     * Nullable a propósito: las asignaciones históricas no los tienen, y una
     * planta sin catálogo de máquinas/tipos sigue pudiendo asignar (mismo
     * criterio que produccion_registros). nullOnDelete: borrar una máquina o
     * un tipo no se lleva la asignación. Idempotente.
     */
    public function up(): void
    {
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('produccion_asignaciones', 'maquina_id')) {
                $table->foreignId('maquina_id')->nullable()->after('procedencia')
                    ->constrained('maquinas')->nullOnDelete();
            }
            if (! Schema::hasColumn('produccion_asignaciones', 'tipo_botellon_id')) {
                $table->foreignId('tipo_botellon_id')->nullable()->after('maquina_id')
                    ->constrained('tipos_botellon')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('produccion_asignaciones', 'tipo_botellon_id')) {
                $table->dropConstrainedForeignId('tipo_botellon_id');
            }
            if (Schema::hasColumn('produccion_asignaciones', 'maquina_id')) {
                $table->dropConstrainedForeignId('maquina_id');
            }
        });
    }
};
