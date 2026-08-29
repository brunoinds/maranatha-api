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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('user_id');
            // Ha' linhas de producao com 106 chars -> string(100) daria ERROR 1406 no MySQL.
            $table->string('description', 255);
            $table->string('ticket_number', 100)->nullable(true);
            $table->string('report_id')->nullable(true);
            // String ISO-8601 com offset, que DATETIME nao aceita. Ver database/mysql-migration/README.md.
            $table->string('date', 40);
            $table->string('model', 100)->default('Direct');
            $table->enum('type', ['Credit', 'Debit'])->default('Credit');
            // double(8,2) arredondaria as 2.448 linhas com mais de 2 casas decimais.
            $table->double('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
