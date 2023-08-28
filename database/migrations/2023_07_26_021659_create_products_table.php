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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('business_id')->constrained('businesses');
            $table->string('name');
            $table->string('description');
            $table->decimal('price', 8, 2);
            $table->decimal('taxes', 8, 2);
            $table->string('image');
            $table->enum('charge_type', ['Weight', 'Unit', 'Time'])->default('Weight');
            $table->integer('average_forecast_hours')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
