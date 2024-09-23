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
        Schema::create('project_jobs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('job_code');
            $table->integer('project_structure_id');

            $table->float('width', 8, 2)->default(0);
            $table->float('length', 8, 2)->default(0);
            $table->float('area', 8, 2)->default(0);
            $table->json('admins_ids')->default('[]');
            $table->integer('supervisor_id');
            $table->string('event_type');
            $table->timestamp('scheduled_start_date');
            $table->timestamp('scheduled_end_date');
            $table->timestamp('started_at')->nullable(true)->default(null);
            $table->timestamp('ended_at')->nullable(true)->default(null);
            $table->string('status')->default('WaitingApproval');
            $table->json('final_report')->nullable(true)->default(null);
            $table->json('marketing_report')->nullable(true)->default(null);
            $table->json('messages')->default('[]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_jobs');
    }
};
