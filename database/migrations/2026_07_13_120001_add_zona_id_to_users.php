<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vinculo vendedor -> zona (D-006): un usuario (vendedor) atiende una zona.
     * Nullable; onDelete set null. Mismo patron que add_sucursal_id_to_users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zona_id');
        });
    }
};
