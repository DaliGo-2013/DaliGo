<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Su equipo está listo, pase a retirar» (dueño 07-08): cuando el técnico
 * termina, avisa él mismo al cliente por correo y le dice que pague en sala de
 * ventas al retirar. El timestamp evita el aviso duplicado y deja registrado
 * quién avisó.
 *
 * Convive con `fecha_aviso` (la fecha manual de «aviso al cliente» del parte del
 * técnico), que este botón rellena si venía vacía: un solo dato visible, sin
 * pedirle al técnico que lo escriba dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_servicio', 'listo_avisado_at')) {
                $table->timestamp('listo_avisado_at')->nullable()->after('fecha_retiro');
                $table->foreignId('listo_avisado_por')->nullable()->after('listo_avisado_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('listo_avisado_por');
            $table->dropColumn('listo_avisado_at');
        });
    }
};
