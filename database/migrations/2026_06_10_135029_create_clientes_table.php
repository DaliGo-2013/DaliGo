<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ficha local de clientes (M03). Los clientes viven en Bsale (correccion #18:
     * no rehacer lo que ya esta alla); DaliGo los espeja por bsale_client_id y
     * agrega lo que Bsale no tiene: segmento, notas y vendedor asignado (cartera,
     * correccion #2). El rut es nullable porque Bsale trae clientes sin RUT
     * (boleta historica); multiples NULL caben en el indice unique.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 20)->nullable()->unique();   // normalizado: 12345678-9 (sin puntos, K mayuscula)
            $table->string('razon_social');                     // empresa: company; persona: nombre y apellido
            $table->string('giro')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('comuna')->nullable();
            $table->boolean('es_empresa')->default(false);      // Bsale companyOrPerson
            $table->boolean('envio_factura_email')->default(false); // Bsale sendDte: la verdad del envio de DTE vive alla
            $table->boolean('activo')->default(true);
            // Campos locales (Bsale no los conoce; la sync jamas los toca).
            $table->string('segmento')->nullable()->index();    // mayorista | retail | recurrente
            $table->text('notas')->nullable();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('bsale_client_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
