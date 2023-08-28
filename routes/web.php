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

require __DIR__.'/auth.php';
