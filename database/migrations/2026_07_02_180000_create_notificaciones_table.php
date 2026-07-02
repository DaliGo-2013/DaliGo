<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M15 · Nucleo de notificaciones (PLAN-M15 §1.2).
 *
 * Una fila por (evento disparado × canal). La fila ES su propia traza
 * (estado/intentos/ultimo_error/timestamps) — por eso el modelo NO se audita
 * (convencion del repo para tablas de alto volumen).
 *
 * MySQL 5.7: strings indexados ≤191 (utf8mb4: 191*4=764 ≤ 767 bytes de prefijo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();

            // Clave del catalogo Notificacion::EVENTOS (ej. 'sistema.prueba').
            $table->string('evento', 191)->index();

            // Objeto de origen del evento (polimorfica, opcional).
            $table->nullableMorphs('notificable');

            // Destinatario: usuario interno y/o direccion externa (email/telefono).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destinatario', 191)->nullable();

            $table->string('canal', 32);   // mail | database | whatsapp
            $table->string('titulo', 191); // asunto ya renderizado
            $table->text('cuerpo');        // cuerpo ya renderizado
            $table->text('payload')->nullable(); // JSON serializado (datos del evento)

            // pendiente | enviada | fallida | leida (solo canal database).
            $table->string('estado', 32)->default('pendiente');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->timestamp('programada_para')->nullable(); // proximo reintento (backoff)
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('leida_at')->nullable();

            $table->timestamps();

            // Contador de la campanita (no-leidas del usuario por canal).
            $table->index(['user_id', 'canal', 'estado']);
            // Barrido del comando de reintentos.
            $table->index(['estado', 'programada_para']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
