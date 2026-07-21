<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones enviadas al cliente desde una orden de servicio (P-M12-02, fase
 * correo). Cada fila es un SNAPSHOT congelado de lo cotizado al momento del
 * envío (si después editan la orden, la carta enviada no cambia). Los re-envíos
 * crean fila nueva y marcan la anterior como 'reemplazada' — historial completo
 * de autorizaciones. El cliente responde ACEPTO / NO ACEPTO por un link firmado
 * (sin comentario: decisión del dueño para evitar el ida y vuelta).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orden_servicio_cotizaciones')) {
            return;
        }

        Schema::create('orden_servicio_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            // Identificador público no enumerable (va en el link firmado del correo).
            $table->string('token', 64)->unique();
            $table->string('estado', 20)->default('enviada'); // enviada|aceptada|rechazada|reemplazada

            // --- Snapshot congelado al enviar (la carta no cambia si editan la orden) ---
            $table->string('cliente_email', 191);
            $table->string('trabajo_realizado', 191)->nullable();
            $table->string('causa_falla', 191)->nullable();
            $table->text('repuestos')->nullable(); // JSON [{nombre,cantidad,precio_unitario,subtotal}]
            $table->unsignedInteger('mano_obra')->default(0);
            $table->unsignedTinyInteger('descuento_pct')->default(0);
            $table->string('descuento_motivo', 30)->nullable();
            $table->unsignedInteger('costo_repuestos')->default(0);
            $table->unsignedInteger('costo_bruto')->default(0);
            $table->unsignedInteger('descuento_monto')->default(0);
            $table->unsignedInteger('costo_total')->default(0);

            $table->timestamp('vence_at')->nullable();
            // Null = el SMTP falló al enviar → botón "Reintentar" en la pantalla.
            $table->timestamp('correo_enviado_at')->nullable();

            // --- Respuesta del cliente (solo ACEPTO / NO ACEPTO) ---
            $table->timestamp('respondida_at')->nullable();
            $table->string('respuesta_ip', 45)->nullable();
            $table->string('respuesta_user_agent', 191)->nullable();

            $table->foreignId('enviada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['orden_servicio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_cotizaciones');
    }
};
