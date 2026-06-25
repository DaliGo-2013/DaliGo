<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapa de taller (la trabaja el tecnico): que se le hizo a la maquina, los
     * repuestos usados (1..N, en tabla aparte), mano de obra, y las fechas de
     * aviso al cliente y de retiro. El costo total se calcula (repuestos +
     * mano de obra) y solo aplica cuando la condicion es reparacion (garantia
     * no cobra). Montos en pesos chilenos (enteros, sin decimales).
     */
    public function up(): void
    {
        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->text('trabajo_realizado')->nullable()->after('falla_reportada');
            $table->unsignedInteger('mano_obra')->nullable()->after('trabajo_realizado'); // CLP
            $table->date('fecha_aviso')->nullable()->after('fecha_entrega');   // aviso al cliente
            $table->date('fecha_retiro')->nullable()->after('fecha_aviso');    // retiro del equipo
        });

        Schema::create('orden_servicio_repuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_servicio_id')->constrained('ordenes_servicio')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('cantidad')->default(1);
            $table->unsignedInteger('precio_unitario')->default(0); // CLP
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_repuestos');

        Schema::table('ordenes_servicio', function (Blueprint $table) {
            $table->dropColumn(['trabajo_realizado', 'mano_obra', 'fecha_aviso', 'fecha_retiro']);
        });
    }
};
