<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::apiResource('invoices', InvoiceController::class);
Route::apiResource('reports', ReportController::class);
Route::apiResource('users', UserController::class);
Route::post('/invoices/{invoice}/image-upload', [
    InvoiceController::class, 'uploadImage' 
]);

Route::get('/reports/{report}/invoices', ReportController::class . '@invoices');
