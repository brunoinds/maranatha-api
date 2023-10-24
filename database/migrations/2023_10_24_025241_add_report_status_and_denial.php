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
        // Adds new columns to the reports table
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->string('status', 100)->default('Draft');
            $table->string('rejection_reason', 100)->nullable(true)->default(null)->create();
            $table->timestamp('approved_at')->nullable(true)->default(null);
            $table->timestamp('rejected_at')->nullable(true)->default(null);
            $table->timestamp('submitted_at')->nullable(true)->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Removes the new columns from the reports table
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->enum('status', ['Draft', 'Submitted'])->default('Draft')->create();
            $table->dropColumn('rejection_reason');
            $table->dropColumn('approved_at');
            $table->dropColumn('rejected_at');
            $table->dropColumn('submitted_at');
        });
    }
};
