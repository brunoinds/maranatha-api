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
            // Guarda a FK para attendances.id, nao uma data: timestamp() era erro de copy-paste.
            $table->unsignedBigInteger('attendance_id');
            // String ISO-8601 com offset ('2024-04-01T00:00:00-05:00'), que DATETIME nao aceita.
            $table->string('date', 40);
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
