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
        Schema::table('inventory_warehouse_outcomes', function (Blueprint $table) {
            $table->index('inventory_warehouse_id'); // Foreign key index
            $table->index('user_id'); // Foreign key index
            $table->index('date'); // For date-based queries and ordering
            $table->index(['inventory_warehouse_id', 'date']); // Composite index for common queries
        });

        Schema::table('inventory_product_items', function (Blueprint $table) {
            $table->index('inventory_warehouse_outcome_id'); // Foreign key index for outcomes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse_outcomes', function (Blueprint $table) {
            $table->dropIndex(['inventory_warehouse_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['date']);
            $table->dropIndex(['inventory_warehouse_id', 'date']);
        });

        Schema::table('inventory_product_items', function (Blueprint $table) {
            $table->dropIndex(['inventory_warehouse_outcome_id']);
        });
    }
};
