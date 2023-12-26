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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('user_id');
            $table->string('description', 100);
            $table->string('ticket_number', 100)->nullable(true);
            $table->string('report_id')->nullable(true);
            $table->timestamp('date');
            $table->string('model', 100)->default('Direct');
            $table->enum('type', ['Credit', 'Debit'])->default('Credit');
            $table->float(column: 'amount', total: 8, places: 2);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
