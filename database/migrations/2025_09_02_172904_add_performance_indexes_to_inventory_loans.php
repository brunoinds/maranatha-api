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
        Schema::table('inventory_warehouse_product_item_loans', function (Blueprint $table) {
            $table->index('inventory_warehouse_id'); // Foreign key index
            $table->index('inventory_product_item_id'); // Foreign key index
            $table->index('loaned_to_user_id'); // Foreign key index
            $table->index('loaned_by_user_id'); // Foreign key index
            $table->index('status'); // For filtering by status
            $table->index(['inventory_warehouse_id', 'status']); // Composite index for common queries
            $table->index(['loaned_to_user_id', 'status']); // Composite index for user loan queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse_product_item_loans', function (Blueprint $table) {
            $table->dropIndex(['inventory_warehouse_id']);
            $table->dropIndex(['inventory_product_item_id']);
            $table->dropIndex(['loaned_to_user_id']);
            $table->dropIndex(['loaned_by_user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['inventory_warehouse_id', 'status']);
            $table->dropIndex(['loaned_to_user_id', 'status']);
        });
    }
};
