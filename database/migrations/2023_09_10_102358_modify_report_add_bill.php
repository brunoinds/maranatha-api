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
        //Add column to reports table
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('type', ['Facture', 'Bill'])->defaut("Bill")->create();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Drop column from reports table
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
