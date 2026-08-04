<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de BULTOS para el simulador de carga (Logística).
     *
     * Un "tipo de bulto" es lo que se sube al camión de una vez, NO el producto:
     * los botellones viajan en bolsas de 5, así que la unidad de carga es la
     * bolsa y no el botellón (aclaración del dueño 04-08-2026). Por eso esta
     * tabla es aparte de `productos`: un mismo SKU puede viajar de varias formas
     * y lo que el cálculo necesita es la caja envolvente REAL del bulto armado,
     * medida en la posición en la que viaja.
     *
     * Las medidas van en CENTÍMETROS enteros a propósito. Nadie mide un pallet
     * con décimas de milímetro, el dueño advirtió que varían "uno o dos
     * centímetros", y el cálculo es por división entera: guardar más precisión
     * de la que existe sugiere una exactitud falsa.
     */
    public function up(): void
    {
        Schema::create('tipos_bulto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 191);
            $table->string('categoria', 40)->nullable()->index();   // botellones / cajas / maquinas / dispensadores

            // Caja envolvente del bulto ARMADO, como viaja.
            $table->unsignedSmallInteger('largo_cm');
            $table->unsignedSmallInteger('ancho_cm');
            $table->unsignedSmallInteger('alto_cm');
            $table->decimal('peso_kg', 8, 2)->nullable();

            /*
             * Unidades vendibles que contiene el bulto: 5 para la bolsa de
             * botellones, 1 para una caja. Es lo que convierte "28 bolsas" en
             * "140 botellones", que es el número con el que habla el vendedor.
             */
            $table->unsignedSmallInteger('unidades')->default(1);

            /*
             * Apilado. `apilable_max` = cuántos van uno encima del otro (1 = va
             * solo al piso). `soporta_peso_encima` = si se le puede poner OTRA
             * cosa arriba: las máquinas en jaula traen impreso "keep off / box
             * lid may collapse", así que es false y el cálculo debe respetarlo.
             */
            $table->unsignedSmallInteger('apilable_max')->default(1);
            $table->boolean('soporta_peso_encima')->default(false);

            /*
             * Orientación fija: los botellones van acostados con el pico hacia la
             * puerta y las jaulas de máquina a lo largo del costado. Cuando es
             * true, el cálculo NO prueba las 6 rotaciones — usa las medidas tal
             * como están cargadas. Probar rotaciones en un bulto que en la
             * práctica va fijo infla la capacidad (medido: hasta 24% en el HD35).
             */
            $table->boolean('orientacion_fija')->default(false);

            /*
             * Mercancía peligrosa (ej. baterías de litio UN3480, vistas en fotos
             * de carga el 31-jul). NO es un problema de espacio sino de
             * cumplimiento: el simulador debe avisar y no ofrecer rellenar
             * alrededor, porque la respuesta puede ser "cabe pero no se permite".
             */
            $table->boolean('peligrosa')->default(false);
            $table->string('peligrosa_codigo', 20)->nullable();

            $table->boolean('activo')->default(true)->index();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_bulto');
    }
};
