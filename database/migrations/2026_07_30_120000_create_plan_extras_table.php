<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trabajos extras en paralelo de la página /plan (carta Gantt): lo único de
 * esa página que se edita en la UI — el plan oficial se parsea del repo.
 * DDL trivial para MySQL 5.7: strings a 191 por defaultStringLength, sin
 * índices nuevos (tabla chica, se lista completa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_extras', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            // Lista cerrada en PlanExtra::ESTADOS (Rule::in), no enum de BD.
            $table->string('estado')->default('no_iniciada');
            $table->unsignedTinyInteger('avance')->default(0); // 0–100
            $table->string('responsable')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_extras');
    }
};
