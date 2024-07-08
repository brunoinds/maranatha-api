<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseOutcomeRequestRequest;
use App\Http\Requests\UpdateInventoryWarehouseOutcomeRequestRequest;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestStatus;
use Illuminate\Support\Str;
use App\Helpers\Toolbox;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use OneSignal;



class InventoryWarehouseOutcomeRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    public function listMeOutcomeRequests()
    {
        $meUser = auth()->id();
        $outcomeRequests = InventoryWarehouseOutcomeRequest::where('user_id', $meUser)->get();
        return response()->json($outcomeRequests->toArray());
    }


    public function listChatMessages(InventoryWarehouseOutcomeRequest $warehouseOutcomeRequest)
    {
        $meUser = auth()->id();
        $warehouseOutcomeRequest->messages = array_map(function($observation) use ($meUser){
            if ($observation['user_id'] !== $meUser) {
                $observation['read_at'] = now();
                if ($observation['received_at'] === null) {
                    $observation['received_at'] = now();
                }
            }
            return $observation;
        }, $warehouseOutcomeRequest->messages);
        $warehouseOutcomeRequest->save();

        return response()->json($warehouseOutcomeRequest->messages);
    }



    public function storeChatMessage(InventoryWarehouseOutcomeRequest $warehouseOutcomeRequest)
    {
        $validated = request()->validate([
            'text' => 'nullable|string',
            'image' => 'nullable|string',
            'written_at' => 'required|date',
            'sent_at' => 'nullable|date',
            'received_at' => 'nullable|date',
            'read_at' => 'nullable|date'
        ]);

        $validated['id'] = Str::uuid();
        $validated['sent_at'] = now();
        $validated['user_id'] = auth()->id();

        if ($validated['image'] !== null){
            $imageValidation = Toolbox::validateImageBase64($validated['image']);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }

                $imageResource = Image::make($validated['image']);
                $imageEncoded = $imageResource->encode('png')->getEncoded();

                $imageId = hash('sha256', $validated['id']);
                $path = 'warehouse-chat/' . $imageId;

                Storage::disk('public')->put($path, $imageEncoded);

                $validated['image'] = $imageId;
            }
        }


        $warehouseOutcomeRequest->messages = array_merge($warehouseOutcomeRequest->messages, [$validated]);

        //Now, we need to mark all the other from user_id that is not me messages as read:
        $warehouseOutcomeRequest->messages = array_map(function($observation) use ($validated) {
            if ($observation['user_id'] !== $validated['user_id']) {
                $observation['read_at'] = now();
                if ($observation['received_at'] === null) {
                    $observation['received_at'] = now();
                }
            }
            return $observation;
        }, $warehouseOutcomeRequest->messages);

        $warehouseOutcomeRequest->save();


        $notificationUrlOnUserReports = env('APP_WEB_URL') . '/inventory/outcome-requests/' . $warehouseOutcomeRequest->id. '/chat';
        $notifications = [];

        $user = auth()->user();
        $notifications[] = [
            'headings' => '💬 Chat Pedido #00' . $warehouseOutcomeRequest->id,
            'message' => (function() use ($user, $validated){
                if ($validated['image'] !== null && $validated['text'] !== null){
                    return $user->name . ' envió una imagen 🌅: "' . $validated['text'] . '"';
                }elseif ($validated['image'] !== null){
                    return $user->name . ' envió una imagen 🌅.';
                }elseif ($validated['text'] !== null){
                    return $user->name . ' envió: "' . $validated['text'] . '"';
                }
            })(),
            'users_ids' => (function() use ($user, $warehouseOutcomeRequest){
                if ($warehouseOutcomeRequest->user_id === $user->id){
                    return $warehouseOutcomeRequest->warehouse->owners;
                }else{
                    return [$warehouseOutcomeRequest->user_id];
                }
            })(),
            'data' => [
                'deepLink' => $notificationUrlOnUserReports
            ]
        ];

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

        $warehouseOutcomeRequest = InventoryWarehouseOutcomeRequest::find($warehouseOutcomeRequest->id);

        //Now we need to mark the message as received:
        $warehouseOutcomeRequest->messages = array_map(function($observation) use ($validated) {
            if ($observation['id'] == $validated['id']) {
                $observation['received_at'] = now();
            }
            return $observation;
        }, $warehouseOutcomeRequest->messages);

        $warehouseOutcomeRequest->save();

        return response()->json($warehouseOutcomeRequest->messages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryWarehouseOutcomeRequestRequest $request)
    {
        $validated = $request->validated();

        $data = array_merge($validated, [
            'user_id' => auth()->id(),
            'status' =>  InventoryWarehouseOutcomeRequestStatus::Draft,
            'messages' => []
        ]);
        $inventoryWarehouseOutcomeRequest = InventoryWarehouseOutcomeRequest::create($data);

        $inventoryWarehouseOutcomeRequest->requested_products = [];
        foreach ($validated['requested_products'] as $requestedProduct) {
            $inventoryWarehouseOutcomeRequest->addRequestedProduct($requestedProduct['product_id'], $requestedProduct['quantity']);
        }

        $inventoryWarehouseOutcomeRequest->save();

        $inventoryWarehouseOutcomeRequest->changeStatus(InventoryWarehouseOutcomeRequestStatus::Requested);

        return response()->json(['message' => 'Warehouse outcome request created', 'warehouse_outcome_request' => $inventoryWarehouseOutcomeRequest->toArray()]);
    }


    public function showChatImage(string $chatImageId)
    {
        $imageId = $chatImageId;
        if (!$imageId){
            return response()->json([
                'error' => [
                    'message' => 'Image not uploaded yet',
                ]
            ], 400);
        }

        $path = 'warehouse-chat/' . $imageId;
        $imageExists = Storage::disk('public')->exists($path);
        if (!$imageExists){
            return response()->json([
                'error' => [
                    'message' => 'Image missing',
                ]
            ], 400);
        }

        $image = Storage::disk('public')->get($path);

        //Send back as base64 encoded image:
        return response()->json(['image' => base64_encode($image)]);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouseOutcomeRequest $warehouseOutcomeRequest)
    {
        return response()->json($warehouseOutcomeRequest->toArray());
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryWarehouseOutcomeRequestRequest $request, InventoryWarehouseOutcomeRequest $warehouseOutcomeRequest)
    {
        $validated = $request->validated();

        $status = $validated['status'];

        unset($validated['status']);

        $warehouseOutcomeRequest->update($validated);

        $warehouseOutcomeRequest->changeStatus(InventoryWarehouseOutcomeRequestStatus::from($status));

        return response()->json(['message' => 'Warehouse outcome request updated', 'warehouse_outcome_request' => $warehouseOutcomeRequest->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouseOutcomeRequest $warehouseOutcomeRequest)
    {
        //Check if the request is in a deletable status:
        if ($warehouseOutcomeRequest->status !== InventoryWarehouseOutcomeRequestStatus::Draft){
            return response()->json([
                'error' => [
                    'message' => 'The request is not in a deletable status',
                ]
            ], 400);
        }

        $warehouseOutcomeRequest->delete();
        return response()->json(['message' => 'Warehouse outcome request deleted']);
    }
}
