<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alinha o schema produzido por `php artisan migrate` com o schema de producao
 * importado do SQLite (database/mysql-migration/).
 *
 * Sem esta migration, um banco criado do zero diverge do de producao em pontos
 * que importam. Ela e' idempotente: rodar sobre o banco ja' importado nao muda nada.
 *
 * Nao faz nada em SQLite — os tipos so' existem no MySQL.
 */
return new class extends Migration
{
    /**
     * TIMESTAMP -> DATETIME.
     *
     * $table->timestamps() gera TIMESTAMP no MySQL, que:
     *   - so' cobre 1970-01-01 a 2038-01-19; e
     *   - converte de/para UTC a cada escrita e leitura, usando a time_zone da sessao.
     * DATETIME guarda o valor literal, igual ao SQLite. Para uma base contabil,
     * um deslocamento silencioso de fuso em created_at e' inaceitavel.
     */
    private const TIMESTAMP_TO_DATETIME = [
        'attendance_day_workers' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'attendances' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'balances' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'expenses' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'failed_tasks' => 'MODIFY `failed_at` DATETIME NOT NULL',
        'inventory_product_item_uncountables' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'inventory_product_items' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'inventory_products' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'inventory_products_packs' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'inventory_warehouse_incomes' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'inventory_warehouse_outcome_requests' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL, MODIFY `requested_at` DATETIME NULL DEFAULT NULL, MODIFY `rejected_at` DATETIME NULL DEFAULT NULL, MODIFY `approved_at` DATETIME NULL DEFAULT NULL, MODIFY `dispatched_at` DATETIME NULL DEFAULT NULL, MODIFY `on_the_way_at` DATETIME NULL DEFAULT NULL, MODIFY `delivered_at` DATETIME NULL DEFAULT NULL, MODIFY `finished_at` DATETIME NULL DEFAULT NULL',
        'inventory_warehouse_outcomes' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'inventory_warehouse_product_item_loans' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL, MODIFY `loaned_at` DATETIME NULL DEFAULT NULL, MODIFY `received_at` DATETIME NULL DEFAULT NULL, MODIFY `returned_at` DATETIME NULL DEFAULT NULL, MODIFY `confirm_returned_at` DATETIME NULL DEFAULT NULL',
        'inventory_warehouses' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'invoices' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'jobs' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'password_reset_tokens' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL',
        'personal_access_tokens' => 'MODIFY `last_used_at` DATETIME NULL DEFAULT NULL, MODIFY `expires_at` DATETIME NULL DEFAULT NULL, MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'reports' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'users' => 'MODIFY `email_verified_at` DATETIME NULL DEFAULT NULL, MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'worker_payments' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
        'workers' => 'MODIFY `created_at` DATETIME NULL DEFAULT NULL, MODIFY `updated_at` DATETIME NULL DEFAULT NULL',
    ];

    /**
     * Colunas cujo tipo declarado nao comporta o dado real de producao, ou cuja
     * semantica difere do SQLite. Cada uma esta explicada em
     * database/mysql-migration/README.md.
     */
    private const CORRECOES = [
        // 285.374 bytes na maior linha; TEXT so' vai ate' 65.535.
        // A migration vem do pacote softinklab/laravel-keyvalue-storage e nao pode ser editada.
        'kv_storage' => 'MODIFY `value` LONGTEXT COLLATE utf8mb4_bin NOT NULL, '
            . 'MODIFY `comment` LONGTEXT COLLATE utf8mb4_bin NULL DEFAULT NULL',

        // Guarda o nome do arquivo PDF (texto), mas a migration declarou integer.
        'invoices' => 'MODIFY `pdf` VARCHAR(255) COLLATE utf8mb4_bin NULL DEFAULT NULL, '
            // ENUM ordena pela ORDEM DE DECLARACAO; VARCHAR ordena alfabeticamente,
            // como o SQLite. Um ORDER BY type devolveria ordem diferente.
            . 'MODIFY `type` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL, '
            // 238 linhas passam de 100 chars (max 158).
            . 'MODIFY `description` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL',

        // Maior valor real: 297 bytes.
        'inventory_products' => 'MODIFY `image` VARCHAR(1000) COLLATE utf8mb4_bin NULL DEFAULT NULL',

        // ENUM -> VARCHAR, mesmo motivo de invoices.type.
        'reports' => 'MODIFY `type` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL',
        'balances' => 'MODIFY `type` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL DEFAULT \'Credit\', '
            // Ha' linhas com 106 chars.
            . 'MODIFY `description` VARCHAR(255) COLLATE utf8mb4_bin NOT NULL',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_merge_recursive(self::TIMESTAMP_TO_DATETIME, self::CORRECOES) as $tabela => $alteracoes) {
            if (!Schema::hasTable($tabela)) {
                continue;
            }
            foreach ((array) $alteracoes as $alteracao) {
                DB::statement("ALTER TABLE `{$tabela}` {$alteracao}");
            }
        }
    }

    public function down(): void
    {
        // Sem volta: reverter para TIMESTAMP reintroduziria o limite de 2038 e a
        // conversao de fuso, e reverter os VARCHAR truncaria dados de producao.
    }
};
