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
            $table->timestamp('from_date');
            $table->timestamp('to_date');
            $table->enum('type', ['Facture', 'Bill'])->defaut("Bill");
            $table->string('exported_pdf', 100)->nullable(true)->default(null);
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
        Schema::dropIfExists('reports');
    }
};
