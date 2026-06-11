<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventario (M04 Inc 1). El stock vive en Bsale por bodega (office);
     * DaliGo lo espeja en solo-lectura para ver disponibilidad sin entrar a
     * Bsale. `bodegas` es el espejo de las offices de Bsale (16 en la cuenta
     * DALI, distintas de las 4 `sucursales` que son el concepto de DaliGo para
     * usuarios). `stocks` es la matriz bodega × producto con los 3 contadores.
     *
     * Cantidades en decimal(14,4): Bsale permite stock fraccionado para
     * unidades divisibles; no perder precisión.
     */
    public function up(): void
    {
        Schema::create('bodegas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('comuna')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('email')->nullable();
            $table->boolean('es_virtual')->default(false);     // Bsale isVirtual
            $table->boolean('activa')->default(true);           // Bsale state 0 = activa
            $table->unsignedBigInteger('bsale_default_price_list_id')->nullable(); // defaultPriceList
            $table->unsignedBigInteger('bsale_office_id')->unique();
            $table->timestamps();
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('stock_real', 14, 4)->default(0);       // Bsale quantity
            $table->decimal('stock_reservado', 14, 4)->default(0);  // quantityReserved
            $table->decimal('stock_disponible', 14, 4)->default(0); // quantityAvailable
            $table->unsignedBigInteger('bsale_stock_id')->nullable();
            $table->timestamps();
            $table->unique(['bodega_id', 'producto_id']); // un registro por producto por bodega
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('bodegas');
    }
};
