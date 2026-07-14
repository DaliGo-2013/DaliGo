<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DESPACHOS-v1 · P-DSP-01: espejo read-only de los documentos de venta de Bsale.
 *
 * Shape congelado contra la exploración real de producción (P-DSP-00, evidencia
 * docs/qa/INFRA/2026-07-14--INFRA--p-dsp-00-shape-documents.md). Hallazgos ya
 * incorporados: state/commercialState/cancellationStatus son INT (no string);
 * el nodo client puede no venir (boletas) => cliente_id nullable; la línea de
 * detalle no trae description => descripcion nullable (fallback producto/variant).
 * La emisión sigue en Bsale: estas tablas NUNCA se escriben desde la UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_venta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bsale_document_id')->unique();
            $table->unsignedBigInteger('folio')->nullable()->index();
            $table->unsignedBigInteger('bsale_document_type_id')->nullable()->index();
            $table->dateTime('emitido_at')->nullable()->index();
            $table->decimal('neto', 14, 4)->default(0);
            $table->decimal('iva', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            // Estados Bsale: enteros (hallazgo #1 del shape real).
            $table->integer('state')->nullable();
            $table->integer('commercial_state')->nullable();
            // Anulación (fresca solo dentro del resolape de la sync ~1 día;
            // el consumidor re-verifica el doc puntual antes de actuar).
            $table->integer('cancellation_status')->nullable();
            $table->dateTime('cancellation_at')->nullable();
            $table->unsignedTinyInteger('informed_sii')->nullable();
            $table->string('url_pdf', 191)->nullable();
            $table->string('url_public', 191)->nullable();
            $table->string('token', 64)->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('bodega_id')->nullable()->constrained('bodegas')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('documento_venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_venta_id')->constrained('documentos_venta')->cascadeOnDelete();
            $table->unsignedBigInteger('bsale_detail_id')->nullable();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            // Nullable (hallazgo #3): la línea real no trae description; se
            // puebla desde el producto espejado o el nodo variant si se puede.
            $table->string('descripcion', 191)->nullable();
            $table->decimal('cantidad', 14, 4)->default(0);
            $table->decimal('precio_neto', 14, 4)->default(0);
            $table->decimal('descuento', 14, 4)->default(0);
            $table->timestamps();

            // Idempotencia del upsert de detalle dentro del documento.
            $table->unique(['documento_venta_id', 'bsale_detail_id'], 'doc_venta_detalle_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_venta_detalles');
        Schema::dropIfExists('documentos_venta');
    }
};
