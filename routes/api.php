<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;

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


Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::get('/users/{user}/roles', UserController::class . '@roles');
    Route::post('/users/{user}/roles', UserController::class . '@addRole');
    Route::delete('/users/{user}/roles/{role}', UserController::class . '@removeRole');
    Route::get('/users/{user}/roles/{role}', UserController::class . '@hasRole');

});

Route::post("login", [AuthController::class, 'login']);
Route::post("register", [AuthController::class, 'register']);
Route::post("logout", [AuthController::class, 'logout']);


Route::apiResource('invoices', InvoiceController::class);
Route::apiResource('reports', ReportController::class);
Route::post('/invoices/{invoice}/image-upload', [
    InvoiceController::class, 'uploadImage' 
]);

Route::get('/reports/{report}/invoices', ReportController::class . '@invoices');
