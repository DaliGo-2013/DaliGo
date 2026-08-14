<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CUÁNDO NO SE PUEDE PEDIR AL TÉCNICO: feriados, vacaciones y días a media jornada.
 *
 * Pedido del dueño (13-08-2026): «los feriados no trabaja… habría que dejar una opción que
 * diga ocupado por si el técnico está de vacaciones… y lo mismo por si un día trabaja hasta
 * las dos de la tarde o las doce o las tres».
 *
 * UNA SOLA TABLA PARA LAS TRES COSAS porque para el sistema son lo mismo: un rango de fechas
 * en que la agenda no está disponible (o lo está a medias). Tres tablas —feriados, vacaciones,
 * horarios— serían tres pantallas, tres migraciones y tres formas de preguntar lo mismo.
 *
 * EL MOTIVO ES INTERNO. «No es tan importante que la gente sepa que está de vacaciones,
 * simplemente no está disponible» (dueño). Por eso `motivo` existe para el staff —el jefe de
 * ventas necesita saber por qué cerró un día— y NO sale nunca por el endpoint público.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_cierres', function (Blueprint $table) {
            $table->id();

            // Un solo día = desde y hasta iguales. Guardar siempre los dos evita la rama
            // «si es null entonces es de un día» en cada consulta.
            $table->date('fecha_desde');
            $table->date('fecha_hasta');

            // 'cerrado'      → no se atiende (feriado, vacaciones, lo que sea).
            // 'media_jornada'→ se atiende hasta `hora_hasta`.
            $table->string('tipo', 20)->default('cerrado');
            $table->time('hora_hasta')->nullable();

            // INTERNO: nunca viaja al público. Ver el encabezado.
            $table->string('motivo', 191)->nullable();

            // 'feriado' lo siembra el sistema y se puede volver a sembrar sin duplicar;
            // 'manual' lo carga el jefe de ventas y NUNCA lo pisa un seeder.
            $table->string('origen', 20)->default('manual');

            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // La consulta real es «¿hay algún cierre que toque este rango?»: se filtra por
            // las dos puntas, así que las dos van indexadas.
            $table->index(['fecha_desde', 'fecha_hasta']);
            $table->index('origen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_cierres');
    }
};
