<?php

namespace App\Support\GoogleSheets;

use Revolution\Google\Sheets\Facades\Sheets;

use Google\Client;

class Excel{
    public static function updateDBSheet($output):void{
        $sheet = Sheets::spreadsheet(env('GOOGLE_SHEETS_DB_ID'))->sheet('DB');
        $workableRange = $sheet->range('A2:N600');

        
        //Clear database:
        $rows = array_fill(0, 599, "");
        collect($rows)->each(function($item, $index) use (&$rows){
            $rows[$index] = array_fill(0, 14, "");
        });
        $workableRange->update($rows);



        

        //Fill database:
        $output = collect($output)->map(function($item){
            return [
                $item['type'],
                $item['user'],
                $item['creation_date'],
                $item['consumption_date'],
                $item['commerce_number'],
                $item['ticket_number'],
                $item['description'],
                $item['expense_code'],
                $item['job_code'],
                $item['amount'],
                $item['report']['money_type'],
                $item['identifier'],
                $item['report']['date'],
                $item['report']['amount'],
                $item['report']['identifier'],
            ];
        });



        $sheet->append($output->toArray());
    }
    public static function getWorkersSheet():array{
        $sheet = Sheets::spreadsheet(env('GOOGLE_SHEETS_DB_ID'))->sheet('👷 Workers');
        $workers = $sheet->range('A4:Z600')->all();
        $paymentsMonths = $sheet->range('D3:Z3')->all()[0];

        $data = collect($workers)->filter(function($item){
            return $item[0] !== "";
        })->map(function($item) use ($paymentsMonths){
            return [
                'dni' => $item[0],
                'name' => isset($item[1]) ? $item[1] : "",
                'team' => isset($item[2]) ? intval($item[2]) : 0,
                'payments' => (function() use ($paymentsMonths, $item){
                    $payments = [];
                    foreach($paymentsMonths as $index => $paymentMonth){
                        $amount = isset($item[$index + 3]) ? $item[$index + 3] : "S/.0.00";
                        $amount = str_replace('S/.', '', $amount);
                        $amount = floatval($amount);

                        $payments[] = [
                            'month' => intval(explode('/', $paymentMonth)[0]),
                            'year' => intval(explode('/', $paymentMonth)[1]),
                            'amount' => $amount,
                        ];
                    }
                    return $payments;
                })()
            ];
        })->toArray();

        return $data;
    }
}