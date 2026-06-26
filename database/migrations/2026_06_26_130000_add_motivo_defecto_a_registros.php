<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motivo del defecto por tanda: por que las preformas de esa tanda salieron
     * de segunda o malas (ej. burbujas, rebaba, cuello deforme). Lista cerrada
     * (ProduccionRegistro::MOTIVOS_DEFECTO). Vive por tanda; el operario lo
     * elige al cargar las cantidades. Nullable: solo aplica cuando hay segunda
     * o malo > 0; las tandas viejas y las sin defecto quedan en null.
     */
    public function up(): void
    {
        Schema::table('produccion_registros', function (Blueprint $table) {
            $table->string('motivo_segunda')->nullable()->after('segunda');
            $table->string('motivo_malo')->nullable()->after('malo');
        });
    }

    public function down(): void
    {
        Schema::table('produccion_registros', function (Blueprint $table) {
            $table->dropColumn(['motivo_segunda', 'motivo_malo']);
        });
    }
};
