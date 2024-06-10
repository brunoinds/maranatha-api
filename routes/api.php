<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ExpenseController;
use App\Support\Assistants\WorkersAssistant;
use mikehaertl\shellcommand\Command;
use App\Http\Controllers\ManagementRecordsController;
use App\Http\Controllers\ManagementBalancesController;
use App\Http\Controllers\ApplicationNativeController;
use App\Http\Controllers\ApplicationWebController;
use App\Http\Controllers\InstantMessageController;
use App\Http\Controllers\WorkerPaymentController;
use App\Http\Controllers\WorkerController;

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


Route::group(['prefix' => 'cd'], function () {
    Route::get('config-cache', function(){
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
    Route::get('migrate', function(){
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
    Route::get("check", function(){
        return response()->json(["message" => "API is working!"], 200);
    });
});


Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('jobs', JobController::class);
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('reports', ReportController::class);
    Route::apiResource('attendances', AttendanceController::class);
    Route::apiResource('balances', BalanceController::class);



    //Users group:
    Route::group([], function(){
        Route::get('/users/{user}/roles', UserController::class . '@roles');
        Route::post('/users/{user}/roles', UserController::class . '@addRole');
        Route::delete('/users/{user}/roles/{role}', UserController::class . '@removeRole');
        Route::get('/users/{user}/roles/{role}', UserController::class . '@hasRole');
    });



    //Account group:
    Route::group([], function(){
        Route::get('account/me', ProfileController::class . '@showMe');
        Route::post("logout", [AuthController::class, 'logout']);
    });



    //Me group:
    Route::group([], function(){
        Route::get('/me/reports', ReportController::class . '@myReports');
        Route::get('/me/attendances', AttendanceController::class . '@myAttendances');
        Route::delete('/me/account', UserController::class . '@deleteMyAccount');
    });



    //Balance group:
    Route::group(['prefix' => 'balance'], function () {
        Route::get('me/years/{year}', BalanceController::class . '@meBalanceYear');
        Route::post('users/{user}/credits', BalanceController::class . '@userBalanceAddCredit');
        Route::delete('users/{user}/credits/{balance}', BalanceController::class . '@userBalanceRemoveCredit');
        Route::post('users/{user}/debits', BalanceController::class . '@userBalanceAddDebit');
        Route::delete('users/{user}/debits/{balance}', BalanceController::class . '@userBalanceRemoveDebit');
        Route::get('reports/{report}/balances', BalanceController::class . '@getBalancesFromReport');
        Route::get('reports/{report}/receipt-image', BalanceController::class . '@getBalanceReceiptImageFromReport');
        Route::post('reports/{report}/receipt-image', BalanceController::class . '@setBalanceReceiptImageFromReport');
        Route::delete('reports/{report}/receipt-image', BalanceController::class . '@deleteBalanceReceiptImageFromReport');
    });
    Route::group([], function(){
        Route::get('balances/{balance}/receipt-image', BalanceController::class . '@getReceiptImage');
    });



    //Attendance group:
    Route::group([], function(){
        Route::post('attendances-with-workers', AttendanceController::class . '@storeWithWorkers');
        Route::get('attendances/{attendance}/with-workers-attendances', AttendanceController::class . '@showWithWorkersAttendances');
        Route::put('attendances/{attendance}/workers-attendances', AttendanceController::class . '@storeWorkersAttendances');
        Route::put('attendances/{attendance}/transfer-ownership', AttendanceController::class . '@transferOwnership');

        Route::get('/workers-list', function(){
            $workers = WorkersAssistant::getListWorkers();
            return response()->json($workers);
        });
    });



    //Invoice group:
    Route::group([], function(){
        Route::get('invoices/ticket-number/check', InvoiceController::class . '@checkTicketNumber');
        Route::post('/invoices/{invoice}/image-upload', [
            InvoiceController::class, 'uploadImage'
        ]);
        Route::get('/invoices/{invoice}/image', [
            InvoiceController::class, 'showImage'
        ]);
    });



    //Report group:
    Route::group([], function(){
        Route::post('/reports/{report}/pdf-upload', [
            ReportController::class, 'uploadReportPDF'
        ]);
        Route::get('/reports/{report}/invoices', ReportController::class . '@invoices');
        Route::get('/reports/{report}/excel-download', [
            ReportController::class, 'downloadExcel'
        ]);
        Route::get('/reports/{report}/pdf-download', [
            ReportController::class, 'downloadPDF'
        ]);

        Route::group([
            'excluded_middleware' => 'throttle:api'
        ], function(){
            Route::get('/reports/{report}/check-progress-pdf-download', [
                ReportController::class, 'checkProgressDownloadPDF'
            ]);
        });
    });


    //Chat group:
    Route::group([], function(){
        Route::post('/chats/users/{user}/messages', [
            InstantMessageController::class, 'store'
        ]);

        Route::get('/chats/users/{user}/messages', [
            InstantMessageController::class, 'messagesInConversation'
        ]);


        Route::post('/chats/broadcasting/events', [
            InstantMessageController::class, 'storeBroadcastingEvent'
        ]);

        Route::get('/chats/broadcasting/events', [
            /*
            Event list:
            - new-messages: This command will get all the new messages from the server and will mark them as received
            -

            */



            InstantMessageController::class, 'messagesInConversation'
        ]);
    });


    //Workers group:
    Route::group([], function(){
        Route::apiResource('workers', WorkerController::class);
        Route::apiResource('worker-payments', WorkerPaymentController::class);
        Route::get('workers/{worker}/payments', WorkerPaymentController::class . '@index');
        Route::post('workers/{worker}/payments', WorkerPaymentController::class . '@store');
    });


    //Management group:
    Route::group(['prefix' => 'management'], function () {
        Route::group(['prefix' => 'records'], function () {
            Route::get('attendances/by-worker', ManagementRecordsController::class . '@attendancesByWorker');
            Route::get('attendances/by-jobs', ManagementRecordsController::class . '@attendancesByJobs');
            Route::get('attendances/by-jobs-expenses', ManagementRecordsController::class . '@attendancesByJobsExpenses');

            Route::get('jobs/by-costs', ManagementRecordsController::class . '@jobsByCosts');
            Route::get('users/by-costs', ManagementRecordsController::class . '@usersByCosts');
            Route::get('reports/by-time', ManagementRecordsController::class . '@reportsByTime');
            Route::get('invoices/by-items', ManagementRecordsController::class . '@invoicesByItems');
        });
        Route::group(['prefix' => 'balances'], function () {
            Route::get('users', ManagementBalancesController::class . '@usersBalances');
            Route::get('users/{user}/years/{year}', BalanceController::class . '@userBalanceYear');
        });
    });
});



//Public routes:
Route::group([], function(){
    Route::post("login", [AuthController::class, 'login']);
    Route::post("register", [AuthController::class, 'register']);
    Route::post("users", [UserController::class, 'store']);

    //Application native group:
    Route::group(['prefix' => 'app/native'], function () {
        Route::get('bundles', ApplicationNativeController::class . '@bundles');
        Route::get('bundles/{version}', ApplicationNativeController::class . '@bundle');
        Route::post('bundles', ApplicationNativeController::class . '@receiveBundle');
    });

    Route::group(['prefix' => 'app/web'], function () {
        Route::post('bundles', ApplicationWebController::class . '@receiveBundle');
    });
});
