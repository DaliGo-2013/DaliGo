<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A QUÉ HORA LE ACOMODA AL CLIENTE.
 *
 * Pedido del dueño (14-08-2026): «quitar el apartado de cuándo puedes y cuándo no, sino
 * agregar un horario de trabajo para asistir… el cliente pincha y elige el horario».
 *
 * Reemplaza al texto libre `disponibilidad` en el formulario PÚBLICO: una hora elegida de una
 * lista se puede cruzar con la agenda; un párrafo («fines de semana no, después de las 15,
 * avisar antes de llegar») hay que leerlo y traducirlo a mano en cada llamada.
 *
 * La columna `disponibilidad` NO se borra: tiene lo que escribieron los clientes hasta hoy y
 * el formulario INTERNO la sigue usando para anotar lo que se conversa por teléfono. Borrarla
 * sería tirar información que alguien va a necesitar leer.
 *
 * Es PREFERIDA, como la fecha: la hora definitiva la fija quien coordina (`hora` / `hora_fin`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            $table->time('hora_preferida')->nullable()->after('fecha_preferida');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_trabajos', function (Blueprint $table) {
            $table->dropColumn('hora_preferida');
        });
    }
};
