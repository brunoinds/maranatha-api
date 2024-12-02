<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouseOutcome;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestStatus;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestType;
use OneSignal;
use App\Helpers\Toolbox;


class InventoryWarehouseOutcomeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_warehouse_id',
        'inventory_warehouse_outcome_id',
        'user_id',
        'description',
        'requested_products',
        'received_products',
        'messages',
        'requested_at',
        'rejected_at',
        'approved_at',
        'dispatched_at',
        'delivered_at',
        'finished_at',
        'on_the_way_at',
        'job_code',
        'expense_code',
        'status'
    ];

    protected $casts = [
        'requested_products' => 'array',
        'received_products' => 'array',
        'messages' => 'array',
        'status' => InventoryWarehouseOutcomeRequestStatus::class
    ];

    public function addRequestedProduct(int $product_id, float $quantity)
    {
        $this->requested_products = array_merge($this->requested_products, [['product_id' => (int) $product_id, 'quantity' => (float) $quantity]]);
        $this->save();
    }

    public function requestedProductsQuantity()
    {
        return array_reduce($this->requested_products, function ($carry, $requestedProduct) {
            return $carry + $requestedProduct['quantity'];
        }, 0);
    }

    public function requestType(): InventoryWarehouseOutcomeRequestType
    {

        $first = collect($this->requested_products)->first();


        if (!$first){
            return InventoryWarehouseOutcomeRequestType::Outcomes;
        }


        $product = InventoryProduct::find($this->requested_products[0]['product_id']);


        if (!$product) {
            return InventoryWarehouseOutcomeRequestType::Outcomes;
        }

        return $product->is_loanable ? InventoryWarehouseOutcomeRequestType::Loans : InventoryWarehouseOutcomeRequestType::Outcomes;
    }

    public function changeStatus(InventoryWarehouseOutcomeRequestStatus $status)
    {
        $notificationUrlOnUserReports = env('APP_WEB_URL') . '/inventory/outcome-requests/' . $this->id . '?view-mode=';

        $previousStatus = $this->status;
        $newStatus = $status;

        $notifications = [];

        //Progressive timeline:
        if ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Draft && $newStatus === InventoryWarehouseOutcomeRequestStatus::Requested) {
            //Notify warehouse owner, new request incoming
            $notifications[] = [
                'headings' => '📋 Nuevo pedido recibido',
                'message' => $this->user->name . " ha enviado un nuevo pedido de " . $this->requestedProductsQuantity() . " productos y está esperando por su aprobación.",
                'users_ids' => $this->warehouse->owners,
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Dispacher'
                ]
            ];
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Requested && $newStatus === InventoryWarehouseOutcomeRequestStatus::Rejected) {
            //Notify request owner, the request has been rejected
            $notifications[] = [
                'headings' => '❌ Pedido rechazado',
                'message' => "El administrador del almacén ha rechazado su pedido #00" . $this->id . " con " . $this->requestedProductsQuantity() . " productos. Ingresa al chat para más detalles.",
                'users_ids' => [$this->user->id],
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Requester'
                ]
            ];
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Requested && $newStatus === InventoryWarehouseOutcomeRequestStatus::Approved) {
            //Notify request owner, the request has been approved

            $notifications[] = [
                'headings' => '✅ Pedido aprobado',
                'message' => "El administrador del almacén ha aprobado su pedido #00" . $this->id . " con " . $this->requestedProductsQuantity() . " productos. En este momento el almacén lo está alistando en el stock.",
                'users_ids' => [$this->user->id],
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Requester'
                ]
            ];
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Approved && $newStatus === InventoryWarehouseOutcomeRequestStatus::Dispatched) {
            //Notify request owner, the request has dispatched
            $notifications[] = [
                'headings' => '📦 Pedido listo para envío',
                'message' => "Su pedido #00" . $this->id . " con " . $this->requestedProductsQuantity() . " productos está listo para ser envíado a su dirección de entrega. Luego saldrá del almacén hacia ti.",
                'users_ids' => [$this->user->id],
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Requester'
                ]
            ];
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Dispatched && $newStatus === InventoryWarehouseOutcomeRequestStatus::OnTheWay) {
            //Notify request owner, the request has being on the way
            $notifications[] = [
                'headings' => '🚚 Pedido en camino',
                'message' => "Su pedido #00" . $this->id . " con " . $this->requestedProductsQuantity() . " productos salió del almacén y está en camino a su dirección. Al recibirlo, debes de confirmar la entrega en la aplicación y luego revisar si todos los productos han llegado.",
                'users_ids' => [$this->user->id],
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Requester'
                ]
            ];
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::OnTheWay && $newStatus === InventoryWarehouseOutcomeRequestStatus::Delivered) {
            //Notify warehouse owner, the request has been received and its being checked.
            $notifications[] = [
                'headings' => '📍 Pedido recibido',
                'message' => "El pedido #00" . $this->id . " de " . $this->user->name . " con "  . $this->requestedProductsQuantity() . " productos ha sido entregado y está siendo revisado por él. ",
                'users_ids' => $this->warehouse->owners,
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Dispacher'
                ]
            ];
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Delivered && $newStatus === InventoryWarehouseOutcomeRequestStatus::Finished) {
            $this->loans->each(function ($loan) {
                $loan->doReceivedFromWarehouse();
            });

            //Notify warehouse owner, the request has been received and its checked.
            $notifications[] = [
                'headings' => '✅ Pedido finalizado',
                'message' => "El pedido #00" . $this->id . " de " . $this->user->name . " con "  . $this->requestedProductsQuantity() . " productos ha sido revisado y finalizado. Cualquier duda, contactálo desde el chat.",
                'users_ids' => $this->warehouse->owners,
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports . 'Dispacher'
                ]
            ];
        }


        //Regressive timeline:
        if ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Finished && $newStatus === InventoryWarehouseOutcomeRequestStatus::Delivered) {
            //NOT, notify warehouse owner, was finished but its reopend
            $this->loans->each(function ($loan) {
                $loan->undoReceivedFromWarehouse();
            });

        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Delivered && $newStatus === InventoryWarehouseOutcomeRequestStatus::OnTheWay) {
            //NOT, notify warehouse owner, was delivered but its on the way again
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::OnTheWay && $newStatus === InventoryWarehouseOutcomeRequestStatus::Dispatched) {
            //NOT, notify request owner, was on the way but its dispatched again
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Dispatched && $newStatus === InventoryWarehouseOutcomeRequestStatus::Approved) {
            //NOT, notify request owner, was dispatched but its approved again
            //Undo dispatch:
            $this->outcome?->delete();
            $this->loans()->delete();
            $this->inventory_warehouse_outcome_id = null;
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Approved && $newStatus === InventoryWarehouseOutcomeRequestStatus::Requested) {
            //NOT, notify request owner, was approved but its requested again
        }elseif ($previousStatus === InventoryWarehouseOutcomeRequestStatus::Requested && $newStatus === InventoryWarehouseOutcomeRequestStatus::Draft) {
            //NOT, notify warehouse owner, was requested but its drafted again
        }

        $this->status = $status;

        if ($status === InventoryWarehouseOutcomeRequestStatus::Draft) {
            $this->requested_at = null;
            $this->rejected_at = null;
            $this->approved_at = null;
            $this->dispatched_at = null;
            $this->on_the_way_at = null;
            $this->delivered_at = null;
            $this->finished_at = null;
        } elseif ($status === InventoryWarehouseOutcomeRequestStatus::Requested) {
            $this->requested_at = now();
            $this->rejected_at = null;
            $this->approved_at = null;
            $this->dispatched_at = null;
            $this->on_the_way_at = null;
            $this->delivered_at = null;
            $this->finished_at = null;
        } else if ($status === InventoryWarehouseOutcomeRequestStatus::Rejected) {
            $this->rejected_at = now();
            $this->approved_at = null;
            $this->dispatched_at = null;
            $this->on_the_way_at = null;
            $this->delivered_at = null;
            $this->finished_at = null;
        } elseif ($status === InventoryWarehouseOutcomeRequestStatus::Approved) {
            $this->approved_at = now();
            $this->rejected_at = null;
            $this->dispatched_at = null;
            $this->on_the_way_at = null;
            $this->delivered_at = null;
            $this->finished_at = null;
        } elseif ($status === InventoryWarehouseOutcomeRequestStatus::Dispatched) {
            $this->dispatched_at = now();
            $this->on_the_way_at = null;
            $this->delivered_at = null;
            $this->finished_at = null;
        } elseif ($status === InventoryWarehouseOutcomeRequestStatus::OnTheWay) {
            $this->on_the_way_at = now();
            $this->delivered_at = null;
            $this->finished_at = null;
        } elseif ($status === InventoryWarehouseOutcomeRequestStatus::Delivered) {
            $this->delivered_at = now();
            $this->finished_at = null;
        } elseif ($status === InventoryWarehouseOutcomeRequestStatus::Finished) {
            $this->finished_at = now();
        }
        $this->save();

        if (env('APP_ENV') !== 'local'){
            foreach ($notifications as $notification) {
                foreach ($notification['users_ids'] as $userId) {
                    OneSignal::sendNotificationToExternalUser(
                        headings: $notification['headings'],
                        message: $notification['message'],
                        userId: Toolbox::getOneSignalUserId($userId),
                        data: $notification['data']
                    );
                }
            }
        }

    }

    public function warehouse()
    {
        return $this->belongsTo(InventoryWarehouse::class, 'inventory_warehouse_id', 'id');
    }

    public function outcome()
    {
        return $this->belongsTo(InventoryWarehouseOutcome::class, 'inventory_warehouse_outcome_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requestedProducts()
    {
        return collect($this->requested_products)->map(function ($requestedProduct) {
            return InventoryProductItem::find($requestedProduct['product_id']);
        });
    }


    public function job()
    {
        return $this->belongsTo(Job::class, 'job_code', 'code');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_code', 'code');
    }

    public function loans()
    {
        return $this->hasMany(InventoryWarehouseProductItemLoan::class, 'inventory_warehouse_outcome_request_id', 'id');
    }
}
