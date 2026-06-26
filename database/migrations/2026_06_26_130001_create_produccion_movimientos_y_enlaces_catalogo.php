<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M11 — Conexion de la produccion con el catalogo (kardex local).
 *
 * Aditiva y MySQL 5.7-safe (sin ENUM nativo: `tipo` es string con constantes de
 * clase; sin CTE/window). Enlaza preforma/botellon al catalogo `productos`
 * (nullable: degrada con gracia y no rompe historicos) y crea el kardex local
 * `produccion_movimientos`, que SOLO se escribe al aprobar y NUNCA toca el
 * espejo `stocks`/`bodegas` de Bsale.
 */
return new class extends Migration
{
    public function up(): void
    {
        // a) Botellon -> producto del catalogo.
        Schema::table('tipos_botellon', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('nombre')
                ->constrained('productos')->nullOnDelete();
        });

        // b) Preforma del turno -> producto del catalogo.
        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            $table->foreignId('preforma_id')->nullable()->after('asignadas')
                ->constrained('productos')->nullOnDelete();
        });

        // c) Kardex local de produccion.
        Schema::create('produccion_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('tipo'); // consumo_preforma | produccion_primera | produccion_segunda | merma
            $table->unsignedInteger('cantidad');
            $table->date('fecha'); // fecha del reporte (reporteria por dia)
            $table->timestamps();
            $table->index(['producto_id', 'fecha']);
            $table->index('reporte_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produccion_movimientos');

        Schema::table('produccion_asignaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preforma_id');
        });

        Schema::table('tipos_botellon', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
        });
    }
};
