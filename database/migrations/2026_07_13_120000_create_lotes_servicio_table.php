<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lote de ingreso a Servicio Técnico: agrupa las N máquinas que un conductor
     * retira en ruta a UNA empresa y manda a Santiago. Cada máquina sigue siendo
     * una `orden_servicio` independiente y completa; este lote solo guarda los
     * datos que viven UNA vez (empresa, origen, conductor, fecha) y sirve para
     * rastrear "el envío de la empresa X". Idempotente.
     *
     * `lote_uuid` (nullable unique) da idempotencia al reenvío offline: el
     * cliente genera un UUID, y si el lote ya existe no se duplica. Múltiples
     * NULL permitidos (el camino online normal no lo usa).
     */
    public function up(): void
    {
        if (Schema::hasTable('lotes_servicio')) {
            return;
        }

        Schema::create('lotes_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 32)->unique();          // LOTE-XXXXXXXX (autogenerado)
            $table->char('lote_uuid', 36)->nullable()->unique(); // idempotencia cola offline

            // Empresa dueña de las máquinas (denormalizada, como en ordenes_servicio).
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_rut', 20)->nullable();
            $table->string('cliente_email')->nullable();
            $table->string('cliente_telefono', 30)->nullable();

            // Origen de ruta (Los Andes/Curicó/Talca): NO es sucursal, es texto.
            $table->string('origen_ciudad')->nullable();
            // Sucursal de recepción/destino (Mirador): alimenta la fecha de entrega.
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            // Quién retiró en ruta.
            $table->foreignId('conductor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('fecha_ingreso');

            // Valores por defecto del lote (auditoría de con qué se cargó).
            $table->string('tipo_default')->nullable();
            $table->string('facturacion_default')->nullable();
            $table->text('falla_default')->nullable();

            $table->unsignedInteger('total_ordenes')->default(0);

            $table->timestamp('capturado_at')->nullable();   // hora del dispositivo al crear
            $table->timestamp('confirmada_at')->nullable();   // confirmación en Mirador
            $table->string('recibida_por')->nullable();

            $table->timestamps();

            $table->index('fecha_ingreso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotes_servicio');
    }
};
