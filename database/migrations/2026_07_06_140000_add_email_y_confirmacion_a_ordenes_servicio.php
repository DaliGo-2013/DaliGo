<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingreso publico por QR (P-M12-01, piloto). Dos campos:
     *
     * - cliente_email: snapshot del correo del cliente en la orden, igual que
     *   cliente_nombre/rut/telefono. Es el correo al que se manda el detalle del
     *   folio cuando el encargado confirma la recepcion. Nullable: los ingresos
     *   de mostrador previos no lo traen.
     * - confirmada_at: momento en que el encargado valido y recibio fisicamente
     *   la maquina que llego por QR. Null = "llego por QR, falta confirmar". NO es
     *   un estado paralelo (la orden ya es 'recibido' desde el envio); solo marca
     *   si el encargado ya la reviso.
     *
     * Idempotente (hasColumn) porque la 095/telefono ensenio que puede faltar
     * una migracion intermedia en algun entorno.
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'cliente_email')) {
                $table->string('cliente_email', 191)->nullable()->after('cliente_telefono');
            }
            if (! Schema::hasColumn('ordenes_servicio', 'confirmada_at')) {
                $table->timestamp('confirmada_at')->nullable()->after('fecha_entrega');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_servicio', 'cliente_email')) {
                $table->dropColumn('cliente_email');
            }
            if (Schema::hasColumn('ordenes_servicio', 'confirmada_at')) {
                $table->dropColumn('confirmada_at');
            }
        });
    }
};
