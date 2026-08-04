<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hoja de ruta digital (P-DSP-08, PLAN-DESPACHOS-V2 §2).
 *
 * Reemplaza el Excel que Ricardo tipea a mano: una hoja por SALIDA del
 * vehículo (R2), armada por zona (R21), con folio correlativo desde 1000
 * (R25) y la cadena de 3 llaves secuenciales (R11): jefe de ventas autoriza
 * PAGOS → jefe de despacho autoriza RUTA → jefe de bodega autoriza CARGA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hojas_de_ruta', function (Blueprint $table) {
            $table->id();

            // Correlativo visible, parte en 1000 (pedido de Luis, R25). Lo
            // asigna HojaRutaService::crear() como max(folio)+1 bajo
            // lockForUpdate; este unique es la red REAL contra la carrera.
            $table->unsignedInteger('folio')->unique();

            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();   // R17: el camión vuelve a su sucursal
            $table->foreignId('zona_id')->constrained('zonas')->restrictOnDelete();            // R21: la hoja se arma POR zona

            // Vehículo: FK BLANDA al catálogo de M18 + snapshot de texto.
            // M18 permite destroy() físico del vehículo, así que una FK dura
            // rompería su flujo (territorio ajeno); el snapshot conserva el
            // registro histórico aunque el vehículo se borre o renombre —
            // mismo patrón que traslados_servicio (emisor_id + emisor_nombre).
            // El form ELIGE de Vehiculo::activos(): nada se tipea (R1).
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->string('vehiculo', 191);
            $table->string('patente', 12);   // mismo ancho que vehiculos.ppu

            $table->foreignId('conductor_id')->constrained('users')->restrictOnDelete();  // R22: Ricardo autoriza UN conductor para UNA ruta
            $table->string('peoneta_nombre', 191)->nullable();                            // R12: opcional; queda por seguridad y el bono se parte

            // Máquina de estados (transiciones en HojaDeRuta::TRANSICIONES,
            // aplicadas por HojaRutaService bajo lock — no saltables).
            $table->string('estado', 32)->default('borrador')->index();

            // Timestamps por transición: quién y cuándo, AUTOMÁTICOS (R5:
            // cero campos manuales de hora — la guía electrónica del
            // 1-nov-2026 exigirá hora de salida y el sistema la sabrá solo).
            // 'cerrada' no lleva columnas aquí: su transición es P-DSP-10.
            $table->dateTime('pagos_ok_at')->nullable();
            $table->foreignId('pagos_ok_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('ruta_autorizada_at')->nullable();
            $table->foreignId('ruta_autorizada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cargada_at')->nullable();
            $table->foreignId('cargada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('en_ruta_at')->nullable();
            $table->foreignId('en_ruta_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['estado', 'conductor_id']);   // el scoping del conductor busca su hoja en_ruta
        });

        Schema::create('hoja_ruta_paradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hoja_de_ruta_id')->constrained('hojas_de_ruta')->cascadeOnDelete();

            // Unique GLOBAL a propósito: un despacho no puede estar en dos
            // hojas a la vez. Si un rechazado se re-despacha otro día (R15),
            // la parada vieja se resuelve primero — decisión anotada en el
            // parte de P-DSP-08.
            $table->foreignId('despacho_id')->unique()->constrained('despachos')->restrictOnDelete();

            // El orden pactado jefe de despacho + chofer (R3). Editable hasta
            // en_ruta; después solo vía la edición auditada de R6 (P-DSP-10).
            $table->unsignedSmallInteger('orden');

            // R4+R7: «OK» = pagado; sin OK el chofer COBRA en la puerta.
            // Default fail-safe: si nadie dijo pagado, se cobra. El registro
            // del cobro (metodo/monto) lo hace el conductor en P-DSP-09.
            $table->string('estado_cobro', 32)->default('cobrar_en_entrega');
            $table->string('cobro_metodo', 32)->nullable();
            $table->unsignedInteger('cobro_monto')->nullable();

            // R15: entregada | rechazada | reprogramada (lo escribe P-DSP-09).
            $table->string('resultado', 32)->nullable();

            $table->timestamps();

            // SIN unique en [hoja, orden] a propósito: un swap A↔B se valida
            // fila a fila en MySQL y violaría el índice a mitad del update.
            // La secuencia la regenera completa el service al reordenar; el
            // candado real de integridad es el unique de despacho_id.
            $table->index(['hoja_de_ruta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hoja_ruta_paradas');
        Schema::dropIfExists('hojas_de_ruta');
    }
};
