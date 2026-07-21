<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmación del CLIENTE a una visita/trabajo de terreno ya agendado: cuando el
 * vendedor coordina (fecha/hora/técnico), al cliente le llega un correo con un
 * link donde confirma que puede ese día (texto libre corto) o avisa que no puede.
 * Vive en la propia fila (un trabajo = una cita viva); al reprogramar se resetea
 * y se reenvía. Todo idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            if (! Schema::hasColumn('agenda_trabajos', 'confirmacion_token')) {
                $table->string('confirmacion_token', 64)->nullable()->unique()->after('estado');
            }
            if (! Schema::hasColumn('agenda_trabajos', 'confirmacion_enviada_at')) {
                $table->timestamp('confirmacion_enviada_at')->nullable()->after('confirmacion_token');
            }
            if (! Schema::hasColumn('agenda_trabajos', 'cliente_confirmacion')) {
                $table->string('cliente_confirmacion', 20)->nullable()->after('confirmacion_enviada_at'); // confirmada|no_puede
            }
            if (! Schema::hasColumn('agenda_trabajos', 'cliente_confirmacion_at')) {
                $table->timestamp('cliente_confirmacion_at')->nullable()->after('cliente_confirmacion');
            }
            if (! Schema::hasColumn('agenda_trabajos', 'cliente_confirmacion_nota')) {
                $table->string('cliente_confirmacion_nota', 1000)->nullable()->after('cliente_confirmacion_at'); // ~150 palabras
            }
        });
    }

    public function down(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            foreach ([
                'cliente_confirmacion_nota', 'cliente_confirmacion_at', 'cliente_confirmacion',
                'confirmacion_enviada_at', 'confirmacion_token',
            ] as $col) {
                if (Schema::hasColumn('agenda_trabajos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
