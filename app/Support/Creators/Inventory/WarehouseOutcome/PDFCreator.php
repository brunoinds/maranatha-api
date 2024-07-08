<?php


namespace App\Support\Creators\Inventory\WarehouseOutcome;

use App\Helpers\Toolbox;
use App\Models\InventoryWarehouseOutcome;
use App\Models\Report;
use App\Support\Assistants\ReportAssistant;
use DateTime;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use chillerlan\QRCode\QRCode;


class PDFCreator
{
    private $html = '';

    private InventoryWarehouseOutcome $outcome;

    private function __construct(InventoryWarehouseOutcome $outcome)
    {
        $this->outcome = $outcome;
        $this->loadTemplate($outcome);
        $this->loadPlaceholders($outcome);
        $this->loadItemsInTable();
        $this->loadQrCode();
    }


    private function loadTemplate()
    {
        $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcome/PDFTemplate.html'));
    }
    private function loadPlaceholders(InventoryWarehouseOutcome $outcome)
    {
        $this->html = str_replace('{{$warehouseName}}', $outcome->warehouse->name, $this->html);
        $this->html = str_replace('{{$warehouseZone}}', $outcome->warehouse->zone, $this->html);
        $this->html = str_replace('{{$warehouseCountry}}', Toolbox::countryName($outcome->warehouse->country), $this->html);
        $this->html = str_replace('{{$outcomeId}}', $outcome->id, $this->html);
        $this->html = str_replace('{{$date}}', Carbon::parse($outcome->date)->format('d/m/y'), $this->html);
        $this->html = str_replace('{{$job}}', $outcome->job_code . ' - ' .$outcome->job?->name, $this->html);
        $this->html = str_replace('{{$expense}}', $outcome->expense_code . ' - ' .$outcome->expense?->name, $this->html);
        $this->html = str_replace('{{$outcomeRequestId}}', $outcome->request?->id, $this->html);
        $this->html = str_replace('{{$dispatchedBy}}', $outcome->user?->name, $this->html);
        $this->html = str_replace('{{$requestedBy}}', $outcome->request?->user->name, $this->html);


        $maranathaLogoUrl = 'https://is1-ssl.mzstatic.com/image/thumb/Purple221/v4/ef/ca/d2/efcad26f-b207-0931-2a0a-93c13cf8d8b4/AppIcon-0-0-1x_U007epad-0-85-220.png/492x0w.webp';
        $logoBase64Src = 'data:image/png;base64,' . base64_encode(file_get_contents($maranathaLogoUrl));
        $this->html = str_replace('{{$maranathaLogo}}', $logoBase64Src, $this->html);
    }
    private function loadItemsInTable()
    {
        $productsImagesLoaded = $this->outcome->items->groupBy(function($item){
            return $item->inventory_product_id ;
        })->map(function($groupedItems){
            if ( $groupedItems->first()->product->image !== null){
                return base64_encode(file_get_contents($groupedItems->first()->product->image));
            }else{
                return null;
            }
        });


        $items = '';
        $iteration = 0;
        $this->outcome->items->groupBy(function($item){
            return $item->inventory_product_id . $item->sell_currency . $item->sell_amount;
        })->each(function($groupedItems) use (&$items, &$iteration, &$productsImagesLoaded){
            $i = ++$iteration;

            $product = $groupedItems->first()->product;
            $productName = $product->name;
            $productImage = $product->image;
            $productDescription = $product->description;
            $productBrand = $product->brand;
            $productTotalAmount = $groupedItems->sum('sell_amount');
            $productUnitAmount = $groupedItems->first()->sell_amount;
            $productCurrency = $groupedItems->first()->sell_currency;
            $productQuantity = $groupedItems->count();

            $unitPrice = Toolbox::moneyPrefix($productCurrency) . ' ' . number_format($productUnitAmount, 2);
            $totalPrice = Toolbox::moneyPrefix($productCurrency) . ' ' . number_format($productTotalAmount, 2);


            if ($productsImagesLoaded->has($product->id) && $productsImagesLoaded->get($product->id) !== null){
                $productImage = '<img src="data:image/png;base64,' . $productsImagesLoaded->get($product->id) . '">';
            }else{
                $productImage = '';
            }


            $items .= "<tr>
                <td>$i</td>
                <td>$productImage</td>
                <td>$productName</td>
                <td>$productDescription</td>
                <td>$productBrand</td>
                <td>$productQuantity</td>
                <td>$unitPrice</td>
                <td><b>$totalPrice</b></td>
            </tr>";
        });


        $finalPrice = '';

        collect($this->outcome->amount())->each(function($item, $i) use (&$finalPrice){
            if ($i > 0){
                $finalPrice .= '<br> +';
            }
            $finalPrice .= Toolbox::moneyPrefix($item->currency) . ' ' . number_format($item->amount, 2) . ' ';
        });


        $this->html = str_replace('{{$tableRows}}', $items, $this->html);
        $this->html = str_replace('{{$tableTotals}}', $finalPrice, $this->html);

    }
    private function loadQRCode()
    {
        if ($this->outcome->request === null){
            $link = env('APP_URL') . '/app/inventory/warehouses/' . $this->outcome->inventory_warehouse_id;
        }else{
            $link = env('APP_URL') . '/app/inventory/outcome-requests/' . $this->outcome->request->id;
        }
        $qrcode = (new QRCode)->render($link);
        $this->html = str_replace('{{$qrCode}}', $qrcode, $this->html);
    }




    public function create($options = []) : Dompdf
    {
        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot([base_path('public') . '/storage/']);

        $dompdf->loadHtml($this->html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf;
    }

    public static function new(InventoryWarehouseOutcome $outcome)
    {
        return new self($outcome);
    }
}
