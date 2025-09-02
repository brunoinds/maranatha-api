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
        Schema::table('reports', function (Blueprint $table) {
            $table->index('user_id'); // Foreign key index
            $table->index('status'); // For filtering by status
            $table->index(['user_id', 'status']); // Composite index for common queries
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('report_id'); // Foreign key index
            $table->index('date'); // For date-based queries and ordering
            $table->index('amount'); // For amount aggregations
            $table->index(['report_id', 'date']); // Composite for report date queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['report_id']);
            $table->dropIndex(['date']);
            $table->dropIndex(['amount']);
            $table->dropIndex(['report_id', 'date']);
        });
    }
};
