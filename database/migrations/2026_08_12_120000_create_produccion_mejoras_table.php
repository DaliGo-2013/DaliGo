<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-M11-23: kaizen digital. El soplador propone una mejora desde
     * mi-reporte; el jefe la revisa/aplica/descarta con respuesta opcional
     * desde el panel. Conversacion estructurada que vive en las pantallas
     * (sin M14 ni M15 a proposito).
     *
     * - soplador_id nullOnDelete: si el usuario se elimina, la propuesta
     *   queda (es historia operativa), sin autor.
     * - cliente_uuid char(36) nullable + unique compuesto: idempotencia de
     *   la cola offline (mismo contrato que produccion_paradas; los NULL
     *   del camino nativo no chocan en MySQL 5.7 ni SQLite).
     * - Varchar con largo explicito <=191 (MySQL 5.7 + utf8mb4; SQLite no avisa).
     *
     * Idempotente (hasTable) por si un deploy interrumpido la deja a medias.
     */
    public function up(): void
    {
        if (Schema::hasTable('produccion_mejoras')) {
            return;
        }

        Schema::create('produccion_mejoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soplador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('cliente_uuid', 36)->nullable();
            $table->string('texto', 191);
            $table->string('estado', 32)->default('pendiente');
            $table->string('respuesta', 191)->nullable();
            $table->timestamps();
            $table->unique(['soplador_id', 'cliente_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_mejoras');
    }
};
