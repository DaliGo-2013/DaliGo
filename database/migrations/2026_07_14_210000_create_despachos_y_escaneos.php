<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DESPACHOS-v1 · P-DSP-03: la entidad despacho sobre el documento espejado
 * (retiro anti-fraude M07 + entrega con prueba M08-MVP) y el log append-only
 * de escaneos del QR (base de la alerta de doble retiro).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despachos', function (Blueprint $table) {
            $table->id();
            // Impredecible (DSP-XXXXXXXX): el QR no es enumerable (anti-fraude).
            $table->string('codigo', 32)->unique();
            // UNIQUE: la regla "un despacho por documento" (v1) es estructural —
            // el check del service es solo la cara amable (review P-DSP-03: un
            // doble-submit con la verificación HTTP en medio creaba dos QR válidos).
            $table->foreignId('documento_venta_id')->unique()->constrained('documentos_venta')->restrictOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $table->string('estado', 32);
            $table->string('transportista', 191)->nullable();
            $table->foreignId('conductor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('retirado_at')->nullable();   // 1er escaneo válido (hora server)
            $table->dateTime('entregado_at')->nullable();  // confirmación recibida (hora server)
            $table->dateTime('capturado_at')->nullable();  // hora del DISPOSITIVO (offline-safe, SPIKE §4.2)
            $table->string('entrega_uuid', 191)->nullable()->index(); // idempotencia offline
            $table->string('firma_path', 191)->nullable();
            $table->string('foto_path', 191)->nullable();
            $table->timestamps();

            // Query real: cola de bodega y hoja de ruta filtran estado dentro de zona.
            $table->index(['estado', 'zona_id']);
        });

        Schema::create('escaneos_despacho', function (Blueprint $table) {
            $table->id();
            $table->foreignId('despacho_id')->constrained('despachos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resultado', 32); // valido / doble_retiro / estado_invalido
            $table->string('detalle', 191)->nullable();
            // Append-only: sin updated_at (un escaneo jamás se edita).
            $table->dateTime('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escaneos_despacho');
        Schema::dropIfExists('despachos');
    }
};
