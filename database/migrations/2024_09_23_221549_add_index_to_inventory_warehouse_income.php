<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_warehouse_incomes', function (Blueprint $table) {
            $table->index(['inventory_warehouse_id'], 'inventory_warehouse_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse_incomes', function (Blueprint $table) {
            $table->dropIndex('inventory_warehouse_index');
        });
    }
};
