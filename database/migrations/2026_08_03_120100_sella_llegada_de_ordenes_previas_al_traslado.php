<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sella como YA LLEGADAS las ordenes de sucursal que existian antes de que
 * existiera el registro de traslado.
 *
 * Por que hace falta: la regla del dueño es que una maquina no se puede reparar
 * si no fue recepcionada en la casa matriz. Ese candado, aplicado en seco, dejaria
 * BLOQUEADAS todas las ordenes que hoy estan vivas en Abate y Coquimbo — con la
 * operacion andando y sin ningun traslado que se pueda crear hacia atras. Seria
 * un candado correcto rompiendo trabajo real.
 *
 * Se les estampa `traslado_recibida_at` (con la fecha de confirmacion o, si no la
 * tiene, la de ingreso) dejando `traslado_id` en NULL: eso las marca como
 * «llego antes del registro» — se distinguen de las que si tienen cadena de
 * custodia, y no se inventa un traslado que nunca ocurrio.
 *
 * One-shot e idempotente: solo toca las que estan sin sellar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $central = DB::table('sucursales')->where('es_central', true)->value('id');

        DB::table('ordenes_servicio')
            ->whereNull('traslado_recibida_at')
            ->whereNotNull('sucursal_id')
            // Las de la casa matriz no viajan: se dejan en NULL a proposito.
            ->when($central, fn ($q) => $q->where('sucursal_id', '!=', $central))
            ->update([
                'traslado_recibida_at' => DB::raw('COALESCE(confirmada_at, fecha_ingreso)'),
            ]);
    }

    public function down(): void
    {
        // No se revierte: distinguir lo sellado por esta migracion de lo sellado
        // por una recepcion real exigiria una marca aparte, y borrar ambos
        // dejaria ordenes vivas bloqueadas. Es un sello historico, no un estado.
    }
};
