<?php


namespace App\Support\Creators\Inventory\WarehouseOutcomeProducts;

use App\Helpers\Toolbox;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryProduct;
use Carbon\Carbon;
use Dompdf\Dompdf;
use chillerlan\QRCode\QRCode;


class WarehouseOutcomeProductsPdfCreator
{
    private $html = '';

    private InventoryWarehouseOutcome $outcome;

    private function __construct(InventoryWarehouseOutcome $outcome)
    {
        $this->outcome = $outcome;
    }


    private function loadTemplate($options = ['withImages' => true])
    {
        if ($options['withImages']){
            $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcomeProducts/Templates/WithImages.html'));
        }else{
            $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcomeProducts/Templates/WithoutImages.html'));
        }
    }

    private function loadPlaceholders()
    {
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

        $maranathaLogoUrl = 'https://is1-ssl.mzstatic.com/image/thumb/Purple221/v4/ef/ca/d2/efcad26f-b207-0931-2a0a-93c13cf8d8b4/AppIcon-0-0-1x_U007epad-0-85-220.png/492x0w.webp';
        $logoBase64Src = 'data:image/png;base64,' . base64_encode(file_get_contents($maranathaLogoUrl));
        $this->html = str_replace('{{$maranathaLogo}}', $logoBase64Src, $this->html);
    }


    private function loadItemsWithImages($items)
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

    private function loadItemsWithoutImages($items)
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

        $this->html = str_replace('{{$tableRows}}', $items, $this->html);
        $this->html = str_replace('{{$tableTotals}}', $finalPrice, $this->html);
    }

    private function getItems($options = ['withImages' => true])
    {
        $productsIds = [];

        $this->outcome->items->groupBy(function($item){
            return $item->inventory_product_id ;
        })->each(function($groupedItems) use (&$productsIds){
            $productsIds[] = $groupedItems->first()->product->id;
        });

        $this->outcome->uncountableItems()->groupBy(function($item){
            return $item->inventory_product_id ;
        })->each(function($groupedItems) use (&$productsIds){
            $productsIds[] = $groupedItems->first()->product->id;
        });

        $productsImages = [];

        collect($productsIds)->each(function($productId) use (&$productsImages, &$options){
            $product = InventoryProduct::find($productId);
            if ($product->image !== null){

                if (!$options['withImages']){
                    $productsImages[$product->id] = null;
                }

                //Try catch file_get_contents, if error, append null:
                $image = @file_get_contents($product->image);

                if ($image !== false){
                    $productsImages[$product->id] = base64_encode($image);
                }else{
                    $productsImages[$product->id] = null;
                }
            }else{
                $productsImages[$product->id] = null;
            }
        });


        $outcomeInstance = $this->outcome;

        $items = [];
        $iteration = 0;
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

        $this->outcome->uncountableItems()->groupBy(function($item) use ($outcomeInstance){
            return $item->inventory_product_id . $item->outcomes_details[$outcomeInstance->id]['sell_currency'] . $item->outcomes_details[$outcomeInstance->id]['sell_amount'];
        })->each(function($groupedItems) use (&$items, &$iteration, &$productsImages, $outcomeInstance){
            $i = ++$iteration;

            $product = $groupedItems->first()->product;
            $productName = $product->name;
            $productImage = $product->image;
            $productDescription = $product->description;
            $productBrand = $product->brand;


            $productTotalAmount = $groupedItems->first()->outcomes_details[$outcomeInstance->id]['sell_amount'];
            $productUnitAmount = $groupedItems->first()->calculateSellPriceFromBuyPrice(1);
            $productCurrency = $groupedItems->first()->outcomes_details[$outcomeInstance->id]['sell_currency'];
            $productQuantity = $groupedItems->first()->outcomes_details[$outcomeInstance->id]['quantity'];

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


        $finalPrices = [];

        collect($this->outcome->amount())->each(function($item, $i) use (&$finalPrices){
            $finalPrices[] = Toolbox::moneyPrefix($item->currency) . ' ' . number_format($item->amount, 2);
        });

        return [
            'items' => $items,
            'final_prices' => $finalPrices
        ];
    }


    private function loadQRCode()
    {
        if (!is_null($this->outcome->outcomeRequest)){
            $link = env('APP_URL') . '/app/inventory/outcome-requests/' . $this->outcome->outcomeRequest->id. '?view-mode=Dispacher';
        }else{
            $link = env('APP_URL') . '/app/inventory/warehouses/' . $this->outcome->inventory_warehouse_id;
        }

        $qrcode = (new QRCode)->render($link);
        $this->html = str_replace('{{$qrCode}}', $qrcode, $this->html);
    }


    public function create($options = ['withImages' => true]) : Dompdf
    {
        $this->loadTemplate($options);
        $this->loadPlaceholders($this->outcome);
        $items = $this->getItems();

        if ($options['withImages']){
            $this->loadItemsWithImages($items);
        }else{
            $this->loadItemsWithoutImages($items);
        }

        $this->loadQrCode();

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
