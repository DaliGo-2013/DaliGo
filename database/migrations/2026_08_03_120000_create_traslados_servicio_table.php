<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traslado de maquinas a reparar entre sucursales (pedido del dueño 03-08-2026).
 *
 * El agujero que cierra: una maquina recibida en Abate o Coquimbo TIENE que
 * viajar a la casa matriz (Mirador) para repararse — la ficha ya lo decia («Se
 * repara en El Mirador») — pero del viaje no quedaba NADA: ni quien la entrego,
 * ni quien la recibio, ni cuando. Entre la recepcion en sucursal y la reparacion
 * habia dias sin ningun responsable, y ahi vivian las excusas.
 *
 * Cadena de custodia cerrada: EMISOR (nombre + cuenta si la hay) al despachar,
 * RECEPTOR al recibir, y el conteo de las dos puntas para que una diferencia
 * sea un hecho registrado y no una discusion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslados_servicio', function (Blueprint $table) {
            $table->id();
            // Codigo impredecible (mismo criterio que ordenes y lotes: no
            // enumerable desde afuera).
            $table->string('codigo')->unique();

            $table->foreignId('sucursal_origen_id')->constrained('sucursales');
            $table->foreignId('sucursal_destino_id')->constrained('sucursales');

            // 'en_transito' al despachar, 'recibido' al confirmar la llegada. No
            // hay estado "preparando" a proposito: un traslado nace despachado.
            // Un borrador a medio llenar es justo lo que se podreria sin que
            // nadie lo cierre.
            $table->string('estado')->default('en_transito')->index();

            // EMISOR. `emisor_id` es nullable porque hoy NO hay cuentas creadas
            // en Abate ni Coquimbo (dato del dueño): la responsabilidad arranca
            // por nombre escrito, igual que los conductores, y el dia que existan
            // las cuentas queda amarrada a una persona con clave. El nombre se
            // guarda SIEMPRE, incluso con cuenta: si mañana se renombra o se da
            // de baja el usuario, el registro historico no debe cambiar.
            $table->foreignId('emisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('emisor_nombre');
            $table->string('conductor')->nullable();
            $table->timestamp('despachado_at');
            $table->text('observaciones_envio')->nullable();

            // RECEPTOR (se llena al confirmar la llegada).
            $table->foreignId('receptor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receptor_nombre')->nullable();
            $table->timestamp('recibido_at')->nullable();
            $table->text('observaciones_recepcion')->nullable();

            // Conteo de las DOS puntas: `total_enviado` se congela al despachar
            // para que una diferencia posterior no se pueda maquillar editando el
            // traslado.
            $table->unsignedInteger('total_enviado');
            $table->unsignedInteger('total_recibido')->nullable();

            $table->timestamps();

            // La consulta caliente: "que traslados me estan llegando".
            $table->index(['sucursal_destino_id', 'estado'], 'traslados_destino_estado_index');
        });

        Schema::table('ordenes_servicio', function (Blueprint $table) {
            // La orden viaja en UN traslado (nullable: las recibidas en la casa
            // matriz no viajan).
            $table->foreignId('traslado_id')->nullable()->after('lote_id')
                ->constrained('traslados_servicio')->nullOnDelete();
            // Sello de llegada POR MAQUINA, no por traslado: es lo que permite
            // detectar que salieron 4 y llegaron 3, y decir cual falta.
            $table->timestamp('traslado_recibida_at')->nullable()->after('traslado_id');
            $table->index('traslado_id');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropForeign(['traslado_id']);
            $table->dropColumn(['traslado_id', 'traslado_recibida_at']);
        });
        Schema::dropIfExists('traslados_servicio');
    }
};
