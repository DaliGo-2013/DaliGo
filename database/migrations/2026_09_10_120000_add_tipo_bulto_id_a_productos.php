<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CÓMO VIAJA CADA PRODUCTO: el enlace que faltaba entre el catálogo y el simulador.
 *
 * Pedido del jefe de logística (10-09-2026): traer las facturas al simulador «para saber
 * su capacidad total». Las facturas ya estaban espejadas (`documento_venta_detalles`, con
 * `producto_id` y `cantidad`) y el simulador ya sabía convertir «200 botellones» en «40
 * bolsas» dividiendo por las unidades del bulto. Lo único que no existía era el dato del
 * medio: de qué bulto es cada producto. `tipos_bulto` no lo tiene a propósito —«NO es un
 * producto: un mismo SKU puede viajar de varias formas»—, así que el enlace va del lado
 * del producto, como su forma HABITUAL de viajar.
 *
 * Decisión del dueño (10-09-2026), entre tres opciones: una forma por defecto por producto,
 * editable en su ficha y cambiable línea por línea al importar. Ni varias formas por
 * producto (una tabla y una pantalla más, y una pregunta más en cada importación) ni
 * adivinar por nombre o categoría (un calce equivocado da un plan de carga con cara de
 * verificado). Y NULLABLE con intención: el producto que no tiene bulto declarado se LISTA
 * al importar, no se salta en silencio ni se inventa.
 *
 * `nullOnDelete`: si se borra un tipo de bulto, sus productos vuelven a «sin declarar» y
 * la próxima importación los va a nombrar — mejor que un enlace roto o una fila caída.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('tipo_bulto_id')
                ->nullable()
                ->after('largo_cm')
                ->constrained('tipos_bulto')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_bulto_id');
        });
    }
};
