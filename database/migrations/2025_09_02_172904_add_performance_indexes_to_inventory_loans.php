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
        Schema::table('inventory_warehouse_product_item_loans', function (Blueprint $table) {
            // Nomes explicitos e curtos: o nome gerado pelo Laravel a partir desta
            // tabela (38 chars) estoura o limite de 64 caracteres do MySQL.
            $table->index('inventory_warehouse_id', 'iwpil_warehouse_id_index');
            $table->index('inventory_product_item_id', 'iwpil_product_item_id_index');
            $table->index('loaned_to_user_id', 'iwpil_loaned_to_user_id_index');
            $table->index('loaned_by_user_id', 'iwpil_loaned_by_user_id_index');
            $table->index('status', 'iwpil_status_index');
            $table->index(['inventory_warehouse_id', 'status'], 'iwpil_warehouse_id_status_index');
            $table->index(['loaned_to_user_id', 'status'], 'iwpil_loaned_to_user_id_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_warehouse_product_item_loans', function (Blueprint $table) {
            $table->dropIndex('iwpil_warehouse_id_index');
            $table->dropIndex('iwpil_product_item_id_index');
            $table->dropIndex('iwpil_loaned_to_user_id_index');
            $table->dropIndex('iwpil_loaned_by_user_id_index');
            $table->dropIndex('iwpil_status_index');
            $table->dropIndex('iwpil_warehouse_id_status_index');
            $table->dropIndex('iwpil_loaned_to_user_id_status_index');
        });
    }
};
