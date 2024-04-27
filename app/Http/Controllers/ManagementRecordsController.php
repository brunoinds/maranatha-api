<?php

namespace App\Http\Controllers;

use App\Support\Generators\Records\Attendances\RecordAttendancesByWorker;
use App\Support\Generators\Records\Jobs\RecordJobsByCosts;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobs;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobsExpenses;
use App\Support\Generators\Records\Users\RecordUsersByCosts;
use App\Support\Generators\Records\Reports\RecordReportsByTime;
use App\Support\Generators\Records\Invoices\RecordInvoicesByItems;
use DateTime;
use Carbon\Carbon;


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

        $record = new RecordAttendancesByWorker([
            'startDate' => Carbon::parse(new DateTime($validatedData['start_date']))->startOfDay()->toDateTime(),
            'endDate' => Carbon::parse(new DateTime($validatedData['end_date']))->endOfDay()->toDateTime(),
            'supervisor' => $validatedData['supervisor'],
            'team' => $validatedData['team'],
            'function' => $validatedData['function'],
            'workerDni' => $validatedData['worker_dni'],
        ]);

        $document = $record->generate();

        return response()->json($document);
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

        $record = new RecordJobsByCosts([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobRegion' => $validatedData['job_region'],
            'expenseCode' => $validatedData['expense_code'],
        ]);

        $document = $record->generate();

        return response()->json($document);
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
        ]);

        $defaults = [
            'job_code' => null,
            'expense_code' => null,
            'supervisor' => null,
            'worker_dni' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        $record = new RecordAttendancesByJobs([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'supervisor' => $validatedData['supervisor'],
            'workerDni' => $validatedData['worker_dni'],
        ]);

        $document = $record->generate();

        return response()->json($document);
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
        ]);

        $defaults = [
            'job_code' => null,
            'expense_code' => null,
            'supervisor' => null,
            'worker_dni' => null,
        ];

        $validatedData = array_merge($defaults, $validatedData);

        $record = new RecordAttendancesByJobsExpenses([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'supervisor' => $validatedData['supervisor'],
            'workerDni' => $validatedData['worker_dni'],
        ]);

        $document = $record->generate();

        return response()->json($document);
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

        $record = new RecordUsersByCosts([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'jobCode' => $validatedData['job_code'],
            'expenseCode' => $validatedData['expense_code'],
            'type' => $validatedData['type'],
            'userId' => $validatedData['user_id'],
        ]);

        $document = $record->generate();

        return response()->json($document);
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

        $record = new RecordReportsByTime([
            'startDate' => new DateTime($validatedData['start_date']),
            'endDate' => new DateTime($validatedData['end_date']),
            'country' => $validatedData['country'],
            'moneyType' => $validatedData['money_type'],
            'type' => $validatedData['type'],
        ]);

        $document = $record->generate();

        return response()->json($document);
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

        return response()->json($document);
    }
}
