<?php

namespace App\Http\Controllers;


use App\Support\Assistants\ApplicationNativeAssistant;


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
}
