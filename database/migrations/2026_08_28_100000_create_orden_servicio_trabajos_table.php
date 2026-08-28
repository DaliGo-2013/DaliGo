<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VARIOS trabajos por orden (dueño, 28-08-2026): «de repente hay trabajos donde el tecnico
 * hace como tres o cuatro trabajos sobre un dispensador (cambio de llave, cambio de estanque,
 * cambio de caldera y se agrega espigon) y esa respuesta no existe, sino la lista de
 * respuestas tendria que ser una combinacion infinita».
 *
 * Antes la mano de obra colgaba de que el TEXTO de `ordenes_servicio.trabajo_realizado`
 * coincidiera palabra por palabra con una fila del catalogo. Dos consecuencias que se veian
 * en produccion: una reparacion mixta no podia coincidir con nada (Fernando escribio tres
 * trabajos en una frase y la mano de obra quedo en $0, lo que TRABA el envio de la
 * cotizacion), y ajustarle una coma a una respuesta de la lista borraba la mano de obra.
 *
 * Ahora los trabajos se MARCAN y viven aca; la mano de obra sale de esta tabla. El texto
 * sigue en `trabajo_realizado` y sigue siendo lo que lee el cliente —los 12 lugares que lo
 * leen (correos, cotizacion, snapshot, informe Excel, ficha) no se tocan— pero ya no manda
 * sobre el dinero: editarlo no cambia ni un peso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_servicio_trabajos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            // RESTRICT y no cascade: un trabajo del catalogo NO se borra (se desactiva, ver
            // TiempoReparacionController), asi que si alguna vez alguien intenta borrarlo, que
            // falle en vez de vaciarle la mano de obra a ordenes historicas.
            $table->foreignId('tiempo_reparacion_id')->constrained('tiempos_reparacion')->restrictOnDelete();
            // Las horas del catalogo AL MOMENTO de guardar el parte. El catalogo se calibra
            // (jefatura ajusta las horas) y una orden ya cotizada no puede cambiar de precio
            // sola despues: el snapshot de la cotizacion promete un monto. Mismo criterio que
            // `orden_servicio_repuestos.precio_unitario`, que tampoco relee el catalogo.
            $table->decimal('horas', 4, 1);
            $table->timestamps();

            // Un trabajo no se marca dos veces en la misma orden.
            $table->unique(['orden_servicio_id', 'tiempo_reparacion_id'], 'osj_orden_trabajo_unico');
        });

        Schema::table('ordenes_servicio', function (Blueprint $table) {
            // Lo que el tecnico hizo y NO esta en el catalogo («cambio de estanque», «se
            // agrega espigon»). Se guarda aparte del texto final para poder LISTARLO en
            // «Costos generales de reparacion»: cada linea repetida es un candidato a entrar
            // al catalogo, asi el catalogo se calibra con el uso real en vez de adivinar
            // combinaciones. Sin esto, lo escrito a mano se disuelve dentro de la frase y
            // jefatura no tiene como enterarse.
            $table->text('trabajos_extra')->nullable()->after('trabajo_realizado');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn('trabajos_extra');
        });

        Schema::dropIfExists('orden_servicio_trabajos');
    }
};
