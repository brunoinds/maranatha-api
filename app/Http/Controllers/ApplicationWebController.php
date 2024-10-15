<?php

namespace App\Http\Controllers;


use App\Utils\Assistants\ApplicationWebAssistant;


class ApplicationWebController extends Controller
{
    public function receiveBundle()
    {
        //The request receives 2 things: secret_key and the file:
        $secretKey = request()->input('secret_key');

        //Check if the secret key is valid:
        if ($secretKey !== env('APP_PRIVATE_KEY')){
            return response()->json([
                'message' => 'Invalid secret key',
            ], 403);
        }

        $file = request()->file('file');

        if (!$file->isValid()){
            return response()->json([
                'message' => $file->getErrorMessage(),
            ], 400);
        }

        //Check name:
        $bundleFileName = $file->getClientOriginalName();

        if (str_contains($bundleFileName, '(m') === false){
            return response()->json([
                'message' => 'Invalid file name',
            ], 400);
        }

        if (str_contains($bundleFileName, '_') === false){
            return response()->json([
                'message' => 'Invalid file name',
            ], 400);
        }

        if (str_contains($bundleFileName, ').zip') === false){
            return response()->json([
                'message' => 'Invalid file name',
            ], 400);
        }

        try{
            $bundleFile = ApplicationWebAssistant::createBundleFile($file->getPathname(), $file->getClientOriginalName());
            ApplicationWebAssistant::extractAndApplyBundle($bundleFile);

            return response()->json([
                'message' => 'Bundle received and applied successfully',
                'bundle' => [
                    'version' => $bundleFile->version,
                    'minimalVersion' => $bundleFile->minimalVersion,
                    'size' => $bundleFile->size,
                    'url' => $bundleFile->url,
                ]
            ]);
        }catch (\Exception $e){
            return response()->json([
                'message' => $e->getMessage(),
                'error' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
