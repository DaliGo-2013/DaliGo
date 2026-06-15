<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill de transicion: los reportes creados con el flujo viejo tienen
     * cantidades directas pero ningun registro. Sin esto, el primer
     * recalcularDesdeRegistros() post-deploy (que suma solo registros)
     * pisaria esas cantidades vivas. Se crea un registro sin maquina/tipo
     * con los totales actuales de cada reporte que tenga produccion.
     *
     * Idempotente: salta reportes que ya tienen registros.
     */
    public function up(): void
    {
        $reportes = DB::table('produccion_reportes')
            ->whereRaw('(primera + segunda + malo) > 0')
            ->orderBy('id')
            ->get(['id', 'primera', 'segunda', 'malo']);

        foreach ($reportes as $reporte) {
            $yaTieneRegistros = DB::table('produccion_registros')
                ->where('reporte_id', $reporte->id)
                ->exists();

            if ($yaTieneRegistros) {
                continue;
            }

            DB::table('produccion_registros')->insert([
                'reporte_id' => $reporte->id,
                'maquina_id' => null,
                'tipo_botellon_id' => null,
                'primera' => $reporte->primera,
                'segunda' => $reporte->segunda,
                'malo' => $reporte->malo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Sin reversa: no hay forma de distinguir los registros del backfill de
     * registros legitimos sin maquina/tipo. Revertir la tabla completa la
     * cubre el down() de create_produccion_registros_table.
     */
    public function down(): void
    {
        //
    }
};
