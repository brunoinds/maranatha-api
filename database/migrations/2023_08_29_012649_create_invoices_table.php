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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('report_id');
            $table->enum('type', ['Facture', 'Bill']);
            // 238 linhas de producao passam de 100 chars (max 158) -> ERROR 1406 no MySQL.
            $table->string('description', 255);
            $table->string('ticket_number', 100);
            $table->string('commerce_number', 100);
            // Guarda a string ISO-8601 com offset ('2024-04-01T00:00:00.000-05:00'),
            // que DATETIME/TIMESTAMP nao aceita. Ver database/mysql-migration/README.md.
            $table->string('date', 40);
            $table->string('job_code', 100);
            $table->string('expense_code', 100);
            // double(8,2) (o que float() gera no MySQL) tem teto de 999999.99;
            // invoices.amount chega a 221.137.500,00.
            $table->double('amount');
            $table->string('qrcode_data', 1000)->nullable(true);
            $table->string('image', 100)->nullable(true)->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
