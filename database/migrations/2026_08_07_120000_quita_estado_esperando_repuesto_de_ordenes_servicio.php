<?php

use App\Models\OrdenServicio;
use Illuminate\Database\Migrations\Migration;

/**
 * Saca el estado 'esperando_repuesto' de las ordenes que lo tengan y las deja
 * En Revision.
 *
 * Regla del dueño (07-08-2026): el taller NO es bodega de acopio. Un repuesto
 * importado puede tardar hasta un año, asi que la maquina no se queda esperando:
 * el tecnico define EN EL MOMENTO, contra el stock que hay, si se puede arreglar
 * o no — y si no se puede, se le dice al cliente. Con eso el estado deja de
 * existir en OrdenServicio::ESTADOS.
 *
 * POR QUE HAY QUE TOCAR LOS DATOS y no solo la lista: `estado` es un string
 * libre (no un enum), asi que las filas viejas sobreviven a la remocion — y
 * quedan rotas de tres maneras. (1) Desaparecen del contador de ordenes activas
 * (ESTADOS_PENDIENTES_TECNICO): una maquina real en el taller se vuelve
 * invisible. (2) El <select> del parte del tecnico no ofrece valores historicos,
 * asi que la orden se abre con «Recibido» preseleccionado y RETROCEDE sola al
 * primer guardado. (3) El filtro por estado del listado no las puede pescar
 * (Rule::in / array_intersect contra ESTADOS).
 *
 * A En Revision porque es la situacion real bajo la regla nueva: la maquina esta
 * en el taller y el tecnico todavia tiene que decidir contra el stock. NO a
 * Cotizacion (implicaria un presupuesto ya enviado, y es justo el estado que
 * habilita mandarselo al cliente) ni a Sin Solucion (cerraria maquinas que
 * quizas si se arreglan, y el cierre exige diagnostico final).
 *
 * Se mueven con ELOQUENT a proposito, no con DB::table: OrdenServicio es
 * Auditable, asi que cada orden movida deja su fila en `audits` (old
 * 'esperando_repuesto' → new 'en_revision') y se ve en la pantalla de auditoria
 * si hubiera que devolver alguna a mano; ademas el hook `saved` invalida los
 * conteos cacheados del historial, que este cambio deja obsoletos. Guardar una
 * orden no dispara avisos ni observers, asi que no se despierta nada mas.
 *
 * One-shot e idempotente: solo toca las que siguen en ese estado (en una BD nueva
 * no hay ninguna y no hace nada).
 */
return new class extends Migration
{
    public function up(): void
    {
        OrdenServicio::query()
            ->where('estado', 'esperando_repuesto')
            ->get()
            ->each(fn (OrdenServicio $orden) => $orden->update(['estado' => 'en_revision']));
    }

    public function down(): void
    {
        // No se revierte: ya movidas, distinguirlas de las que YA estaban En
        // Revision exigiria una marca aparte que no se guardo (el rastro por orden
        // esta en `audits`). Y restaurar el estado dejaria ordenes con un valor que
        // ninguna pantalla sabe ofrecer ni filtrar, que es justo lo que esta
        // migracion existe para evitar.
    }
};
