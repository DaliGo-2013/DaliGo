<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El vendedor asignado pasa a texto libre: muchas veces no es un usuario del
     * sistema. Se guarda el nombre escrito en la ficha (vendedor_nombre). Se
     * conserva vendedor_id (enlace opcional a un usuario) para no perder datos
     * previos ni la relacion; el formulario ahora usa el texto libre.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('vendedor_nombre')->nullable()->after('vendedor_id');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('vendedor_nombre');
        });
    }
};
