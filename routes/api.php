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
use App\Http\Controllers\WorkerPaymentController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\InventoryProductController;
use App\Http\Controllers\InventoryWarehouseController;
use App\Http\Controllers\InventoryProductItemController;
use App\Http\Controllers\InventoryProductsPackController;
use App\Http\Controllers\InventoryWarehouseIncomeController;
use App\Http\Controllers\InventoryWarehouseOutcomeController;
use App\Http\Controllers\InventoryWarehouseOutcomeRequestController;
use App\Http\Controllers\InventoryWarehouseProductItemLoanController;
use App\Http\Controllers\ApplicationAdapterController;
use App\Http\Controllers\ProjectJobController;
use App\Http\Controllers\ProjectStructureController;
use App\Http\Controllers\ProjectConstructionPhaseController;
use App\Http\Controllers\ProjectConstructionTaskController;



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
    Route::get("backup", [ApplicationAdapterController::class, 'backup']);
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
        Route::get('reports/{report}/receipt', BalanceController::class . '@getBalanceReceiptFromReport');
    });
    Route::group([], function(){
        Route::get('balances/{balance}/receipt', BalanceController::class . '@getReceipt');
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
        Route::get('/invoices/{invoice}/image', [
            InvoiceController::class, 'showImage'
        ]);
        Route::get('/invoices/{invoice}/pdf', [
            InvoiceController::class, 'showPdf'
        ]);
    });


    //Report group:
    Route::group([], function(){
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


    //Workers group:
    Route::group([], function(){
        Route::apiResource('workers', WorkerController::class);
        Route::apiResource('worker-payments', WorkerPaymentController::class);
        Route::get('workers/{worker}/payments', WorkerPaymentController::class . '@index');
        Route::post('workers/{worker}/payments', WorkerPaymentController::class . '@store');

        Route::post('workers-payments', WorkerPaymentController::class . '@storeMultiple');

    });


    //Management group:
    Route::group(['prefix' => 'management'], function () {
        Route::group(['prefix' => 'records'], function () {
            Route::get('attendances/by-worker', ManagementRecordsController::class . '@attendancesByWorker');
            Route::get('attendances/by-jobs', ManagementRecordsController::class . '@attendancesByJobs');
            Route::get('attendances/by-jobs-expenses', ManagementRecordsController::class . '@attendancesByJobsExpenses');
            Route::get('attendances/by-workers-jobs-expenses', ManagementRecordsController::class . '@attendancesByWorkersJobsExpenses');

            Route::get('jobs/by-costs', ManagementRecordsController::class . '@jobsByCosts');
            Route::get('users/by-costs', ManagementRecordsController::class . '@usersByCosts');
            Route::get('reports/by-time', ManagementRecordsController::class . '@reportsByTime');
            Route::get('invoices/by-items', ManagementRecordsController::class . '@invoicesByItems');
            Route::get('inventory/by-products-kardex', ManagementRecordsController::class . '@inventoryProductsKardex');
            Route::get('inventory/by-products-balance', ManagementRecordsController::class . '@inventoryProductsBalance');
            Route::get('inventory/by-products-stock', ManagementRecordsController::class . '@inventoryProductsStock');
            Route::get('inventory/by-products-loans-kardex', ManagementRecordsController::class . '@inventoryProductsLoansKardex');

        });
        Route::group(['prefix' => 'balances'], function () {
            Route::get('users', ManagementBalancesController::class . '@usersBalances');
            Route::get('users/{user}/years/{year}', BalanceController::class . '@userBalanceYear');
        });
    });


    //Inventory group:
    Route::group([], function(){
        Route::get('inventory/product-image-search', InventoryProductController::class . '@queryImageSearch');

        Route::get('inventory/me/outcome-requests', InventoryWarehouseOutcomeRequestController::class . '@listMeOutcomeRequests');
        Route::get('inventory/me/loans', InventoryWarehouseProductItemLoanController::class . '@listMeLoans');

        Route::apiResource('inventory/products', InventoryProductController::class);

        Route::apiResource('inventory/warehouses', InventoryWarehouseController::class);
        Route::apiResource('inventory/products/items', InventoryProductItemController::class);
        Route::apiResource('inventory/products-packs', InventoryProductsPackController::class);
        Route::apiResource('inventory/warehouse-incomes', InventoryWarehouseIncomeController::class);
        Route::apiResource('inventory/warehouse-outcomes', InventoryWarehouseOutcomeController::class);
        Route::apiResource('inventory/warehouse-outcome-requests', InventoryWarehouseOutcomeRequestController::class);
        Route::apiResource('inventory/warehouse-loans', InventoryWarehouseProductItemLoanController::class);
        Route::post('inventory/warehouse-loans', InventoryWarehouseProductItemLoanController::class . '@storeBulk');


        Route::get('inventory/warehouse-outcomes/{warehouseOutcome}/download-pdf', [
            InventoryWarehouseOutcomeController::class, 'downloadPDF'
        ]);
        Route::get('inventory/warehouse-outcome-requests/{warehouseOutcomeRequest}/download-pdf', [
            InventoryWarehouseOutcomeRequestController::class, 'downloadPDF'
        ]);

        Route::get('inventory/products/items/{inventoryProductItem}/loans', InventoryProductItemController::class . '@loans');


        Route::get('inventory/warehouses/{warehouse}/incomes', InventoryWarehouseController::class . '@listIncomes');
        Route::get('inventory/warehouses/{warehouse}/outcomes', InventoryWarehouseController::class . '@listOutcomes');
        Route::get('inventory/warehouses/{warehouse}/outcome-requests', InventoryWarehouseController::class . '@listOutcomeRequests');
        Route::get('inventory/warehouses/{warehouse}/loans', InventoryWarehouseController::class . '@listLoans');

        Route::get('inventory/warehouses/{warehouse}/products', InventoryWarehouseController::class . '@listProducts');
        Route::get('inventory/warehouses/{warehouse}/stock', InventoryWarehouseController::class . '@listStock');

        Route::get('inventory/warehouse-outcome-requests/{warehouseOutcomeRequest}/chat', InventoryWarehouseOutcomeRequestController::class . '@listChatMessages');
        Route::post('inventory/warehouse-outcome-requests/{warehouseOutcomeRequest}/chat', InventoryWarehouseOutcomeRequestController::class . '@storeChatMessage');
        Route::post('inventory/warehouse-outcome-requests/{warehouseOutcomeRequest}/import-products-as-income', InventoryWarehouseOutcomeRequestController::class . '@importProductsAsIncome');


        Route::get('inventory/chat-attachments/{chatAttachmentId}', InventoryWarehouseOutcomeRequestController::class . '@showChatAttachment');

        Route::get('inventory/warehouse-outcomes/{inventoryWarehouseOutcome}/products', InventoryWarehouseOutcomeController::class . '@listProductsItems');
        Route::get('inventory/warehouse-outcome-requests/{warehouseOutcomeRequest}/loans', InventoryWarehouseOutcomeRequestController::class . '@listLoans');

        Route::get('inventory/warehouse-incomes/{inventoryWarehouseIncome}/products', InventoryWarehouseIncomeController::class . '@listProductsItems');
        Route::get('inventory/warehouse-incomes/{inventoryWarehouseIncome}/image', InventoryWarehouseIncomeController::class . '@showImage');

    });

    //Projects group:
    Route::group(['prefix' => 'projects'], function(){
        Route::apiResource('jobs', ProjectJobController::class);
        Route::apiResource('structures', ProjectStructureController::class);
        Route::apiResource('construction-phases', ProjectConstructionPhaseController::class);
        Route::apiResource('construction-tasks', ProjectConstructionTaskController::class);


        Route::post('construction-tasks/{constructionTask}/daily-reports', ProjectConstructionTaskController::class . '@storeDailyReport');


        Route::get('jobs/{job}/chat', ProjectJobController::class . '@listChatMessages');
        Route::post('jobs/{job}/chat', ProjectJobController::class . '@storeChatMessage');
        Route::get('jobs/chat-attachments/{chatAttachmentId}', ProjectJobController::class . '@showChatAttachment');

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
