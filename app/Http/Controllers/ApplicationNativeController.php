<?php

namespace App\Http\Controllers;


use App\Utils\Assistants\ApplicationNativeAssistant;
use OneSignal;

class ApplicationNativeController extends Controller
{

    public function bundles()
    {
        $bundleFile = ApplicationNativeAssistant::bundleFile();
        if (!$bundleFile) {
            return response()->json([
                'bundles' => []
            ], 200);
        }

        return response()->json([
            'bundles' => [
                [
                    'version' => $bundleFile->version,
                    'minimalVersion' => $bundleFile->minimalVersion,
                    'size' => $bundleFile->size,
                    'url' => $bundleFile->url,
                ]
            ]
        ], 200);
    }

    public function bundle()
    {
        $version = request()->route('version');

        $bundleFile = ApplicationNativeAssistant::bundleFile();

        if (!$bundleFile) {
            return response()->json([
                'message' => 'No bundle found',
            ], 404);
        }

        if ($version !== $bundleFile->version) {
            return response()->json([
                'message' => 'Bundle version not found',
            ], 404);
        }

        return response()->download($bundleFile->path);
    }

    public function receiveBundle()
    {
        $secretKey = request()->input('secret_key');

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

        $bundleFile = ApplicationNativeAssistant::setBundleFile($file->getPathname(), $file->getClientOriginalName());

        return response()->json([
            'message' => 'Bundle received successfully',
            'bundle' => [
                'version' => $bundleFile->version,
                'minimalVersion' => $bundleFile->minimalVersion,
                'size' => $bundleFile->size,
                'url' => $bundleFile->url,
            ]
        ]);
    }
}
