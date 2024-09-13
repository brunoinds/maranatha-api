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
        Schema::create('project_construction_phases', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('project_job_id');
            $table->string('expense_code');

            $table->string('name');
            $table->string('description')->nullable(true)->default(null);
            $table->string('color');
            $table->string('status')->default('WaitingToStart');
            $table->timestamp('scheduled_start_date');
            $table->timestamp('scheduled_end_date');
            $table->timestamp('started_at')->nullable(true)->default(null);
            $table->timestamp('ended_at')->nullable(true)->default(null);

            $table->integer('progress')->default(0);

            $table->json('final_report')->nullable(true)->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_construction_phases');
    }
};
