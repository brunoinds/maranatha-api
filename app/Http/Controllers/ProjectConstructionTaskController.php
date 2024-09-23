<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectConstructionTaskRequest;
use App\Http\Requests\UpdateProjectConstructionTaskRequest;
use App\Models\ProjectConstructionTask;
use App\Helpers\Toolbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Intervention\Image\Facades\Image;
use App\Helpers\Enums\ProjectConstructionTaskStatus;

class ProjectConstructionTaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProjectConstructionTask::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectConstructionTaskRequest $request)
    {
        $validated = $request->validated();

        $constructionTask = ProjectConstructionTask::create($validated);

        $constructionTask->projectConstructionPhase->refreshProgress();
        return response()->json(['message' => 'Project construction task created successfully', 'construction_task' => $constructionTask]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeDailyReport(ProjectConstructionTask $constructionTask)
    {
        $validated = request()->validate([
            'id' => 'required|string',
            'created_at' => 'required|date',
            'progress' => 'required|numeric|min:0|max:100',
            'notes' => 'present|string|nullable',
            'count_workers' => 'required|numeric|min:0',
            'date' => 'required|date',
            'attachments_base64' => 'present|array',
            'attachments_base64.*' => 'required|string',
        ]);



        //Validate all attachments:
        foreach($validated['attachments_base64'] as $attachment){
            $imageValidation = Toolbox::validateImageBase64($attachment);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }
            }
        }

        //Upload all attachments:
        $attachmentsIds = [];
        foreach ($validated['attachments_base64'] as $attachmentBase64){
            try{
                $imageResource = Image::make($attachmentBase64);
                $imageEncoded = $imageResource->encode('png')->getEncoded();
            } catch(\Exception $e){
                return response()->json([
                    'error' => [
                        'message' => 'Invalid image data',
                        'details' => $e->getMessage()
                    ]
                ], 400);
            }

            $imageId = Str::random(40);
            $path = 'projects/' . $imageId;
            Storage::disk('public')->put($path, $imageEncoded);

            $attachmentsIds[] = $imageId;
        }

        $constructionTask->daily_reports = array_merge($constructionTask->daily_reports, [
            [
                'id' => $validated['id'],
                'created_at' => Carbon::now()->toISO8601String(),
                'progress' => $validated['progress'],
                'notes' => $validated['notes'],
                'count_workers' => $validated['count_workers'],
                'date' => $validated['date'],
                'attachments_ids' => $attachmentsIds
            ]
        ]);


        //Update constructionTask by daily_report
        if (count($constructionTask->daily_reports) === 1 && $constructionTask->status == ProjectConstructionTaskStatus::WaitingToStart->value){
            $constructionTask->status = ProjectConstructionTaskStatus::Ongoing;
            if ($constructionTask->started_at === null){
                $constructionTask->started_at = $validated['date'];
            }

            if ($constructionTask->projectConstructionPhase->status == ProjectConstructionTaskStatus::WaitingToStart->value){
                $constructionTask->projectConstructionPhase->status = ProjectConstructionTaskStatus::Ongoing;
                if ($constructionTask->projectConstructionPhase->started_at === null){
                    $constructionTask->projectConstructionPhase->started_at = $validated['date'];
                }
                $constructionTask->projectConstructionPhase->save();
            }
        }

        //Update count_workers and progress:
        if ($constructionTask->count_workers < $validated['count_workers']){
            $constructionTask->count_workers = $validated['count_workers'];
        }
        if ($constructionTask->progress < $validated['progress']){
            $constructionTask->progress = $validated['progress'];
        }
        if ($validated['progress'] === 100){
            $constructionTask->ended_at = $validated['date'];
            $constructionTask->status = ProjectConstructionTaskStatus::Finished;
        }
        $constructionTask->save();
        $constructionTask->projectConstructionPhase->refreshProgress();
        return response()->json(['message' => 'Project construction task daily report created successfully', 'daily_report' => $constructionTask->daily_reports[count($constructionTask->daily_reports) - 1]]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectConstructionTask $constructionTask)
    {
        return response()->json($constructionTask);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectConstructionTaskRequest $request, ProjectConstructionTask $constructionTask)
    {
        $validated = $request->validated();

        $constructionTask->update($validated);

        $constructionTask->projectConstructionPhase->refreshProgress();
        return response()->json(['message' => 'Project construction task updated successfully', 'construction_task' => $constructionTask]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectConstructionTask $constructionTask)
    {
        $phase = $constructionTask->projectConstructionPhase;
        $constructionTask->delete();
        $phase->refreshProgress();
        return response()->json(['message' => 'Project construction task deleted successfully']);
    }
}
