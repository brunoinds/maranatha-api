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
        Schema::create('inventory_product_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('order');
            $table->string('batch')->nullable(true);
            // 2.752 linhas tem mais de 2 casas decimais (ex.: 0.3333) e 9 passam de 1e6.
            $table->double('buy_amount');
            $table->double('sell_amount');
            $table->string('buy_currency');
            $table->string('sell_currency');

            $table->string('status')->default('InStock');

            $table->integer('inventory_product_id');
            $table->integer('inventory_warehouse_id');
            $table->integer('inventory_warehouse_income_id');
            $table->integer('inventory_warehouse_outcome_id')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_items');
    }
};
