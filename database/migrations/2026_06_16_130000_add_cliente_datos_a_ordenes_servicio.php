<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El nombre y el RUT del cliente se guardan en la propia orden (snapshot) para
     * que queden archivados en el historial aunque la persona no exista en el
     * catalogo de Clientes. Si existe, el formulario los autocompleta y ademas
     * enlaza por cliente_id; si no, se escriben a mano. Nullable en BD por las
     * filas previas; la obligatoriedad se exige en la validacion del formulario.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->string('cliente_nombre')->nullable()->after('cliente_id');
            $table->string('cliente_rut', 20)->nullable()->index()->after('cliente_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn(['cliente_nombre', 'cliente_rut']);
        });
    }
};
