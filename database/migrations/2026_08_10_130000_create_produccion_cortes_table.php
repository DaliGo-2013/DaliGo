<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-M11-21: bitacora de los cortes SIC. Cada corte (cada 2 h, por reporte
     * en curso) deja UNA fila con su proyeccion y si disparo aviso — es la
     * memoria que hace posible el escalamiento (2 cortes seguidos bajo umbral
     * => urgente) y el anti-spam (3er corte igual => silencio), ademas del
     * semaforo del panel vivo.
     *
     * corte_slot es el INSTANTE UTC del slot (startOfHour del schedule): Chile
     * tiene DST, asi que una "hora bonita" chilena seria ambigua el dia del
     * cambio. El unique [reporte_id, corte_slot] es el candado que evita que
     * una re-corrida del mismo slot duplique avisos (patron vehiculo_avisos).
     *
     * Idempotente (hasTable) por si un deploy interrumpido la deja a medias.
     */
    public function up(): void
    {
        if (Schema::hasTable('produccion_cortes')) {
            return;
        }

        Schema::create('produccion_cortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
            $table->dateTime('corte_slot'); // instante UTC del slot del scheduler
            $table->boolean('bajo_umbral');
            $table->unsignedSmallInteger('proyeccion'); // % clampeado a 999
            $table->boolean('avisado')->default(false);
            $table->boolean('urgente')->default(false);
            $table->timestamps();
            $table->unique(['reporte_id', 'corte_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_cortes');
    }
};
