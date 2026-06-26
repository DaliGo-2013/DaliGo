<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra el tipo de equipo "maquina" a "dispensador" en las ordenes de
 * servicio tecnico. El tipo se guarda como string en ordenes_servicio.tipo_equipo
 * (no es enum), asi que solo hay que actualizar las filas existentes; la lista
 * de opciones vive en OrdenServicio::TIPOS. Idempotente: si ya no hay filas con
 * "maquina", no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ordenes_servicio')
            ->where('tipo_equipo', 'maquina')
            ->update(['tipo_equipo' => 'dispensador']);
    }

    public function down(): void
    {
        DB::table('ordenes_servicio')
            ->where('tipo_equipo', 'dispensador')
            ->update(['tipo_equipo' => 'maquina']);
    }
};
