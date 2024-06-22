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
        Schema::dropIfExists('instant_messages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('instant_messages', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('body')->nullable(true)->default(null);
            $table->integer('from_user_id');
            $table->integer('to_user_id');
            $table->integer('replies_to')->nullable(true)->default(null);
            $table->timestamp('sent_at');
            $table->timestamp('received_at')->nullable(true)->default(null);
            $table->timestamp('read_at')->nullable(true)->default(null);
            $table->timestamp('played_at')->nullable(true)->default(null);
            $table->string('type');
            $table->string('status')->default('Sent');
            $table->json('attachment')->nullable(true);
            $table->json('metadata');
        });
    }
};
