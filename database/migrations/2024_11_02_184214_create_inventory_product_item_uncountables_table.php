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
        Schema::create('inventory_product_item_uncountables', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('order');
            $table->string('batch')->nullable(true);


            $table->float(column: 'quantity_inserted', total: 8, places: 2)->default(0);
            $table->float(column: 'quantity_used', total: 8, places: 2)->default(0);
            $table->float(column: 'quantity_remaining', total: 8, places: 2)->default(0);


            $table->float(column: 'buy_amount', total: 8, places: 2);
            $table->string('buy_currency');

            $table->string('status')->default('InStock');

            $table->integer('inventory_product_id');
            $table->integer('inventory_warehouse_id');
            $table->integer('inventory_warehouse_income_id');

            $table->json('inventory_warehouse_outcome_ids')->default('[]');
            $table->json('outcomes_details')->default('{}');
            $table->integer('origin_inventory_product_item_uncountable_id')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_item_uncountables');
    }
};
