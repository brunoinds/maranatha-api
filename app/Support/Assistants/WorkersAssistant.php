<?php

namespace App\Support\Assistants;

use App\Helpers\Enums\BalanceModel;
use App\Helpers\Enums\BalanceType;
use App\Models\Balance;
use App\Support\GoogleSheets\Excel;

class WorkersAssistant{
    public static function getListWorkers():array{
        $workers = collect(Excel::getWorkersSheet())->map(function($item){
            return [
                'dni' => $item['dni'],
                'name' => $item['name'],
                'team' => $item['team'],
            ];
        });
        return $workers->toArray();
    }
    public static function getWorkerByDNI(string $dni):array{
        $workers = self::getListWorkers();
        foreach($workers as $worker){
            if ($worker['dni'] === $id){
                return $worker;
            }
        }
        return null;
    }

}