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
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('description')->nullable()->default(null);
            $table->string('category')->nullable()->default(null);
            $table->string('brand')->nullable()->default(null);
            $table->string('presentation')->nullable()->default(null);
            $table->string('unit')->default('Units');
            $table->string('code')->nullable()->default(null);
            $table->string('status')->default('Active');
            $table->string('image')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};
