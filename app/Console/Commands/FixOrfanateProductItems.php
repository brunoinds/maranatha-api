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

class FixOrfanateProductItems extends Command
{
    protected $signature = 'inventory:fix-orfanate-items';

    protected $description = 'Fix orphaned product items: delete items with broken FKs, fix uncountable outcome_ids so check-orphans returns none';

    public function handle(): int
    {
        $this->info('Fixing orphaned inventory product items...');
        $this->newLine();

        $itemsTable = (new InventoryProductItem)->getTable();
        $uncountableTable = (new InventoryProductItemUncountable)->getTable();
        $productTable = (new InventoryProduct)->getTable();
        $warehouseTable = (new InventoryWarehouse)->getTable();
        $incomeTable = (new InventoryWarehouseIncome)->getTable();
        $outcomeTable = (new InventoryWarehouseOutcome)->getTable();

        // --- InventoryProductItem: collect all IDs with any orphaned FK ---
        $orphanedItemIds = collect();
        $columns = [
            'inventory_product_id' => $productTable,
            'inventory_warehouse_id' => $warehouseTable,
            'inventory_warehouse_income_id' => $incomeTable,
            'inventory_warehouse_outcome_id' => $outcomeTable,
        ];
        foreach ($columns as $fkColumn => $relatedTable) {
            $ids = DB::table($itemsTable)
                ->leftJoin($relatedTable, "{$itemsTable}.{$fkColumn}", '=', "{$relatedTable}.id")
                ->whereNotNull("{$itemsTable}.{$fkColumn}")
                ->whereNull("{$relatedTable}.id")
                ->pluck("{$itemsTable}.id");
            $orphanedItemIds = $orphanedItemIds->merge($ids);
        }
        $orphanedItemIds = $orphanedItemIds->unique()->values();

        $deletedCountable = 0;
        foreach ($orphanedItemIds as $id) {
            $item = InventoryProductItem::find($id);
            if ($item) {
                $item->delete();
                $deletedCountable++;
            }
        }
        if ($deletedCountable > 0) {
            $this->info("InventoryProductItem: deleted {$deletedCountable} orphaned item(s).");
        }

        // --- InventoryProductItemUncountable: 1) fix outcome_ids with missing IDs ---
        $existingOutcomeIds = DB::table($outcomeTable)->pluck('id')->all();
        $existingOutcomeIdsSet = array_flip($existingOutcomeIds);

        $uncountablesWithOutcomeIds = DB::table($uncountableTable)
            ->whereNotNull('inventory_warehouse_outcome_ids')
            ->where('inventory_warehouse_outcome_ids', '!=', '[]')
            ->get();

        $fixedOutcomeIds = 0;
        foreach ($uncountablesWithOutcomeIds as $row) {
            $ids = json_decode($row->inventory_warehouse_outcome_ids, true);
            if (!is_array($ids)) {
                continue;
            }
            $missingInRow = array_filter($ids, fn($id) => !isset($existingOutcomeIdsSet[$id]));
            if (empty($missingInRow)) {
                continue;
            }
            $model = InventoryProductItemUncountable::find($row->id);
            if (!$model) {
                continue;
            }
            foreach ($missingInRow as $outcomeId) {
                $model->removeOutcomeId((int) $outcomeId);
                $fixedOutcomeIds++;
            }
        }
        if ($fixedOutcomeIds > 0) {
            $this->info("InventoryProductItemUncountable: removed {$fixedOutcomeIds} missing outcome ID(s) from outcome_ids.");
        }

        // --- InventoryProductItemUncountable: 2) delete rows with orphaned product/warehouse/income ---
        $uncountableColumns = [
            'inventory_product_id' => $productTable,
            'inventory_warehouse_id' => $warehouseTable,
            'inventory_warehouse_income_id' => $incomeTable,
        ];
        $orphanedUncountableIds = collect();
        foreach ($uncountableColumns as $fkColumn => $relatedTable) {
            $ids = DB::table($uncountableTable)
                ->leftJoin($relatedTable, "{$uncountableTable}.{$fkColumn}", '=', "{$relatedTable}.id")
                ->whereNotNull("{$uncountableTable}.{$fkColumn}")
                ->whereNull("{$relatedTable}.id")
                ->pluck("{$uncountableTable}.id");
            $orphanedUncountableIds = $orphanedUncountableIds->merge($ids);
        }
        $orphanedUncountableIds = $orphanedUncountableIds->unique()->values();

        $deletedUncountable = 0;
        foreach ($orphanedUncountableIds as $id) {
            $item = InventoryProductItemUncountable::find($id);
            if ($item) {
                $item->delete();
                $deletedUncountable++;
            }
        }
        if ($deletedUncountable > 0) {
            $this->info("InventoryProductItemUncountable: deleted {$deletedUncountable} orphaned record(s).");
        }

        $total = $deletedCountable + $fixedOutcomeIds + $deletedUncountable;
        if ($total === 0) {
            $this->info('No orphaned items to fix.');
        } else {
            $this->newLine();
            $this->info("Done. Run <info>php artisan inventory:check-orfanate-items</info> to verify no orphans remain.");
        }

        return self::SUCCESS;
    }
}
