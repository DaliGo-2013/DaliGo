<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Servicio tecnico (taller): ingreso de maquinas y lavadoras. Reemplaza el
     * Excel de OneDrive para que el flujo viva dentro de DaliGo. Version basica:
     * solo registro/listado (el `estado` es un campo simple, no una maquina de
     * estados). El dueno del equipo se enlaza por RUT a la ficha de clientes.
     *
     * Casi todo es nullable a proposito: deja abierta la futura entrada parcial
     * por QR (el cliente escanea, completa sus datos y el tecnico la revisa).
     * nullOnDelete: borrar un cliente/sucursal/usuario NO borra la orden historica.
     */
    public function up(): void
    {
        Schema::create('ordenes_servicio', function (Blueprint $table) {
            $table->id(); // = folio visible (#000123)

            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('fecha_ingreso');
            $table->string('tipo_equipo');                  // maquina | lavadora | otro
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->text('falla_reportada')->nullable();
            $table->text('accesorios')->nullable();          // accesorios recibidos

            $table->string('estado')->default('recibido')->index(); // select simple, NO workflow
            $table->text('observaciones')->nullable();
            $table->date('fecha_entrega')->nullable();

            // Puerta abierta a la vision futura (sin uso en la UI basica).
            $table->string('fuente')->default('mostrador');  // mostrador | qr

            $table->timestamps();

            $table->index('fecha_ingreso');
            $table->index('tipo_equipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_servicio');
    }
};
