<?php

namespace App\Http\Controllers;

use App\Helpers\Toolbox;
use App\Http\Requests\ListInstantMessagesInConversationRequest;
use App\Http\Requests\StoreInstantMessageRequest;
use App\Http\Requests\UpdateInstantMessageRequest;
use App\Models\InstantMessage;
use App\Support\Assistants\InstantMessagesConversationAssistant;
use App\Models\User;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;



class InstantMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return InstantMessage::all();
    }


    /**
     * List messages between two users
     */
    public function messagesInConversation(ListInstantMessagesInConversationRequest $request)
    {
        $validated = $request->validated();

        $fromUserId = $validated['from_user_id'];
        $toUserId = $validated['to_user_id'];

        $fromUser = User::findOrFail($fromUserId);
        $toUser = User::findOrFail($toUserId);

        $messages = InstantMessagesConversationAssistant::getConversationMessagesQuery($fromUser, $toUser)->get();

        return response()->json([
            'messages' => $messages
        ]);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstantMessageRequest $request)
    {
        $validated = $request->validated();


        if ($validated['type'] === 'Image'){
            $base64Image = $validated['attachment']['base64'];

            $imageValidation = Toolbox::validateImageBase64($base64Image);
            if (!$imageValidation->isValid){
                return response()->json([
                    'message' => $imageValidation->message
                ], 400);
            }


            $imageResource = Image::make($base64Image);
            $imageEncoded = $imageResource->encode('png')->getEncoded();

            $imageId = Str::random(40);



            $path = 'messages-attachments/' . $imageId;


            $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);

            //Intervention Image, reduce image to lower resolution and get the thiny base64 to serve as a blurred image on the front-end loading effect:
            $shortImageInBase64 = Image::make($base64Image)->resize(15, 15)->encode('data-url')->encoded;

            $validated['attachment'] = [
                'id' => $imageId,
                'url' =>  Storage::disk('public')->url($path),
                'thumbnail' => $shortImageInBase64
            ];




            if (!$wasSuccessfull){
                return response()->json([
                    'message' => 'Image upload failed'
                ], 500);
            }
        }

        $instantMessage = InstantMessage::create($validated);

        return response()->json([
            'message' => 'Instant message created successfully',
            'instant_message' => $instantMessage
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(InstantMessage $instantMessage)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInstantMessageRequest $request, InstantMessage $instantMessage)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InstantMessage $instantMessage)
    {
        //
    }
}
