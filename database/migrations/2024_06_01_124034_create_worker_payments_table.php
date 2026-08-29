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
        Schema::create('worker_payments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('worker_id');
            $table->integer('month');
            $table->integer('year');
            // 257 linhas passam de 1e6; double(8,2) daria ERROR 1264.
            $table->double('amount');
            $table->string('currency');
            $table->string('description', 400)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_payments');
    }
};
