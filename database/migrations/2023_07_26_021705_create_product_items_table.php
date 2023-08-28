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
        Schema::create('product_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->string('name');
            $table->string('description');
            $table->longText('observations');
            $table->decimal('price', 8, 2);
            $table->decimal('taxes', 8, 2);
            $table->enum('charge_type', ['Weight', 'Unit', 'Time'])->default('Unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_items');
    }
};
