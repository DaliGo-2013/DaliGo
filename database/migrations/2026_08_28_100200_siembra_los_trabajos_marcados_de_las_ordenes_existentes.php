<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ONE-SHOT. Llena `orden_servicio_trabajos` para las órdenes que YA existen, haciendo coincidir
 * su `trabajo_realizado` con el catálogo por texto exacto — el mismo criterio con el que hasta
 * hoy se calculaba la mano de obra.
 *
 * POR QUÉ NO SE PUEDE OMITIR: desde este cambio la mano de obra se deriva de los trabajos
 * MARCADOS. Una orden histórica no tiene ninguno; si alguien abre su parte, corrige una fecha y
 * guarda, la mano de obra se recalcularía sobre un conjunto vacío y quedaría en $0 —un BORRADO
 * SILENCIOSO de dinero ya cotizado, exactamente la familia de defecto de la bitácora
 * [2026-08-20] (el campo que la pantalla no dibuja llega ausente y un `?? 0` lo lee como cero).
 * Sembrar la pivote hace que esas órdenes lleguen a la pantalla con sus trabajos ya marcados.
 *
 * Las que NO coinciden (texto libre, como las tres reparaciones que Fernando escribió a mano)
 * quedan sin filas a propósito: no hay forma de adivinar qué trabajos del catálogo eran, y
 * declararlo vacío es lo honesto. Su `mano_obra` guardada NO se toca, y la pantalla ahora
 * muestra el hueco en vez de esconderlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orden_servicio_trabajos') || ! Schema::hasTable('tiempos_reparacion')) {
            return;
        }

        // El catálogo indexado por su texto. Se incluyen los INACTIVOS: una orden vieja pudo
        // cerrarse con un trabajo que después se desactivó, y su mano de obra fue real.
        $catalogo = DB::table('tiempos_reparacion')->get(['id', 'trabajo', 'horas'])
            ->keyBy(fn ($t) => $t->trabajo);

        if ($catalogo->isEmpty()) {
            return;
        }

        $ahora = now();

        DB::table('ordenes_servicio')
            ->whereNotNull('trabajo_realizado')
            ->orderBy('id')
            ->select(['id', 'trabajo_realizado'])
            ->chunk(200, function ($ordenes) use ($catalogo, $ahora) {
                $filas = [];

                foreach ($ordenes as $orden) {
                    $t = $catalogo->get($orden->trabajo_realizado);
                    if (! $t) {
                        continue;
                    }

                    $filas[] = [
                        'orden_servicio_id' => $orden->id,
                        'tiempo_reparacion_id' => $t->id,
                        // Las horas del catálogo de HOY. Es lo mismo que la orden estaba usando
                        // hasta este deploy (la mano de obra se recalculaba en cada guardado
                        // leyendo el catálogo), así que sembrarlas no le cambia el monto a nadie.
                        'horas' => $t->horas,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($filas !== []) {
                    // insertOrIgnore por el unique (orden, trabajo): la migración tiene que poder
                    // correr dos veces sin reventar (el deploy corre migrate en cada push).
                    DB::table('orden_servicio_trabajos')->insertOrIgnore($filas);
                }
            });
    }

    public function down(): void
    {
        // No se puede distinguir lo sembrado por acá de lo que el técnico marcó después, así que
        // el reverso no borra nada: dejar filas de más es inofensivo (la pantalla las muestra
        // marcadas), borrar de más sería perder trabajo del técnico.
    }
};
