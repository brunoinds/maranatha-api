<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectJobRequest;
use App\Http\Requests\UpdateProjectJobRequest;
use App\Models\ProjectJob;
use App\Helpers\Toolbox;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
class ProjectJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProjectJob::all()->map(function($job){
            unset($job->messages);
            return $job;
        }));
    }


    public function listChatMessages(ProjectJob $job)
    {
        $meUser = auth()->id();
        $job->messages = array_map(function($observation) use ($meUser){
            if ($observation['user_id'] !== $meUser) {
                $observation['read_at'] = now();
                if ($observation['received_at'] === null) {
                    $observation['received_at'] = now();
                }
            }
            return $observation;
        }, $job->messages);
        $job->save();

        return response()->json($job->messages);
    }

    public function storeChatMessage(ProjectJob $job)
    {
        $validated = request()->validate([
            'text' => 'nullable|string',
            'image' => 'nullable|array',
            'image.data' => 'nullable|string',
            'image.size' => 'integer',

            'document' => 'nullable|array',
            'document.data' => 'nullable|string',
            'document.size' => 'integer',
            'document.type' => 'string',
            'document.name' => 'string',

            'video' => 'nullable|array',
            'video.data' => 'nullable|string',
            'video.size' => 'integer',
            'video.type' => 'string',
            'video.duration' => 'integer',

            'audio' => 'nullable|array',
            'audio.data' => 'nullable|string',
            'audio.size' => 'integer',
            'audio.type' => 'string',
            'audio.duration' => 'integer',

            'location' => 'nullable|string',
            'reply_to' => 'nullable|string',
            'react_to' => 'nullable|string',

            'written_at' => 'required|date',
            'sent_at' => 'nullable|date',
            'received_at' => 'nullable|date',
            'read_at' => 'nullable|date',
        ]);

        $validated['id'] = Str::uuid();
        $validated['sent_at'] = now();
        $validated['user_id'] = auth()->id();

        if (isset($validated['image'])){
            $imageValidation = Toolbox::validateImageBase64($validated['image']['data']);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }

                $imageResource = Image::make($validated['image']['data']);
                $imageEncoded = $imageResource->encode('png')->getEncoded();

                $imageId = Str::uuid();
                $path = 'projects-chat/' . $imageId;

                Storage::disk('public')->put($path, $imageEncoded);

                $validated['image'] = [
                    'data' => $imageId,
                    'size' => strlen($imageEncoded),
                ];
            }
        }
        if (isset($validated['document'])){
            $documentDecoded = base64_decode($validated['document']['data']);
            $documentId = Str::uuid();
            $path = 'projects-chat/' . $documentId;
            Storage::disk('public')->put($path, $documentDecoded);
            $validated['document'] = [
                'data' => $documentId,
                'size' => $validated['document']['size'],
                'type' => $validated['document']['type'],
                'name' => $validated['document']['name'],
            ];
        }
        if (isset($validated['video'])){
            $documentDecoded = base64_decode($validated['video']['data']);
            $documentId = Str::uuid();
            $path = 'projects-chat/' . $documentId;
            Storage::disk('public')->put($path, $documentDecoded);
            $validated['video'] = [
                'data' => $documentId,
                'size' => $validated['video']['size'],
                'duration' => $validated['video']['duration'],
                'type' => $validated['video']['type'],
            ];
        }
        if (isset($validated['audio'])){
            $documentDecoded = base64_decode($validated['audio']['data']);
            $documentId = Str::uuid();
            $path = 'projects-chat/' . $documentId;
            Storage::disk('public')->put($path, $documentDecoded);
            $validated['audio'] = [
                'data' => $documentId,
                'size' => $validated['audio']['size'],
                'duration' => $validated['audio']['duration'],
                'type' => $validated['audio']['type'],
            ];
        }


        $job->messages = array_merge($job->messages, [$validated]);

        //Now, we need to mark all the other from user_id that is not me messages as read:
        $job->messages = array_map(function($observation) use ($validated) {
            if ($observation['user_id'] !== $validated['user_id']) {
                $observation['read_at'] = now();
                if ($observation['received_at'] === null) {
                    $observation['received_at'] = now();
                }
            }
            return $observation;
        }, $job->messages);

        $job->save();


        $notificationUrlOnUserReports = env('APP_WEB_URL') . '/projects/jobs/' . $job->id. '/chat';
        $notifications = [];

        $user = auth()->user();
        $notifications[] = [
            'headings' => '💬 Chat Job ' . $job->job_code,
            'message' => (function() use ($user, $validated){
                if (isset($validated['image']) && $validated['text'] !== null){
                    return $user->name . ' envió una imagen 🌅: "' . $validated['text'] . '"';
                }elseif (isset($validated['image'])){
                    return $user->name . ' envió una imagen 🌅.';
                }elseif (isset($validated['document']) && $validated['text'] !== null){
                    return $user->name . ' envió un documento 📄: "' . $validated['text'] . '"';
                }elseif (isset($validated['document'])){
                    return $user->name . ' envió un documento 📄.';
                }elseif (isset($validated['react_to']) && $validated['text'] !== null){
                    return $user->name . ' reaccionó a un mensaje: "' . $validated['text'] . '"';
                }elseif (isset($validated['reply_to']) && $validated['text'] !== null){
                    return $user->name . ' respondió a un mensaje: "' . $validated['text'] . '"';
                }elseif ($validated['text'] !== null){
                    return $user->name . ' envió: "' . $validated['text'] . '"';
                }
            })(),
            'users_ids' => (function() use ($user, $job){
                $sendTo = array_merge($job->admins_ids, [$job->supervisor_id]);
                $sendTo = array_filter($sendTo, function($userId) use ($user){
                    return $userId !== $user->id;
                });
                return $sendTo;
            })(),
            'data' => [
                'deepLink' => $notificationUrlOnUserReports
            ]
        ];

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



        $job = ProjectJob::find($job->id);

        //Now we need to mark the message as received:
        $job->messages = array_map(function($observation) use ($validated) {
            if ($observation['id'] == $validated['id']) {
                $observation['received_at'] = now();
            }
            return $observation;
        }, $job->messages);

        $job->save();

        return response()->json($job->messages);
    }

    public function showChatAttachment(string $chatAttachmentId)
    {
        $attachmentId = $chatAttachmentId;
        if (!$attachmentId){
            return response()->json([
                'error' => [
                    'message' => 'Attachment not uploaded yet',
                ]
            ], 400);
        }

        $path = 'projects-chat/' . $attachmentId;
        $attachmentExists = Storage::disk('public')->exists($path);
        if (!$attachmentExists){
            return response()->json([
                'error' => [
                    'message' => 'Attachment missing',
                ]
            ], 400);
        }

        $attachment = Storage::disk('public')->get($path);
        return response()->json(['attachment' => base64_encode($attachment)]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectJobRequest $request)
    {
        $validated = $request->validated();

        $project = ProjectJob::create($validated);

        $project->importPhasesAndTasksFromStructure();

        return response()->json(['message' => 'Project job created successfully', 'project' => $project]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectJob $job)
    {
        $job->job;
        $job->constructionPhases->each(function($phase){
            $phase->tasks;
        });
        return response()->json($job);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectJobRequest $request, ProjectJob $job)
    {
        $validated = $request->validated();

        $job->update($validated);
        return response()->json(['message' => 'Project job updated successfully', 'project' => $job]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectJob $job)
    {
        $job->delete();
        return response()->json(['message' => 'Project job deleted successfully']);
    }




}
