<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agenda del técnico industrial (servicio en terreno): trabajos agendados
     * por el jefe o los vendedores — mantención, reparación o instalación de
     * plantas de osmosis, llenadoras y lavadoras — con el cliente, la dirección
     * y lo que hay que hacer. El técnico ve su mes y marca lo realizado.
     * Cliente denormalizado (como en ordenes_servicio): la agenda histórica no
     * depende de la ficha. nullOnDelete en todas las FK. Idempotente.
     */
    public function up(): void
    {
        if (Schema::hasTable('agenda_trabajos')) {
            return;
        }

        Schema::create('agenda_trabajos', function (Blueprint $table) {
            $table->id();

            $table->string('tipo')->index();                 // mantencion | reparacion | instalacion
            $table->date('fecha')->index();                  // día agendado
            $table->string('estado')->default('agendado')->index(); // agendado | realizado | cancelado

            // Servicio del catálogo (opcional: puede ser un trabajo fuera de tarifa).
            $table->foreignId('servicio_terreno_id')->nullable()->constrained('servicios_terreno')->nullOnDelete();

            // Cliente (denormalizado + enlace opcional a la ficha).
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_rut', 20)->nullable();
            $table->string('cliente_telefono', 30)->nullable();
            $table->string('cliente_email')->nullable();
            $table->string('direccion')->nullable();         // dónde se hace el trabajo
            $table->string('ciudad')->nullable();

            // Técnico industrial asignado.
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('descripcion')->nullable();         // lo que hay que hacer (detalle)
            $table->text('notas_tecnico')->nullable();       // lo que el técnico reporta al cerrar

            $table->string('creado_por')->nullable();        // quién lo agendó (nombre)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_trabajos');
    }
};
