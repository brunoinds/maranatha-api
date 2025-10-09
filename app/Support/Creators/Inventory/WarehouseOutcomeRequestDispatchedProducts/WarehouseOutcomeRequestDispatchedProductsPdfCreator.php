<?php


namespace App\Support\Creators\Inventory\WarehouseOutcomeRequestDispatchedProducts;

use App\Helpers\Toolbox;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouseOutcome;
use Carbon\Carbon;
use Dompdf\Dompdf;
use chillerlan\QRCode\QRCode;


class WarehouseOutcomeRequestDispatchedProductsPdfCreator
{
    private $html = '';

    private InventoryWarehouseOutcomeRequest $outcomeRequest;
    private InventoryWarehouseOutcome|null $outcome = null;

    private function __construct(InventoryWarehouseOutcomeRequest $outcomeRequest)
    {
        $this->outcomeRequest = $outcomeRequest;

        if (!is_null($outcomeRequest->outcome)){
            $this->outcome = $outcomeRequest->outcome;
        }
    }


    private function loadTemplate($options = ['withImages' => true])
    {
        if ($options['withImages']){
            $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcomeRequestDispatchedProducts/Templates/WithImages.html'));
        }else{
            $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcomeRequestDispatchedProducts/Templates/WithoutImages.html'));
        }
    }

    private function loadPlaceholders()
    {
        $this->html = str_replace('{{$warehouseName}}', $this->outcomeRequest->warehouse->name, $this->html);
        $this->html = str_replace('{{$warehouseZone}}', $this->outcomeRequest->warehouse->zone, $this->html);
        $this->html = str_replace('{{$warehouseCountry}}', Toolbox::countryName($this->outcomeRequest->warehouse->country), $this->html);
        $this->html = str_replace('{{$date}}', Carbon::parse($this->outcomeRequest->date)->format('d/m/y'), $this->html);
        $this->html = str_replace('{{$job}}', $this->outcomeRequest->job_code . ' - ' .$this->outcomeRequest->job?->name, $this->html);
        $this->html = str_replace('{{$expense}}', $this->outcomeRequest->expense_code . ' - ' .$this->outcomeRequest->expense?->name, $this->html);
        $this->html = str_replace('{{$outcomeRequestId}}', $this->outcomeRequest->id, $this->html);
        $this->html = str_replace('{{$requestedBy}}', $this->outcomeRequest->user->name, $this->html);
        $this->html = str_replace('{{$dispatchedBy}}', $this->outcomeRequest->outcome?->user?->name, $this->html);



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


    private function loadQRCode()
    {
        $link = env('APP_URL') . '/app/inventory/outcome-requests/' . $this->outcomeRequest->id . '?view-mode=Dispacher';
        $qrcode = (new QRCode)->render($link);
        $this->html = str_replace('{{$qrCode}}', $qrcode, $this->html);
    }



    private function getItems($options = ['withImages' => true])
    {
        $productsIds = [];

        if (!is_null($this->outcome)){
            $this->outcome->items->groupBy(function($item){
                return $item->inventory_product_id ;
            })->each(function($groupedItems) use (&$productsIds){
                $productsIds[] = $groupedItems->first()->product->id;
            });

            $this->outcome->uncountableItems->groupBy(function($item){
                return $item->inventory_product_id ;
            })->each(function($groupedItems) use (&$productsIds){
                $productsIds[] = $groupedItems->first()->product->id;
            });
        }

        $this->outcomeRequest->loans->groupBy(function($item){
            return $item->inventory_product_item_id;
        })->each(function($groupedItems) use (&$productsIds){
            $productsIds[] = $groupedItems->first()->productItem->product->id;
        });

        $productsImages = [];

        collect($productsIds)->each(function($productId) use (&$productsImages, &$options){
            $product = InventoryProduct::find($productId);
            if ($product->image !== null && $options['withImages']){
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
            $outcomeInstance = $this->outcome;

            $this->outcome->uncountableItems->groupBy(function($item) use ($outcomeInstance){
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



    public function create($options = ['withImages' => true]) : Dompdf
    {
        $this->loadTemplate($options);
        $this->loadPlaceholders();

        $items = $this->getItems($options);

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

    public static function new(InventoryWarehouseOutcomeRequest $outcomeRequest)
    {
        return new self($outcomeRequest);
    }
}
