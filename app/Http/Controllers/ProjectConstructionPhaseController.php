<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectConstructionPhaseRequest;
use App\Http\Requests\UpdateProjectConstructionPhaseRequest;
use App\Models\ProjectConstructionPhase;
use Illuminate\Http\Request;
use App\Models\ProjectConstructionTask;
use App\Helpers\Toolbox;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Intervention\Image\Facades\Image;


class ProjectConstructionPhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(ProjectConstructionPhase::all());
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectConstructionPhaseRequest $request)
    {
        $validated = $request->validated();
        $constructionPhase = ProjectConstructionPhase::create($validated);

        //Construction Task Importing:
        foreach ($validated['tasks'] as $task){
            $task = (object) $task;

            $constructionTask = ProjectConstructionTask::create([
                'project_job_id' => $constructionPhase->projectJob->id,
                'project_construction_phase_id' => $constructionPhase->id,
                'name' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'scheduled_start_date' => $task->scheduled_start_date,
                'scheduled_end_date' => $task->scheduled_end_date,
                'started_at' => $task->started_at,
                'ended_at' => $task->ended_at,
                'count_workers' => $task->count_workers,
                'progress' => $task->progress,
                'daily_reports' => []
            ]);
        }

        return response()->json(['message' => 'Project construction phase created successfully', 'construction_phase' => $constructionPhase]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectConstructionPhase $constructionPhase)
    {
        return response()->json($constructionPhase);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectConstructionPhaseRequest $request, ProjectConstructionPhase $constructionPhase)
    {
        $validated = $request->validated();


        if ($validated['final_report'] !== null && isset($validated['final_report']['attachments_base64'])){
            //Validate all attachments:
            foreach($validated['final_report']['attachments_base64'] as $attachment){
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
            foreach ($validated['final_report']['attachments_base64'] as $attachmentBase64){
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

                $validated['final_report']['attachments_ids'][] = $imageId;
            }
        }

        $constructionPhase->update($validated);

        return response()->json(['message' => 'Project construction phase updated successfully', 'construction_phase' => $constructionPhase]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectConstructionPhase $constructionPhase)
    {
        $constructionPhase->delete();
        return response()->json(['message' => 'Project construction phase deleted successfully']);
    }
}
