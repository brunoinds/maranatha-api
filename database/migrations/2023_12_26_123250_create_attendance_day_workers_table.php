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
        Schema::create('attendance_day_workers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('worker_dni', 100);
            $table->timestamp('attendance_id');
            $table->timestamp('date');
            $table->string('status')->default('Present');
            $table->string('observations', 400)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_day_workers');
    }
};
