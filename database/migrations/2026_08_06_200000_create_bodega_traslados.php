<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M04-F2 (PLAN-M04 §3-F2, P-M04-20): la orden de traslado del wizard de baja.
 * «Eliminar» una bodega jamás pierde stock — si contiene existencias, el
 * sistema crea esta orden (a dónde va lo que queda) y deja la bodega
 * `pendiente_traslado` hasta que un sync confirme stock 0.
 *
 * Los items son una FOTO al momento de la orden (cantidad, nombre y sku
 * denormalizados): el documento que viaja a bodega no cambia si el stock
 * sigue moviéndose o el catálogo renombra. Varchar explícito ≤191 (MySQL 5.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bodega_traslados', function (Blueprint $table) {
            $table->id();
            // restrict en ambas: una bodega con órdenes es historia operativa
            // (además las bodegas nunca se borran — baja lógica, PLAN §1).
            $table->foreignId('bodega_id')->constrained('bodegas')->restrictOnDelete();
            $table->foreignId('bodega_destino_id')->constrained('bodegas')->restrictOnDelete();
            // pendiente · completado · anulado (BodegaTraslado::ESTADOS).
            $table->string('estado', 32)->default('pendiente');
            // Quién pidió la baja: FK + nombre denormalizado (patrón
            // emisor_nombre de traslados_servicio — la orden impresa conserva
            // el nombre aunque el usuario se elimine).
            $table->foreignId('solicitante_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('solicitante_nombre', 191);
            // El cierre automático lo estampa el sync al confirmar stock 0.
            $table->timestamp('completado_at')->nullable();
            $table->timestamp('anulado_at')->nullable();
            // Guard del aviso «llegó stock a una bodega en baja»: una sola vez
            // por orden (el sync corre cada 15 min; sin esto sería spam ×96/día).
            $table->timestamp('aviso_stock_nuevo_at')->nullable();
            $table->timestamps();

            $table->index(['bodega_id', 'estado']);
        });

        Schema::create('bodega_traslado_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_traslado_id')->constrained('bodega_traslados')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            // La foto en texto: el documento no depende del catálogo vivo.
            $table->string('nombre', 191);
            $table->string('sku', 64)->nullable();
            // Misma precisión que el espejo `stocks`.
            $table->decimal('cantidad', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bodega_traslado_items');
        Schema::dropIfExists('bodega_traslados');
    }
};
