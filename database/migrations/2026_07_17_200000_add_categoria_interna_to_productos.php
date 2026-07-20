<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categoría INTERNA del catálogo: una clasificación propia de DaliGo, curada a
 * mano (ej. "Industrial (Carlos)"), que corre EN PARALELO a `categoria` (esta
 * última la manda Bsale y el sync la reescribe). `categoria_interna` NO forma
 * parte del upsert de Bsale (CatalogSync), así que el sync NUNCA la pisa: es el
 * lugar seguro para agrupar productos sin depender de/ni tocar Bsale. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('productos', 'categoria_interna')) {
            return;
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->string('categoria_interna')->nullable()->index()->after('categoria');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'categoria_interna')) {
                $table->dropColumn('categoria_interna');
            }
        });
    }
};
