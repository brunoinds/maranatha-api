<?php

namespace App\Http\Controllers;

use App\Support\Generators\Records\Attendances\RecordAttendancesByWorker;
use App\Support\Generators\Records\Jobs\RecordJobsByCosts;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobs;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobsExpenses;
use App\Support\Generators\Records\Attendances\RecordAttendancesByWorkersJobsExpenses;
use App\Support\Generators\Records\Users\RecordUsersByCosts;
use App\Support\Generators\Records\Reports\RecordReportsByTime;
use App\Support\Generators\Records\Invoices\RecordInvoicesByItems;
use App\Support\Generators\Records\Inventory\RecordInventoryProductsKardex;
use App\Support\Generators\Records\Inventory\RecordInventoryProductsBalance;
use App\Support\Generators\Records\Inventory\RecordInventoryProductsStock;
use App\Support\Generators\Records\Inventory\RecordInventoryProductsLoansKardex;
use App\Support\Generators\Records\Inventory\RecordInventoryProducts;
use App\Support\Generators\Records\General\RecordGeneralRecords;


use DateTime;
use Carbon\Carbon;
use App\Support\Cache\RecordsCache;


class ManagementRecordsController extends Controller
{
    public function attendancesByWorker()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'supervisor' => 'nullable|string',
            'team' => 'nullable|string',
            'function' => 'nullable|string',
            'worker_dni' => 'nullable|string',
        ]);

        $defaults = [
            'supervisor' => null,
            'team' => null,
            'function' => null,
            'worker_dni' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('attendancesByWorker', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('attendancesByWorker', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordAttendancesByWorker([
            'startDate' => Carbon::parse(new DateTime($validatedData['start_date']))->startOfDay()->toDateTime(),
            'endDate' => Carbon::parse(new DateTime($validatedData['end_date']))->endOfDay()->toDateTime(),
            'supervisor' => $validatedData['supervisor'],
            'team' => $validatedData['team'],
            'function' => $validatedData['function'],
            'workerDni' => $validatedData['worker_dni'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('attendancesByWorker', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function jobsByCosts()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'job_region' => 'nullable|string',
            'expense_code' => 'nullable|string',
        ]);

        $defaults = [
            'job_region' => null,
            'expense_code' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('jobsByCosts', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('jobsByCosts', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordJobsByCosts([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobRegion' => $validatedData['job_region'],
            'expenseCode' => $validatedData['expense_code'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('jobsByCosts', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function attendancesByJobs()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'job_code' => 'nullable|string',
            'expense_code' => 'nullable|string',
            'supervisor' => 'nullable|string',
            'worker_dni' => 'nullable|string',
            'job_zone' => 'nullable|string',
        ]);

        $defaults = [
            'job_code' => null,
            'job_zone' => null,
            'expense_code' => null,
            'supervisor' => null,
            'worker_dni' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('attendancesByJobs', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('attendancesByJobs', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordAttendancesByJobs([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'supervisor' => $validatedData['supervisor'],
            'workerDni' => $validatedData['worker_dni'],
            'jobZone' => $validatedData['job_zone'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('attendancesByJobs', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function attendancesByJobsExpenses()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'job_code' => 'nullable|string',
            'expense_code' => 'nullable|string',
            'supervisor' => 'nullable|string',
            'worker_dni' => 'nullable|string',
            'job_zone' => 'nullable|string',
        ]);

        $defaults = [
            'job_code' => null,
            'expense_code' => null,
            'supervisor' => null,
            'worker_dni' => null,
            'job_zone' => null
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('attendancesByJobsExpenses', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('attendancesByJobsExpenses', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordAttendancesByJobsExpenses([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'supervisor' => $validatedData['supervisor'],
            'workerDni' => $validatedData['worker_dni'],
            'jobZone' => $validatedData['job_zone'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('attendancesByJobsExpenses', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function attendancesByWorkersJobsExpenses()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'job_code' => 'nullable|string',
            'expense_code' => 'nullable|string',
            'supervisor' => 'nullable|string',
            'worker_dni' => 'nullable|string',
            'job_zone' => 'nullable|string',
        ]);

        $defaults = [
            'job_code' => null,
            'expense_code' => null,
            'supervisor' => null,
            'worker_dni' => null,
            'job_zone' => null
        ];

        $validatedData = array_merge($defaults, $validatedData);

        /*if (RecordsCache::getRecord('attendancesByWorkersJobsExpenses', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('attendancesByWorkersJobsExpenses', $validatedData),
                'is_cached' => true
            ]);
        }*/

        $record = new RecordAttendancesByWorkersJobsExpenses([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'supervisor' => $validatedData['supervisor'],
            'workerDni' => $validatedData['worker_dni'],
            'jobZone' => $validatedData['job_zone'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('attendancesByWorkersJobsExpenses', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function usersByCosts()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'job_code' => 'nullable|string',
            'expense_code' => 'nullable|string',
            'type' => 'nullable|string|in:Invoices,Bills,Factures,Workers',
            'user_id' => 'nullable|integer',
        ]);

        $defaults = [
            'job_code' => null,
            'expense_code' => null,
            'type' => null,
            'user_id' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('usersByCosts', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('usersByCosts', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordUsersByCosts([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'type' => $validatedData['type'],
            'userId' => $validatedData['user_id'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('usersByCosts', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }


    public function reportsByTime()
    {
        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'country' => 'nullable|string',
            'money_type' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        $defaults = [
            'country' => null,
            'money_type' => null,
            'type' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('reportsByTime', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('reportsByTime', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordReportsByTime([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'country' => $validatedData['country'],
            'moneyType' => $validatedData['money_type'],
            'type' => $validatedData['type'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('reportsByTime', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function invoicesByItems()
    {
        /**
         * @param array $options
         * @param DateTime $options['startDate']
         * @param DateTime $options['endDate']
         * @param string $options['country']
         * @param string $options['moneyType']
         * @param string $options['invoiceType']
         * @param string|null $options['jobRegion']
         * @param string|null $options['expenseCode']
         * @param string|null $options['jobCode']
         */


        $validatedData = request()->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'country' => 'nullable|string',
            'money_type' => 'nullable|string',
            'invoice_type' => 'nullable|in:Facture,Bill',
            'job_region' => 'nullable|string',
            'expense_code' => 'nullable|string',
            'job_code' => 'nullable|string',
        ]);

        $defaults = [
            'country' => null,
            'money_type' => null,
            'invoice_type' => null,
            'job_region' => null,
            'expense_code' => null,
            'job_code' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('invoicesByItems', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('invoicesByItems', $validatedData),
                'is_cached' => true
            ]);
        }


        $record = new RecordInvoicesByItems([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'country' => $validatedData['country'],
            'moneyType' => $validatedData['money_type'],
            'invoiceType' => $validatedData['invoice_type'],
            'jobRegion' => $validatedData['job_region'],
            'expenseCode' => $validatedData['expense_code'],
            'jobCode' => $validatedData['job_code'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('invoicesByItems', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }


    public function inventoryProducts()
    {
        $validatedData = request()->validate([
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'sub_categories' => 'nullable|array',
            'sub_categories.*' => 'string',
        ]);

        $defaults = [
            'categories' => null,
            'sub_categories' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('inventoryProducts', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('inventoryProducts', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordInventoryProducts([
            'categories' => $validatedData['categories'],
            'subCategories' => $validatedData['sub_categories'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('inventoryProducts', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function inventoryProductsKardex()
    {
        $validatedData = request()->validate([
            'money_type' => 'nullable|string',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'string',
            'expense_code' => 'nullable|string',
            'job_code' => 'nullable|string',
            'product_id' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'sub_categories' => 'nullable|array',
            'sub_categories.*' => 'string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $defaults = [
            'money_type' => null,
            'warehouse_ids' => null,
            'expense_code' => null,
            'job_code' => null,
            'product_id' => null,
            'start_date' => null,
            'end_date' => null,
            'categories' => null,
            'sub_categories' => null,
            'start_date' => null,
            'end_date' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('inventoryProductsKardex', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('inventoryProductsKardex', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordInventoryProductsKardex([
            'startDate' => ($validatedData['start_date']) ? new DateTime($validatedData['start_date']) : null,
            'endDate' => ($validatedData['end_date']) ? new DateTime($validatedData['end_date']) : null,
            'moneyType' => $validatedData['money_type'],
            'warehouseIds' => $validatedData['warehouse_ids'],
            'expenseCode' => $validatedData['expense_code'],
            'jobCode' => $validatedData['job_code'],
            'productId' => $validatedData['product_id'],
            'categories' => $validatedData['categories'],
            'subCategories' => $validatedData['sub_categories'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('inventoryProductsKardex', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function inventoryProductsBalance()
    {
        $validatedData = request()->validate([
            'money_type' => 'nullable|string',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'string',
            'product_id' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'sub_categories' => 'nullable|array',
            'sub_categories.*' => 'string',
        ]);

        $defaults = [
            'money_type' => null,
            'warehouse_ids' => null,
            'product_id' => null,
            'categories' => null,
            'sub_categories' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('inventoryProductsBalance', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('inventoryProductsBalance', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordInventoryProductsBalance([
            'moneyType' => $validatedData['money_type'],
            'warehouseIds' => $validatedData['warehouse_ids'],
            'productId' => $validatedData['product_id'],
            'categories' => $validatedData['categories'],
            'subCategories' => $validatedData['sub_categories'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('inventoryProductsBalance', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function inventoryProductsStock()
    {
        $validatedData = request()->validate([
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'string',
            'product_id' => 'nullable|string',
            'brand' => 'nullable|string',
            'status' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'sub_categories' => 'nullable|array',
            'sub_categories.*' => 'string',
        ]);

        $defaults = [
            'warehouse_ids' => null,
            'product_id' => null,
            'brand' => null,
            'status' => null,
            'categories' => null,
            'sub_categories' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('inventoryProductsStock', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('inventoryProductsStock', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordInventoryProductsStock([
            'warehouseIds' => $validatedData['warehouse_ids'],
            'productId' => $validatedData['product_id'],
            'brand' => $validatedData['brand'],
            'status' => $validatedData['status'],
            'categories' => $validatedData['categories'],
            'subCategories' => $validatedData['sub_categories'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('inventoryProductsStock', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }

    public function inventoryProductsLoansKardex()
    {
        $validatedData = request()->validate([
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'string',
            'product_id' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'sub_categories' => 'nullable|array',
            'sub_categories.*' => 'string',
        ]);

        $defaults = [
            'warehouse_ids' => null,
            'product_id' => null,
            'start_date' => null,
            'end_date' => null,
            'categories' => null,
            'sub_categories' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('inventoryProductsLoansKardex', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('inventoryProductsLoansKardex', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordInventoryProductsLoansKardex([
            'warehouseIds' => $validatedData['warehouse_ids'],
            'productId' => $validatedData['product_id'],
            'startDate' => ($validatedData['start_date']) ? new DateTime($validatedData['start_date']) : null,
            'endDate' => ($validatedData['end_date']) ? new DateTime($validatedData['end_date']) : null,
            'categories' => $validatedData['categories'],
            'subCategories' => $validatedData['sub_categories'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('inventoryProductsLoansKardex', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }


    public function generalRecords()
    {
        $validatedData = request()->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'country' => 'nullable|string',
            'money_type' => 'nullable|string',
            'expense_code' => 'nullable|string',
            'job_code' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        $defaults = [
            'start_date' => null,
            'end_date' => null,
            'country' => null,
            'money_type' => null,
            'expense_code' => null,
            'job_code' => null,
            'type' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        if (RecordsCache::getRecord('generalRecords', $validatedData)){
            return response()->json([
                ...RecordsCache::getRecord('generalRecords', $validatedData),
                'is_cached' => true
            ]);
        }

        $record = new RecordGeneralRecords([
            'startDate' => ($validatedData['start_date']) ? new DateTime($validatedData['start_date']) : null,
            'endDate' => ($validatedData['end_date']) ? new DateTime($validatedData['end_date']) : null,
            'country' => $validatedData['country'],
            'moneyType' => $validatedData['money_type'],
            'expenseCode' => $validatedData['expense_code'],
            'jobCode' => $validatedData['job_code'],
            'type' => $validatedData['type'],
        ]);

        $document = $record->generate();

        RecordsCache::storeRecord('generalRecords', $validatedData, $document);

        return response()->json([
            ...$document,
            'is_cached' => false
        ]);
    }
}
