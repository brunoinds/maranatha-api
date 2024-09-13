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
        Schema::create('project_structures', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name');
            $table->string('structure_type');
            $table->string('building_type');
            $table->integer('axes_count')->nullable(true)->default(null);

            $table->integer('beams_count')->nullable(true)->default(null);
            $table->integer('columns_count')->nullable(true)->default(null);
            $table->integer('stringers_count')->nullable(true)->default(null);
            $table->integer('facades_count')->nullable(true)->default(null);

            $table->json('default_phases')->default('{"construction": [], "studio": []}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_structures');
    }
};
