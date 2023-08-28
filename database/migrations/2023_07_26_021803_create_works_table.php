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
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('machine_id')->constrained('machines');
            $table->foreignId('product_items_id')->constrained('product_items');
            $table->foreignId('user_id')->constrained('users');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->enum('status', ['Pending', 'Working', 'Finished', 'Cancelled']);
            $table->longText('observations');
            $table->enum('type', ['Washing', 'Drying', 'Ironing', 'Folding', 'Order'])->default('Washing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
