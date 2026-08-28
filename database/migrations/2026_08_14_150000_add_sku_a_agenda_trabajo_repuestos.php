<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CÓDIGO del repuesto que el técnico usó en terreno (dueño 14-08-2026).
 *
 * El técnico industrial ya podía declarar QUÉ usó, pero solo por nombre en texto
 * libre. El código importa por una razón que NO es el inventario: el descuento de
 * stock sale de la factura o boleta que emite el vendedor —Bsale descuenta al
 * facturar— y el técnico no emite documentos. Su lista es un AVISO.
 *
 * Lo que el código resuelve es que el vendedor arme esa factura sin volver a
 * preguntarle nada al técnico: «2 membranas» escrito a mano hay que interpretarlo
 * y buscarlo a mano en el catálogo; con el código la línea sale sola. De paso el
 * informe y el Excel pueden nombrar el repuesto sin ambigüedad.
 *
 * DaliGo NO genera ningún movimiento de stock con esto, a propósito: dos
 * escritores del mismo número —uno de ellos el espejo de Bsale— divergen siempre.
 *
 * Nullable porque un repuesto escrito a mano es legítimo y frecuente (no está en
 * el catálogo, o el técnico no lo encontró). Null significa «no vino del
 * catálogo», no «falta el dato». Espeja `orden_servicio_repuestos.sku` del taller.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('agenda_trabajo_repuestos', 'sku')) {
            return;
        }

        Schema::table('agenda_trabajo_repuestos', function (Blueprint $table) {
            $table->string('sku')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('agenda_trabajo_repuestos', 'sku')) {
            return;
        }

        Schema::table('agenda_trabajo_repuestos', function (Blueprint $table) {
            $table->dropColumn('sku');
        });
    }
};
