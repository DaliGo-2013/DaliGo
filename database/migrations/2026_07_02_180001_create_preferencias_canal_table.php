<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M15 · Opt-out por usuario/evento/canal (PLAN-M15 §1.2).
 *
 * Sin fila = default del canal (mail habilitado, whatsapp deshabilitado,
 * database siempre). Este modelo SI se audita (bajo volumen y significativo:
 * quien se dio de baja de que).
 *
 * MySQL 5.7: el unique compuesto usa evento a 100 chars para caber en el
 * prefijo de indice utf8mb4 (100*4 + 32*4 + 8 = 536 ≤ 767 bytes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_canal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('evento', 100);
            $table->string('canal', 32);
            $table->boolean('habilitado')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'evento', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_canal');
    }
};
