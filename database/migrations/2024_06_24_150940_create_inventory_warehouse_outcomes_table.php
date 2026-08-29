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
        Schema::create('inventory_warehouse_outcomes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('description')->nullable(true)->default(null);
            // String ISO-8601 com offset, que DATETIME nao aceita. Ver database/mysql-migration/README.md.
            $table->string('date', 40);


            $table->integer('user_id')->nullable(true);
            $table->string('job_code')->nullable(true);
            $table->string('expense_code')->nullable(true);
            $table->integer('inventory_warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_warehouse_outcomes');
    }
};
