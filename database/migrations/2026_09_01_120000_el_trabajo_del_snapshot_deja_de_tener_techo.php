<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `orden_servicio_cotizaciones.trabajo_realizado` pasa de VARCHAR(500) a TEXT.
 *
 * POR QUÉ AHORA. Desde el 01-09-2026 el técnico no escribe la frase del cliente: marca trabajos
 * y el servidor la arma con todos ellos (`OrdenServicio::fraseDeTrabajos`). El campo de texto que
 * la limitaba —y su `max:500`— ya no existe, así que el largo dejó de tener quién lo contenga y
 * pasó a depender de CUÁNTOS trabajos marque el técnico. Medido sobre el catálogo real de 21
 * trabajos: 10 marcados dan 511 caracteres y los 21 dan 793. O sea que ya se pasaba, y el modo de
 * falla es el peor posible: «Data too long» de MySQL al ENVIAR la cotización (SQLite lo deja
 * pasar, así que local y los tests no lo verían), lejos de la pantalla donde se marcaron.
 *
 * POR QUÉ TEXT Y NO UN VARCHAR MÁS GRANDE. Esta columna ya se agrandó una vez por exactamente
 * esta razón (2026_08_28_100100, de 191 a 500) y se quedó corta a los tres días, porque el número
 * se eligió mirando el caso típico —«cuatro o cinco trabajos»— y no el peor. El caso peor no es
 * fijo: crece con el catálogo, y el dueño acaba de declarar que va a seguir agregándole trabajos
 * («iré agregando respuestas con el paso del tiempo»). Un techo que hay que ir subiendo cada vez
 * que el catálogo crece es un techo mal puesto. La columna hermana de `ordenes_servicio` —la
 * fuente de este snapshot— ya es TEXT desde que nació; esto las deja iguales, que es lo que
 * siempre debieron ser.
 *
 * `down()` vuelve a 500 y NO es reversible sin pérdida si alguna cotización ya guardó un texto
 * más largo: MySQL lo truncaría. Se deja igual porque es lo que corresponde a un rollback, pero
 * ese es el motivo por el que no conviene rodar hacia atrás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            $table->text('trabajo_realizado')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio_cotizaciones', function (Blueprint $table) {
            $table->string('trabajo_realizado', 500)->nullable()->change();
        });
    }
};
