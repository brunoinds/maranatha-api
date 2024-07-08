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
        Schema::create('inventory_warehouse_outcome_requests', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('inventory_warehouse_id');
            $table->integer('inventory_warehouse_outcome_id')->nullable()->default(null);
            $table->integer('user_id');


            $table->string('description')->nullable(true)->default(null);
            $table->json('requested_products')->default('[]');
            $table->json('received_products')->default('[]');

            $table->json('messages')->default('[]');
            $table->string('status')->default('Draft');

            $table->timestamp('requested_at')->nullable()->default(null);
            $table->timestamp('rejected_at')->nullable()->default(null);
            $table->timestamp('approved_at')->nullable()->default(null);
            $table->timestamp('dispatched_at')->nullable()->default(null);
            $table->timestamp('on_the_way_at')->nullable()->default(null);
            $table->timestamp('delivered_at')->nullable()->default(null);
            $table->timestamp('finished_at')->nullable()->default(null);

            $table->string('job_code')->nullable(true);
            $table->string('expense_code')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouse_outcome_requests');
    }
};
