<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Balance;
use Illuminate\Support\Collection;
use App\Helpers\Enums\BalanceModel;

class FixDuplicatedBalances extends Command
{
    protected $signature = 'balances:fix-duplicateds';

    protected $description = 'Fix duplicated balances in the database';

    public function handle()
    {
        $this->removeDuplicates();
    }

    private function removeDuplicates(): void
    {
        Balance::whereNotNull('report_id')->where('model', BalanceModel::Expense)->get()->groupBy('report_id')->each(function($group){
            if ($group->count() > 1){
                $group->each(function($balance, $index) use ($group){
                    if ($index === 0){
                        return;
                    }
                    $this->info('Deleting duplicate balanceId ' . $balance->id . ' from reportId ' . $balance->report_id . '. The original balanceId is ' . $group->first()->id . ' and will be kept.');
                    $balance->delete();
                });
            }
        });
    }

}
