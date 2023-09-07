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
            $table->integer('report_id');
            $table->enum('type', ['Facture', 'Bill']);
            $table->string('description', 100);
            $table->string('ticket_number', 100);
            $table->string('commerce_number', 100);
            $table->timestamp('date');
            $table->string('job_code', 100);
            $table->string('expense_code', 100);
            $table->float(column: 'amount', total: 8, places: 2);
            $table->string('qrcode_data', 1000)->nullable(true);
            $table->string('image', 100)->nullable(true)->default(null);
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
