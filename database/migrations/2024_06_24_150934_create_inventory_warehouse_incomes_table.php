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
        Schema::create('inventory_warehouse_incomes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('description')->nullable(true)->default(null);
            $table->timestamp('date');
            $table->string('ticket_type')->nullable(true)->default(null);
            $table->string('ticket_number')->nullable(true)->default(null);
            $table->string('commerce_number')->nullable(true)->default(null);

            $table->string('qrcode_data', 1000)->nullable(true)->default(null);
            $table->string('image', 100)->nullable(true)->default(null);

            $table->string('currency');

            $table->string('job_code')->nullable(true)->default(null);
            $table->string('expense_code')->nullable(true)->default(null);
            $table->integer('inventory_warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouse_incomes');
    }
};
