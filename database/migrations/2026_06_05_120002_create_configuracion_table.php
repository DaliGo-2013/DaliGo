<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuracion global de DaliGo: parametros tipados clave/valor, editables
     * desde la UI. El valor siempre se almacena como texto (incluido JSON) y se
     * castea segun `tipo` en el modelo. MySQL 5.7: sin columna json nativa.
     */
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();          // 191 por defaultStringLength global
            $table->text('valor')->nullable();
            $table->string('tipo');                      // string|integer|decimal|boolean|json
            $table->string('grupo');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
