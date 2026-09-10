<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hitos de la página /plan (P-PLAN-06, pedido del dueño 10-09-2026): dejan de
 * ser una constante del repo —cada cambio del gerente costaba commit + deploy—
 * y pasan a BD para agregarse y editarse desde la pantalla. Se siembran UNA vez
 * desde PlanProyecto::HITOS (PlanHitosSeeder, firstOrCreate por clave: el
 * deploy no pisa lo que se editó en la UI). DDL trivial para MySQL 5.7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_hitos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 12)->unique(); // "H1'", "H8"…
            $table->string('etiqueta');
            $table->date('fecha');
            $table->boolean('cumplido')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_hitos');
    }
};
