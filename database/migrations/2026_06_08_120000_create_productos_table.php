<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalogo maestro local de DaliGo, a nivel SKU (= variante en Bsale).
     * Guarda lo que Bsale NO tiene y necesitamos para cotizar despacho:
     * peso y dimensiones. Enlazable a Bsale por bsale_variant_id/bsale_product_id
     * (la sincronizacion real se hara en un incremento posterior).
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();        // SKU = Bsale variants.code
            $table->string('nombre');                    // 191 por defaultStringLength
            $table->text('descripcion')->nullable();
            $table->string('categoria')->nullable()->index();
            $table->string('marca')->nullable()->index();
            $table->decimal('peso_kg', 10, 3)->nullable();
            $table->decimal('alto_cm', 10, 2)->nullable();
            $table->decimal('ancho_cm', 10, 2)->nullable();
            $table->decimal('largo_cm', 10, 2)->nullable();
            $table->json('atributos')->nullable();       // metadata; 5.7 soporta JSON
            $table->boolean('activo')->default(true);
            $table->unsignedBigInteger('bsale_variant_id')->nullable()->index();
            $table->unsignedBigInteger('bsale_product_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
