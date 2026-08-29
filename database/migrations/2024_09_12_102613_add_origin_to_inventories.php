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
            // Guarda um id inteiro, nao JSON: json() aqui era um engano de tipo.
            $table->unsignedBigInteger('origin_inventory_warehouse_income_id')->nullable();
        });

        Schema::table('inventory_product_items', function (Blueprint $table) {
            // Guarda um id inteiro, nao JSON: json() aqui era um engano de tipo.
            $table->unsignedBigInteger('origin_inventory_product_item_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse_incomes', function (Blueprint $table) {
            $table->dropColumn('origin_inventory_warehouse_income_id');
        });

        Schema::table('inventory_product_items', function (Blueprint $table) {
            $table->dropColumn('origin_inventory_product_item_id');
        });
    }
};
