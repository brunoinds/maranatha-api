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
        Schema::table('inventory_warehouse_outcome_requests', function (Blueprint $table) {
            $table->string('type')->default('Outcomes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse_outcome_requests', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
