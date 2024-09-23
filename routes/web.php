<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use Illuminate\Http\Testing\MimeType;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/app', function () {
    $filePath = resource_path('ionic/index.html');
    return Response::file($filePath);
})->where('any', '.*');

Route::get('/', function () {
    return redirect('/app');
});


Route::get('/app/{any}', function ($file) {
    $internalPaths = explode('/', $file);

    if (count($internalPaths) > 1 && $internalPaths[0] === 'assets') {
        $filePath = resource_path('ionic/' . $file);
        $mime = MimeType::from($filePath);
        $headers = ['Content-Type' => $mime];

        File::exists($filePath) or abort(404, 'File not found!');

        return Response::file($filePath, $headers);
    }else{
        $filePath = resource_path('ionic/index.html');
        return Response::file($filePath);
    }
})->where('any', '.*');

Route::get('/resources/public/{any}', function ($file) {
    $internalPaths = explode('/', $file);


    //Add headers for cors:
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');


    $filePath = resource_path('public/' . $file);
    $mime = MimeType::from($filePath);
    $headers = ['Content-Type' => $mime];

    File::exists($filePath) or abort(404, 'File not found!');

    return Response::file($filePath, $headers);
})->where('any', '.*');

Route::get('/resources/service-workers/one-signal-sw.js', function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Service-Worker-Allowed: /');
    $filePath = resource_path('public/assets/service-workers/OneSignalSDKWorker.js');
    $mime = MimeType::from($filePath);
    $headers = ['Content-Type' => $mime];

    File::exists($filePath) or abort(404, 'File not found!');

    return Response::file($filePath, $headers);
});

Route::get('/public/storage/{any}', function ($file) {
    $internalPaths = explode('/', $file);


    //Add headers for cors:
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');



    $filePath = storage_path('app/public/' . $file); //resource_path('public/' . $file);


    if (str_contains($filePath, 'images') || str_contains($filePath, 'avatars') || str_contains($filePath, 'projects')) {
        $mime = 'image/png';
    }else{
        $mime = MimeType::from($filePath);
    }
    $headers = ['Content-Type' => $mime];
    File::exists($filePath) or abort(404, 'File not found!');

    return Response::file($filePath, $headers);
})->where('any', '.*');

require __DIR__.'/auth.php';
