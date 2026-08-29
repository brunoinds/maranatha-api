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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('user_id');
            $table->string('title', 100);
            // Strings ISO-8601 com offset, que DATETIME nao aceita.
            $table->string('from_date', 40);
            $table->string('to_date', 40);
            $table->enum('type', ['Facture', 'Bill'])->defaut("Bill");
            $table->string('exported_pdf', 100)->nullable(true)->default(null);
            $table->string('status', 100)->default('Draft');
            $table->string('rejection_reason', 100)->nullable(true)->default(null)->create();
            $table->string('approved_at', 40)->nullable()->default(null);
            $table->string('rejected_at', 40)->nullable()->default(null);
            $table->string('submitted_at', 40)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
