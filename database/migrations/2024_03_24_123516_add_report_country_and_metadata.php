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
        //On reports table, add column called: money_type:
        Schema::table('reports', function (Blueprint $table) {
            $table->string('country', 100)->after('money_type')->default('PE');
            $table->json('metadata')->after('country')->default('{}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Reverse the migration:

        //On reports table, drop column called: money_type:
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('country');
            $table->dropColumn('metadata');
        });
    }
};
