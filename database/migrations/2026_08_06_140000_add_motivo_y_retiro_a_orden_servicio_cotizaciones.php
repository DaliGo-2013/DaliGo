<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos ampliaciones del flujo de cotización pedidas por el dueño el 06-08:
 *
 * 1. `respuesta_motivo`: el «¿por qué?» que el cliente puede escribir junto a su
 *    ACEPTO / NO ACEPTO. Da vuelta la decisión del 30-07 («sin comentario, para
 *    evitar el ida y vuelta»): el dueño ahora quiere leer el motivo — sigue
 *    siendo un campo de UNA pasada (no abre conversación), y es opcional.
 *
 * 2. `retiro_avisado_*`: cuando el cliente NO acepta, alguien del equipo le
 *    avisa por correo que puede pasar a retirar su equipo sin reparar. El
 *    timestamp evita avisos duplicados y deja registrado quién avisó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'respuesta_motivo')) {
                $table->text('respuesta_motivo')->nullable()->after('respuesta_user_agent');
            }
            if (! Schema::hasColumn('orden_servicio_cotizaciones', 'retiro_avisado_at')) {
                $table->timestamp('retiro_avisado_at')->nullable()->after('autorizada_por');
                $table->foreignId('retiro_avisado_por')->nullable()->after('retiro_avisado_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retiro_avisado_por');
            $table->dropColumn(['respuesta_motivo', 'retiro_avisado_at']);
        });
    }
};
