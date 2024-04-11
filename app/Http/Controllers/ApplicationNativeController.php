<?php

namespace App\Http\Controllers;


use App\Support\Assistants\ApplicationNativeAssistant;
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

        //$previousBundleFile = ApplicationNativeAssistant::bundleFile();

        $bundleFile = ApplicationNativeAssistant::setBundleFile($file->getPathname(), $file->getClientOriginalName());

        /*if ($previousBundleFile && $previousBundleFile->version < $bundleFile->version){
            OneSignal::sendNotificationToExternalUser(
                headings: "Nuevo reporte recibido 📥",
                message: 'Hay una nueva actualización en la aplicación',
                userId: Toolbox::getOneSignalUserId($adminUser->id),
                data: [
                    'deepLink' => $notificationUrlOnUserReports
                ]
            );
        }*/

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
