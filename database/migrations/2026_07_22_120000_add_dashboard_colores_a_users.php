<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Color de las cards de accesos del Inicio, personalizable POR USUARIO (M16,
 * D-013). TEXT y no ->json(): MySQL 5.7 (patrón de `configuraciones`); el
 * cast 'array' del modelo serializa/deserializa el mapa {card => color}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('dashboard_colores')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_colores');
        });
    }
};
