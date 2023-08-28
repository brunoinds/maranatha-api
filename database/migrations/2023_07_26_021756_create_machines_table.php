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
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('business_id')->constrained('businesses');
            $table->string('name');
            $table->string('description');
            $table->longText('observations');
            $table->enum('type', ['Dryer', 'DryWasher', 'Washer', 'Iron', 'Manual' ,'Other'])->default('Washer');
            $table->enum('status', ['Available', 'Maintenance', 'Disabled'])->default('Available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};
