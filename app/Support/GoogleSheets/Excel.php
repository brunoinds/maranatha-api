<?php

namespace App\Support\GoogleSheets;

use Revolution\Google\Sheets\Facades\Sheets;

use Google\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class Excel{
    public static function updateDBSheet($output):void{
        $sheet = Sheets::spreadsheet(env('GOOGLE_SHEETS_DB_ID'))->sheet('🕋 BlackBox - Invoices');
        $workableRange = $sheet->range('A2:P600');


        //Clear database:
        $rows = array_fill(0, 599, "");
        collect($rows)->each(function($item, $index) use (&$rows){
            $rows[$index] = array_fill(0, 16, "");
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
                $item['payment_status']
            ];
        });

        $sheet->append($output->toArray());
    }
    public static function getWorkersSheet():array{
        $cachedValue = Cache::store('file')->get('Maranatha/Spreadsheets/Workers');

        if ($cachedValue !== null){
            return $cachedValue;
        }


        $workers = null;
        $paymentsMonths = null;
        try {
            $sheet = Sheets::spreadsheet(env('GOOGLE_SHEETS_DB_ID'))->sheet('👷 Workers');
            $workers = $sheet->range('A4:Z600')->all();
            $paymentsMonths = $sheet->range('G3:Z3')->all()[0];
        } catch (\Google\Service\Exception $exception) {
            Log::warning('Failed to get Workers from Google Spreadsheet Workers', ['exception' => $exception]);
            return [];
        }


        $data = collect($workers)->filter(function($item){
            return $item[0] !== "";
        })->map(function($item) use ($paymentsMonths){
            return [
                'dni' => $item[0],
                'name' => isset($item[1]) ? $item[1] : "",
                'team' => isset($item[2]) ? $item[2] : "0",
                'supervisor' => isset($item[3]) ? $item[3] : "",
                'function' => isset($item[4]) ? $item[4] : "",
                'is_active' => isset($item[5]) ? ($item[5] == 'Sí' ? true : false) : true,
                'payments' => (function() use ($paymentsMonths, $item){
                    $payments = [];
                    foreach($paymentsMonths as $index => $paymentMonth){
                        $amount = isset($item[$index + 6]) ? $item[$index + 6] : "S/.0.00";
                        $amount = str_replace('S/.', '', str_replace(',', '', $amount));
                        $amount = floatval($amount);

                        $payments[] = [
                            'month_year' => $paymentMonth,
                            'month' => intval(explode('/', $paymentMonth)[0]),
                            'year' => intval(explode('/', $paymentMonth)[1]),
                            'amount' => $amount,
                            'timespan' => [
                                'start' => Carbon::createFromFormat('m/Y', $paymentMonth)->timezone('America/Lima')->startOfMonth()->format('c'),
                                'end' => Carbon::createFromFormat('m/Y', $paymentMonth)->timezone('America/Lima')->endOfMonth()->endOfDay()->format('c'),
                            ]
                        ];
                    }
                    return $payments;
                })()
            ];
        })->toArray();

        //Store for 30 minutes:
        Cache::store('file')->put('Maranatha/Spreadsheets/Workers', $data, 30 * 60);

        return $data;
    }
}
