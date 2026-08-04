<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Medidas ÚTILES de la caja de carga, para el simulador de carga.
     *
     * `vehiculos` nació para reemplazar la planilla «Control vehiculos», así que
     * trae lo administrativo (patente, vencimientos, carga máxima en kg) pero no
     * el espacio: y con botellones VACÍOS el límite es el volumen, no el peso —
     * 420 botellones son ~420 kg en un camión que lleva más de 1.400. Sin estas
     * tres columnas no se puede calcular nada.
     *
     * Se agregan como nullable y no se toca ninguna columna existente: la tabla
     * es de otra unidad y esto tiene que poder convivir sin romperla.
     *
     * "ÚTIL" significa medido POR DENTRO de la caja, no la ficha del fabricante:
     * la diferencia entre exterior e interior es del 10 al 20% del volumen, o
     * sea la diferencia entre que la carga entre o quede en el andén.
     *
     * `pasillo_cm` reserva el paso que la bodega necesita para cargar (en las
     * fotos de carga real se ve gente caminando adentro). Ese espacio existe
     * físicamente pero NO es capacidad: contarlo promete carga que después no se
     * puede meter.
     */
    public function up(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->unsignedSmallInteger('largo_util_cm')->nullable()->after('capacidad_carga_kg');
            $table->unsignedSmallInteger('ancho_util_cm')->nullable()->after('largo_util_cm');
            $table->unsignedSmallInteger('alto_util_cm')->nullable()->after('ancho_util_cm');
            $table->unsignedSmallInteger('pasillo_cm')->default(0)->after('alto_util_cm');
        });
    }

    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn(['largo_util_cm', 'ancho_util_cm', 'alto_util_cm', 'pasillo_cm']);
        });
    }
};
