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
        Schema::create('inventory_warehouse_product_item_loans', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('loaned_to_user_id');
            $table->timestamp('loaned_by_user_id');

            $table->timestamp('loaned_at')->nullable(true);
            $table->timestamp('received_at')->nullable(true);
            $table->timestamp('returned_at')->nullable(true);
            $table->timestamp('confirm_returned_at')->nullable(true);

            $table->string('status')->default('SendingToLoan');


            $table->json('movements')->default('[]');
            $table->json('intercurrences')->default('[]');

            $table->integer('inventory_product_item_id');
            $table->integer('inventory_warehouse_id');

            $table->integer('inventory_warehouse_outcome_request_id')->nullable(true)->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouse_product_item_loans');
    }
};
