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
        Schema::create('project_construction_tasks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('project_job_id');
            $table->integer('project_construction_phase_id');


            $table->string('name');
            $table->string('description')->nullable(true)->default(null);
            $table->string('status')->default('WaitingToStart');
            $table->timestamp('scheduled_start_date')->nullable(true)->default(null);
            $table->timestamp('scheduled_end_date')->nullable(true)->default(null);
            $table->timestamp('started_at')->nullable(true)->default(null);
            $table->timestamp('ended_at')->nullable(true)->default(null);

            $table->integer('count_workers')->default(0);

            $table->integer('progress')->default(0);
            $table->json('daily_reports')->default('[]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_construction_tasks');
    }
};
