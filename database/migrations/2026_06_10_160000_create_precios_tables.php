<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Listas de precios (M02.2). Las listas y sus valores VIVEN en Bsale
     * (correccion #18: no rehacer lo que ya esta alla; ni siquiera existe POST
     * de listas en su API): DaliGo las espeja en solo-lectura para que las
     * cotizaciones (M05) tengan precio. Campo local: canal (la convencion
     * "una lista = un canal" no existe en Bsale; la define DaliGo).
     *
     * Los valores van en decimal(14,4): el neto real de Bsale llega como float
     * largo (bruto/1.19, ej. 0.8403...) y con 2 decimales se distorsionaria.
     */
    public function up(): void
    {
        Schema::create('listas_precios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->unsignedBigInteger('bsale_coin_id')->nullable(); // 1 = CLP
            $table->boolean('activa')->default(true);                // Bsale state 0 = activa
            // Campo local (Bsale no lo conoce; la sync jamas lo toca).
            $table->string('canal')->nullable();                     // convencion DaliGo: mayorista, retail, web…
            $table->unsignedBigInteger('bsale_price_list_id')->unique();
            $table->timestamps();
        });

        Schema::create('precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_precio_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('precio_neto', 14, 4)->nullable();
            $table->decimal('precio_con_iva', 14, 4)->nullable();
            $table->unsignedBigInteger('bsale_detail_id')->nullable();
            $table->timestamps();
            $table->unique(['lista_precio_id', 'producto_id']); // un precio por producto por lista
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios');
        Schema::dropIfExists('listas_precios');
    }
};
