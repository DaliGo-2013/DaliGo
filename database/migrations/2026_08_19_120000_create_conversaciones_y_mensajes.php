<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MSG-1 (PLAN-MENSAJES): chat interno 1-a-1 entre usuarios.
     *
     * - `conversaciones`: UNA fila por PAR de usuarios, canonico (menor id /
     *   mayor id) con unique — (A,B) y (B,A) son la misma conversacion.
     *   Los contadores de no-leidos van POR LADO y denormalizados: enviar
     *   suma +1 al del otro (bajo lock), abrir el hilo pone el mio en 0.
     *   Asi la lista, los no-leidos y el badge del menu salen de queries
     *   indexadas sobre esta tabla (sin GROUP BY por par en MySQL 5.7).
     * - `mensajes`: append-only. El emisor va nullOnDelete (el mensaje
     *   sobrevive al usuario, se pinta «—»); la conversacion en cambio es
     *   cascadeOnDelete DESDE users a proposito: nullOnDelete romperia el
     *   unique del par canonico, y en la practica los usuarios se desactivan
     *   quitandoles roles, no se borran (anexo §5.1, riesgo declarado).
     * - Varchar con largo explicito (MySQL 5.7 + utf8mb4; SQLite no avisa).
     *
     * Idempotente (hasTable) por si un deploy interrumpido la deja a medias.
     */
    public function up(): void
    {
        if (! Schema::hasTable('conversaciones')) {
            Schema::create('conversaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_menor_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('user_mayor_id')->constrained('users')->cascadeOnDelete();
                // Orden de la lista «mis conversaciones»; NULL = sin mensajes aun.
                $table->timestamp('ultimo_mensaje_at')->nullable();
                $table->unsignedInteger('no_leidos_menor')->default(0);
                $table->unsignedInteger('no_leidos_mayor')->default(0);
                $table->timestamps();

                $table->unique(['user_menor_id', 'user_mayor_id']);
                // «Mis conversaciones ordenadas por ultimo mensaje», por lado.
                $table->index(['user_menor_id', 'ultimo_mensaje_at']);
                $table->index(['user_mayor_id', 'ultimo_mensaje_at']);
            });
        }

        if (! Schema::hasTable('mensajes')) {
            Schema::create('mensajes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversacion_id')->constrained('conversaciones')->cascadeOnDelete();
                $table->foreignId('emisor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('texto', 1000);
                $table->timestamps();

                // Historial del hilo paginado por id.
                $table->index(['conversacion_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
        Schema::dropIfExists('conversaciones');
    }
};
