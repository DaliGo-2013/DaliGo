<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P-M11-22: notas del jefe para los sopladores. Un mensaje operativo que
     * se pinta en mi-reporte del destinatario mientras este vigente (sin M15:
     * la nota vive en la pantalla, no persigue a nadie).
     *
     * - soplador_id NULL = para TODOS los sopladores.
     * - vigente_desde/hasta NULL = sin limite por ese borde.
     * - Varchar con largo explicito <=191 (MySQL 5.7 + utf8mb4; SQLite no avisa).
     *
     * Idempotente (hasTable) por si un deploy interrumpido la deja a medias.
     */
    public function up(): void
    {
        if (Schema::hasTable('produccion_notas')) {
            return;
        }

        Schema::create('produccion_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('soplador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('texto', 191);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_notas');
    }
};
