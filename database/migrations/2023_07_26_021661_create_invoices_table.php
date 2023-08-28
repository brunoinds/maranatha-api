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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('business_id')->constrained('businesses');
            $table->foreignId('customer_id')->constrained('customers');
            $table->timestampTz('paid_at')->nullable();
            $table->longText('observations');
            $table->enum('status', ['Pending', 'Paid', 'Cancelled'])->default('Pending');
            $table->enum('payment_method', ['CreditCard', 'Cash', 'BankTransfer', 'DigitalTransfer', 'Other'])->default('Cash');
            $table->decimal('price_override', 8, 2)->nullable();
            $table->decimal('discounts_override', 8, 2)->nullable();
            $table->decimal('taxes_override', 8, 2)->nullable();
            $table->decimal('amount_override', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
