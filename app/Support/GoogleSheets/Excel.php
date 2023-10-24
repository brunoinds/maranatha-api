<?php

namespace App\Support\GoogleSheets;

use Revolution\Google\Sheets\Facades\Sheets;

use Google\Client;

class Excel{

    public static function updateDBSheet($output):void{
        $sheet = Sheets::spreadsheet('1nHQT3pRi3zlt2i5Av2ZogWlj9IqEO3InBi3ASsXcZlU')->sheet('DB');
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
                $item['identifier'],
                $item['report']['date'],
                $item['report']['amount'],
                $item['report']['identifier'],
            ];
        });



        $sheet->append($output->toArray());
    }
}