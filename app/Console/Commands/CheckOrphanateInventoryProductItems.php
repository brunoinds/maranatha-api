<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Models\InventoryWarehouse;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckOrphanateInventoryProductItems extends Command
{
    protected $signature = 'inventory:check-orphans';

    protected $description = 'Check for orphaned InventoryProductItem and InventoryProductItemUncountable records (FK pointing to deleted models)';

    public function handle(): int
    {
        $this->info('Checking for orphaned inventory product items...');
        $this->newLine();

        $this->checkInventoryProductItems();
        $this->newLine();
        $this->checkInventoryProductItemUncountables();

        return self::SUCCESS;
    }

    private function checkInventoryProductItems(): void
    {
        $tableName = (new InventoryProductItem)->getTable();
        $productTable = (new InventoryProduct)->getTable();
        $warehouseTable = (new InventoryWarehouse)->getTable();
        $incomeTable = (new InventoryWarehouseIncome)->getTable();
        $outcomeTable = (new InventoryWarehouseOutcome)->getTable();

        $columns = [
            'inventory_product_id'       => $productTable,
            'inventory_warehouse_id'     => $warehouseTable,
            'inventory_warehouse_income_id'  => $incomeTable,
            'inventory_warehouse_outcome_id' => $outcomeTable,
        ];

        $rows = [];
        foreach ($columns as $fkColumn => $relatedTable) {
            $count = DB::table($tableName)
                ->leftJoin($relatedTable, "{$tableName}.{$fkColumn}", '=', "{$relatedTable}.id")
                ->whereNotNull("{$tableName}.{$fkColumn}")
                ->whereNull("{$relatedTable}.id")
                ->count();
            $rows[] = [$fkColumn, $relatedTable, $count];
        }

        $this->table(
            ['Column (IS NOT NULL)', 'Related table', 'Orphan count'],
            $rows
        );
        $this->info('InventoryProductItem: rows above are items with a non-null FK whose related row no longer exists.');
    }

    private function checkInventoryProductItemUncountables(): void
    {
        $tableName = (new InventoryProductItemUncountable)->getTable();
        $productTable = (new InventoryProduct)->getTable();
        $warehouseTable = (new InventoryWarehouse)->getTable();
        $incomeTable = (new InventoryWarehouseIncome)->getTable();
        $outcomeTable = (new InventoryWarehouseOutcome)->getTable();

        $columns = [
            'inventory_product_id'          => $productTable,
            'inventory_warehouse_id'        => $warehouseTable,
            'inventory_warehouse_income_id' => $incomeTable,
        ];

        $rows = [];
        foreach ($columns as $fkColumn => $relatedTable) {
            $count = DB::table($tableName)
                ->leftJoin($relatedTable, "{$tableName}.{$fkColumn}", '=', "{$relatedTable}.id")
                ->whereNotNull("{$tableName}.{$fkColumn}")
                ->whereNull("{$relatedTable}.id")
                ->count();
            $rows[] = [$fkColumn, $relatedTable, $count];
        }

        $existingOutcomeIds = DB::table($outcomeTable)->pluck('id')->all();

        $uncountablesWithOutcomeIds = DB::table($tableName)
            ->whereNotNull('inventory_warehouse_outcome_ids')
            ->where('inventory_warehouse_outcome_ids', '!=', '[]')
            ->get();

        $orphanOutcomeCount = 0;
        foreach ($uncountablesWithOutcomeIds as $row) {
            $ids = json_decode($row->inventory_warehouse_outcome_ids, true);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $outcomeId) {
                if (!in_array($outcomeId, $existingOutcomeIds, true)) {
                    $orphanOutcomeCount++;
                    break;
                }
            }
        }

        $rows[] = ['inventory_warehouse_outcome_ids (items with ≥1 missing outcome)', $outcomeTable, $orphanOutcomeCount];

        $this->table(
            ['Column (IS NOT NULL or has IDs)', 'Related table', 'Orphan count'],
            $rows
        );
        $this->info('InventoryProductItemUncountable: rows above are items with a non-null FK or outcome IDs whose related row(s) no longer exist.');
    }
}
