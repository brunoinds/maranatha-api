<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ExpenseController;
use App\Support\Generators\ReportGenerator;
use App\Support\GoogleSheets\Excel;
use mikehaertl\shellcommand\Command;
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
Route::get('cd/config-cache', function(){
    $commandLine = 'cd .. && php artisan config:clear && php artisan cache:clear';

    $result = [
        'message' => null,
        'exitCode' => null,
        'wasSuccessful' => false,
    ];
    $command = new Command($commandLine);
    if ($command->execute()) {
        $result['message'] = $command->getOutput();
        $result['wasSuccessful'] = true;
        $result['exitCode'] = $command->getExitCode();
    } else {
        $result['message'] = $command->getError();
        $result['exitCode'] = $command->getExitCode();
    }

    if (!$result['wasSuccessful']){
        return response()->json([
            'message' => 'Failed to resolve config cache',
            'command' => [
                'instruction' => $commandLine,
                'output' => $result['message'],
                'exitCode' => $result['exitCode'],
            ]
        ], 500);
    }

    return response()->json([
        'message' => 'Cache updated successfully',
        'command' => [
            'instruction' => $commandLine,
            'output' => $result['message'],
            'exitCode' => $result['exitCode'],
        ]
    ], 200);
});
Route::get('cd/migrate', function(){
    $commandLine = 'cd .. && php artisan migrate --force';


    $result = [
        'message' => null,
        'exitCode' => null,
        'wasSuccessful' => false,
    ];
    $command = new Command($commandLine);
    if ($command->execute()) {
        $result['message'] = $command->getOutput();
        $result['wasSuccessful'] = true;
        $result['exitCode'] = $command->getExitCode();
    } else {
        $result['message'] = $command->getError();
        $result['exitCode'] = $command->getExitCode();
    }

    if (!$result['wasSuccessful']){
        return response()->json([
            'message' => 'Failed to migrate',
            'command' => [
                'instruction' => $commandLine,
                'output' => $result['message'],
                'exitCode' => $result['exitCode'],
            ]
        ], 500);
    }

    return response()->json([
        'message' => 'Migrated successfully',
        'command' => [
            'instruction' => $commandLine,
            'output' => $result['message'],
            'exitCode' => $result['exitCode'],
        ]
    ], 200);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::get('/users/{user}/roles', UserController::class . '@roles');
    Route::post('/users/{user}/roles', UserController::class . '@addRole');
    Route::delete('/users/{user}/roles/{role}', UserController::class . '@removeRole');
    Route::get('/users/{user}/roles/{role}', UserController::class . '@hasRole');
    Route::post("logout", [AuthController::class, 'logout']);

    Route::get('account/me', ProfileController::class . '@showMe');




    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('reports', ReportController::class);
    Route::post('/invoices/{invoice}/image-upload', [
        InvoiceController::class, 'uploadImage' 
    ]);

    Route::get('/invoices/{invoice}/image', [
        InvoiceController::class, 'showImage' 
    ]);

    Route::post('/reports/{report}/pdf-upload', [
        ReportController::class, 'uploadReportPDF' 
    ]);
    
    Route::get('/reports/{report}/invoices', ReportController::class . '@invoices');
    Route::get('/me/reports', ReportController::class . '@myReports');


    Route::apiResource('jobs', JobController::class);
    Route::apiResource('expenses', ExpenseController::class);
});

Route::get("check", function(){
    return response()->json(["message" => "API is working!"], 200);
});
Route::post("login", [AuthController::class, 'login']);
Route::post("register", [AuthController::class, 'register']);
Route::post("users", [UserController::class, 'store']);

Route::get('/reports/{report}/excel-download', [
    ReportController::class, 'downloadExcel' 
]);
Route::get('/reports/{report}/pdf-download', [
    ReportController::class, 'downloadPDF' 
]);


Route::get('/excel/general-report', function(){
    $excelOutput = ReportGenerator::generateExcelOutput();
    $response = Excel::updateDBSheet($excelOutput);
    return response()->json([
        'message' => 'Excel generated successfully',
    ]);
});