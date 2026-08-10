<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-M11-20: paradas con duracion + cavidades activas del turno.
     *
     * - produccion_paradas: que detuvo la produccion (motivo tipificado),
     *   de que recurso (maquina|operario) y entre que horas del turno.
     *   cliente_uuid replica el candado de idempotencia de la cola offline
     *   de las tandas (unique compuesto [reporte_id, cliente_uuid]; nullable
     *   a proposito: el camino nativo no manda uuid y los NULL no chocan en
     *   MySQL 5.7 ni SQLite). Las horas son TIME, reloj de pared del turno:
     *   la fecha vive en el reporte (precedente: hora/hora_fin de agenda).
     * - cavidades_activas: con cuantas cavidades del molde se trabajo el
     *   turno; NULL = todas (la entidad moldes llega en F3 y ahi tendra
     *   contra que validar).
     *
     * Idempotente (hasTable/hasColumn) por si un deploy interrumpido la deja
     * a medias. varchar con largo explicito (indice utf8mb4 en MySQL 5.7).
     */
    public function up(): void
    {
        if (! Schema::hasTable('produccion_paradas')) {
            Schema::create('produccion_paradas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
                $table->char('cliente_uuid', 36)->nullable();
                $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->nullOnDelete();
                $table->string('motivo', 64);
                $table->string('clase', 32);   // planificada | no_planificada (derivada del motivo)
                $table->string('origen', 32);  // maquina | operario
                $table->time('inicio');
                $table->time('fin')->nullable(); // NULL = la parada sigue abierta
                $table->boolean('cerrada_al_envio')->default(false);
                $table->timestamps();
                $table->unique(['reporte_id', 'cliente_uuid']);
            });
        }

        Schema::table('produccion_reportes', function (Blueprint $table) {
            if (! Schema::hasColumn('produccion_reportes', 'cavidades_activas')) {
                $table->unsignedTinyInteger('cavidades_activas')->nullable()->after('danada');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_paradas');

        Schema::table('produccion_reportes', function (Blueprint $table) {
            if (Schema::hasColumn('produccion_reportes', 'cavidades_activas')) {
                $table->dropColumn('cavidades_activas');
            }
        });
    }
};
