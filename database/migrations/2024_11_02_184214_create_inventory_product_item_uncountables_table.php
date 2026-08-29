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


            $table->double('quantity_inserted')->default(0);
            $table->double('quantity_used')->default(0);
            $table->double('quantity_remaining')->default(0);


            // buy_amount chega a 171.674.590,00 em producao.
            $table->double('buy_amount');
            $table->string('buy_currency');

            $table->string('status')->default('InStock');

            $table->integer('inventory_product_id');
            $table->integer('inventory_warehouse_id');
            $table->integer('inventory_warehouse_income_id');

            $table->longText('inventory_warehouse_outcome_ids');
            $table->longText('outcomes_details');
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
