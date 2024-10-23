<?php


namespace App\Support\Creators\Inventory\WarehouseOutcome;

use App\Helpers\Toolbox;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouseOutcomeRequest;
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

    private InventoryWarehouseOutcome|null $outcome = null;
    private InventoryWarehouseOutcomeRequest|null $outcomeRequest = null;

    private function __construct()
    {

    }


    public function addOutcomeRequest(InventoryWarehouseOutcomeRequest $outcomeRequest)
    {
        $this->outcomeRequest = $outcomeRequest;
    }
    public function addOutcome(InventoryWarehouseOutcome $outcome)
    {
        $this->outcome = $outcome;
    }

    private function loadTemplate()
    {
        $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcome/PDFTemplate.html'));
    }
    private function loadPlaceholders()
    {
        if (!is_null($this->outcome)){
            $this->html = str_replace('{{$warehouseName}}', $this->outcome->warehouse->name, $this->html);
            $this->html = str_replace('{{$warehouseZone}}', $this->outcome->warehouse->zone, $this->html);
            $this->html = str_replace('{{$warehouseCountry}}', Toolbox::countryName($this->outcome->warehouse->country), $this->html);
            $this->html = str_replace('{{$outcomeId}}', $this->outcome->id, $this->html);
            $this->html = str_replace('{{$date}}', Carbon::parse($this->outcome->date)->format('d/m/y'), $this->html);
            $this->html = str_replace('{{$job}}', $this->outcome->job_code . ' - ' .$this->outcome->job?->name, $this->html);
            $this->html = str_replace('{{$expense}}', $this->outcome->expense_code . ' - ' .$this->outcome->expense?->name, $this->html);
            $this->html = str_replace('{{$outcomeRequestId}}', $this->outcome->request?->id, $this->html);
            $this->html = str_replace('{{$dispatchedBy}}', $this->outcome->user?->name, $this->html);
            $this->html = str_replace('{{$requestedBy}}', $this->outcome->request?->user->name, $this->html);
        }elseif (!is_null($this->outcomeRequest)){
            $this->html = str_replace('{{$warehouseName}}', $this->outcomeRequest->warehouse->name, $this->html);
            $this->html = str_replace('{{$warehouseZone}}', $this->outcomeRequest->warehouse->zone, $this->html);
            $this->html = str_replace('{{$warehouseCountry}}', Toolbox::countryName($this->outcomeRequest->warehouse->country), $this->html);
            $this->html = str_replace('{{$outcomeId}}', $this->outcomeRequest->id, $this->html);
            $this->html = str_replace('{{$date}}', Carbon::parse($this->outcomeRequest->date)->format('d/m/y'), $this->html);
            $this->html = str_replace('{{$job}}', $this->outcomeRequest->job_code . ' - ' .$this->outcomeRequest->job?->name, $this->html);
            $this->html = str_replace('{{$expense}}', $this->outcomeRequest->expense_code . ' - ' .$this->outcomeRequest->expense?->name, $this->html);
            $this->html = str_replace('{{$outcomeRequestId}}', $this->outcomeRequest->id, $this->html);
            $this->html = str_replace('{{$dispatchedBy}}', '-', $this->html);
            $this->html = str_replace('{{$requestedBy}}', $this->outcomeRequest?->user->name, $this->html);
        }


        $maranathaLogoUrl = 'https://is1-ssl.mzstatic.com/image/thumb/Purple221/v4/ef/ca/d2/efcad26f-b207-0931-2a0a-93c13cf8d8b4/AppIcon-0-0-1x_U007epad-0-85-220.png/492x0w.webp';
        $logoBase64Src = 'data:image/png;base64,' . base64_encode(file_get_contents($maranathaLogoUrl));
        $this->html = str_replace('{{$maranathaLogo}}', $logoBase64Src, $this->html);
    }
    private function loadItemsInTable($items)
    {
        $data = $items;
        $items = '';
        foreach ($data['items'] as $item){
            if (is_null($item['image'])){
                $productImage = '';
            }else{
                $productImage = '<img src="data:image/png;base64,' . $item['image'] . '">';
            }


            $items .= '<tr>
                <td>' . $item["index"] . '</td>
                <td>' . $productImage . '</td>
                <td>' . $item["name"] . '</td>
                <td>' . $item["description"] . '</td>
                <td>' . $item["brand"] . '</td>
                <td>' . $item["quantity"] . '</td>
                <td>' . $item["unit_price"] . '</td>
                <td><b>' . $item["total_price"] . '</b></td>
            </tr>';
        }
        $finalPrice = '';

        foreach ($data['final_prices'] as $i => $price){
            if ($i > 0){
                $finalPrice .= '<br> +';
            }
            $finalPrice .= $price;
        }

        $this->html = str_replace('{{$tableRows}}', $items, $this->html);
        $this->html = str_replace('{{$tableTotals}}', $finalPrice, $this->html);
    }

    private function loadItemsResumeInTable($items)
    {
        $data = $items;
        $items = '';
        foreach ($data['items'] as $item){
            $items .= '<tr>
                <td>' . $item["index"] . '</td>
                <td>' . $item["name"] . '</td>
                <td>' . $item["brand"] . '</td>
                <td>' . $item["quantity"] . '</td>
                <td>' . $item["unit_price"] . '</td>
                <td><b>' . $item["total_price"] . '</b></td>
            </tr>';
        }
        $finalPrice = '';

        foreach ($data['final_prices'] as $i => $price){
            if ($i > 0){
                $finalPrice .= '<br> +';
            }
            $finalPrice .= $price;
        }

        $this->html = str_replace('{{$tableRowsResume}}', $items, $this->html);
        $this->html = str_replace('{{$tableTotalsResume}}', $finalPrice, $this->html);
    }

    private function getItems()
    {
        $productsIds = [];



        if (!is_null($this->outcome)){
            $this->outcome->items->groupBy(function($item){
                return $item->inventory_product_id ;
            })->each(function($groupedItems) use (&$productsIds){
                $productsIds[] = $groupedItems->first()->product->id;
            });
        }

        if (!is_null($this->outcomeRequest)){
            $this->outcomeRequest->loans->groupBy(function($item){
                return $item->inventory_product_item_id;
            })->each(function($groupedItems) use (&$productsIds){
                $productsIds[] = $groupedItems->first()->productItem->product->id;
            });
        }

        $productsImages = [];

        collect($productsIds)->each(function($productId) use (&$productsImages){
            $product = InventoryProduct::find($productId);
            if ($product->image !== null){
                $productsImages[$product->id] = base64_encode(file_get_contents($product->image));
            }else{
                $productsImages[$product->id] = null;
            }
        });



        $items = [];
        $iteration = 0;
        if (!is_null($this->outcome)){
            $this->outcome->items->groupBy(function($item){
                return $item->inventory_product_id . $item->sell_currency . $item->sell_amount;
            })->each(function($groupedItems) use (&$items, &$iteration, &$productsImages){
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


                if (isset($productsImages[$product->id]) && $productsImages[$product->id] !== null){
                    $productImage = $productsImages[$product->id];
                }else{
                    $productImage = null;
                }

                $items[] = [
                    'index' => $i,
                    'image' => $productImage,
                    'name' => $productName,
                    'description' => $productDescription,
                    'brand' => $productBrand,
                    'quantity' => $productQuantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice
                ];
            });
        }



        if (!is_null($this->outcomeRequest)){
            $this->outcomeRequest->loans->groupBy(function($loan){
                return $loan->productItem->product->id;
            })->each(function($groupedItems) use (&$items, &$iteration, &$productsImages){
                $i = ++$iteration;

                $product = $groupedItems->first()->productItem->product;
                $productName = $product->name;
                $productImage = $product->image;
                $productDescription = $product->description;
                $productBrand = $product->brand;
                $productQuantity = $groupedItems->count();


                if (isset($productsImages[$product->id]) && $productsImages[$product->id] !== null){
                    $productImage = $productsImages[$product->id];
                }else{
                    $productImage = null;
                }


                $items[] = [
                    'index' => $i,
                    'image' => $productImage,
                    'name' => $productName,
                    'description' => $productDescription,
                    'brand' => $productBrand,
                    'quantity' => $productQuantity,
                    'unit_price' => 'Préstamo',
                    'total_price' => 'Préstamo'
                ];
            });
        }




        $finalPrices = [];

        if (!is_null($this->outcome)){
            collect($this->outcome->amount())->each(function($item, $i) use (&$finalPrices){
                $finalPrices[] = Toolbox::moneyPrefix($item->currency) . ' ' . number_format($item->amount, 2);
            });
        }else{
            $finalPrices[] = '0.00';
        }

        return [
            'items' => $items,
            'final_prices' => $finalPrices
        ];
    }


    private function loadQRCode()
    {
        if (is_null($this->outcome)){
            $link = env('APP_URL') . '/app/inventory/outcome-requests/' . $this->outcomeRequest->id. '?view-mode=Dispacher';
        }else{
            $link = env('APP_URL') . '/app/inventory/warehouses/' . $this->outcome->inventory_warehouse_id;
        }

        $qrcode = (new QRCode)->render($link);
        $this->html = str_replace('{{$qrCode}}', $qrcode, $this->html);
    }


    public function create($options = []) : Dompdf
    {
        $this->loadTemplate();
        $this->loadPlaceholders($this->outcome);

        $items = $this->getItems();

        $this->loadItemsInTable($items);
        $this->loadItemsResumeInTable($items);
        $this->loadQrCode();

        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot([base_path('public') . '/storage/']);

        $dompdf->loadHtml($this->html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf;
    }

    public static function new()
    {
        return new self();
    }
}
