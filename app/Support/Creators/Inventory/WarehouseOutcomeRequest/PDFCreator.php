<?php


namespace App\Support\Creators\Inventory\WarehouseOutcomeRequest;

use App\Helpers\Toolbox;
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

    private InventoryWarehouseOutcomeRequest $outcome;

    private function __construct(InventoryWarehouseOutcomeRequest $outcome)
    {
        $this->outcome = $outcome;
        $this->loadTemplate($outcome);
        $this->loadPlaceholders($outcome);
        $this->loadItemsInTable();
        $this->loadQrCode();
    }


    private function loadTemplate()
    {
        $this->html = file_get_contents(base_path('app/Support/Creators/Inventory/WarehouseOutcomeRequest/PDFTemplate.html'));
    }
    private function loadPlaceholders(InventoryWarehouseOutcomeRequest $outcome)
    {
        $this->html = str_replace('{{$warehouseName}}', $outcome->warehouse->name, $this->html);
        $this->html = str_replace('{{$warehouseZone}}', $outcome->warehouse->zone, $this->html);
        $this->html = str_replace('{{$warehouseCountry}}', Toolbox::countryName($outcome->warehouse->country), $this->html);
        $this->html = str_replace('{{$date}}', Carbon::parse($outcome->date)->format('d/m/y'), $this->html);
        $this->html = str_replace('{{$job}}', $outcome->job_code . ' - ' .$outcome->job?->name, $this->html);
        $this->html = str_replace('{{$expense}}', $outcome->expense_code . ' - ' .$outcome->expense?->name, $this->html);
        $this->html = str_replace('{{$outcomeRequestId}}', $outcome->id, $this->html);
        $this->html = str_replace('{{$requestedBy}}', $outcome->user->name, $this->html);


        $maranathaLogoUrl = 'https://is1-ssl.mzstatic.com/image/thumb/Purple221/v4/ef/ca/d2/efcad26f-b207-0931-2a0a-93c13cf8d8b4/AppIcon-0-0-1x_U007epad-0-85-220.png/492x0w.webp';
        $logoBase64Src = 'data:image/png;base64,' . base64_encode(file_get_contents($maranathaLogoUrl));
        $this->html = str_replace('{{$maranathaLogo}}', $logoBase64Src, $this->html);
    }
    private function loadItemsInTable()
    {
        $productsImagesLoaded = [];
        collect($this->outcome->requested_products)->each(function($item) use (&$productsImagesLoaded ){
            $product = InventoryProduct::find($item['product_id']);
            if ($product->image !== null){
                $productsImagesLoaded[$product->id] = base64_encode(file_get_contents($product->image));
            }else{
                $productsImagesLoaded[$product->id] = null;
            }
        });

        $productsImagesLoaded = collect($productsImagesLoaded);

        $items = '';
        $iteration = 0;
        collect($this->outcome->requested_products)->each(function($item) use (&$items, &$iteration, &$productsImagesLoaded){
            $i = ++$iteration;

            $product = InventoryProduct::find($item['product_id']);
            $productName = $product->name;
            $productImage = $product->image;
            $productDescription = $product->description;
            $productBrand = $product->brand;
            $productQuantity = $item['quantity'];

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
            </tr>";
        });


        $finalPrice = $this->outcome->requestedProductsQuantity();

        $this->html = str_replace('{{$tableRows}}', $items, $this->html);
        $this->html = str_replace('{{$tableTotals}}', $finalPrice, $this->html);

    }
    private function loadQRCode()
    {
        $link = env('APP_URL') . '/app/inventory/outcome-requests/' . $this->outcome->id . '?view-mode=Dispacher';
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

    public static function new(InventoryWarehouseOutcomeRequest $outcome)
    {
        return new self($outcome);
    }
}
