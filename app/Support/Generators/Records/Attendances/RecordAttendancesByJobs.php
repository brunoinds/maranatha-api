<?php

namespace App\Support\Generators\Records\Attendances;

use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Enums\MoneyType;
use App\Support\Exchange\Exchanger;

use App\Helpers\Toolbox;
use App\Models\AttendanceDayWorker;

use App\Support\Assistants\WorkersAssistant;
use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Invoice;
use Brunoinds\SunatDolarLaravel\Exchange;
use App\Models\Attendance;



class RecordAttendancesByJobs
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $jobCode = null;
    private string|null $expenseCode = null;
    private string|null $supervisor = null;
    private string|null $workerDni = null;
    private string|null $jobZone = null;

    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param null|string $options['jobCode']
     * @param null|string $options['expenseCode']
     * @param null|string $options['supervisor']
     * @param null|string $options['workerDni']
     * @param null|string $options['jobZone']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->jobCode = $options['jobCode'];
        $this->expenseCode = $options['expenseCode'];
        $this->supervisor = $options['supervisor'];
        $this->workerDni = $options['workerDni'];
        $this->jobZone = $options['jobZone'];
    }

    private function getWorkersData():array
    {
        $workersSpendings = collect(WorkersAssistant::getWorkersSpendings())->map(function($workerSpendings){
            return $workerSpendings['spendings'];
        })->flatten(1);

        $spendingsInSpan = $workersSpendings->where('date', '>=', $this->startDate->format('c'))->where('date', '<=', $this->endDate->format('c'));

        if ($this->workerDni !== null){
            $spendingsInSpan = $spendingsInSpan->where('worker.dni', '=', $this->workerDni);
        }

        if ($this->jobCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.code', '=', $this->jobCode);
        }

        if ($this->expenseCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('expense.code', '=', $this->expenseCode);
        }

        if ($this->supervisor !== null){
            $spendingsInSpan = $spendingsInSpan->where('worker.supervisor', '=', $this->supervisor);
        }

        if ($this->jobZone !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.zone', '=', $this->jobZone);
        }



        $spendingsInSpan = collect($spendingsInSpan)->groupBy(function($spending){
            return $spending['attendance']['id'] . '/~/' . $spending['attendance']['created_at'] . '/~/' . $spending['job']['code'] . '/~/' . $spending['expense']['code'] . '/~/' . $spending['attendance_day']['worker_dni'] . '/~/' . $spending['worker']['supervisor'] . '/~/' . $spending['worker']['name'] . '/~/' . $spending['job']['zone'];
        });

        // Get all attendance IDs
        $attendanceIds = collect($spendingsInSpan)->map(function($spendings, $code) {
            return explode('/~/', $code)[0];
        })->unique()->toArray();

        // Load all Attendance records at once
        $allAttendances = Attendance::query()->whereIn('id', $attendanceIds)->get();

        $spendingsInSpan = array_column($spendingsInSpan->map(function($spendings, $code) use ($allAttendances){
            $attendanceId = explode('/~/', $code)[0];
            $attendance = $allAttendances->where('id', $attendanceId)->first();
            $attendanceFromDate = Carbon::parse($attendance->from_date)->format('d/m/Y');
            $attendanceToDate = Carbon::parse($attendance->to_date)->format('d/m/Y');

            return [
                'attendance_id' => $attendanceId,
                'attendance_created_at' => explode('/~/', $code)[1],
                'attendance_from_date' => $attendanceFromDate,
                'attendance_to_date' => $attendanceToDate,
                'job_code' => explode('/~/', $code)[2],
                'expense_code' => explode('/~/', $code)[3],
                'worker_dni' => explode('/~/', $code)[4],
                'supervisor' => explode('/~/', $code)[5],
                'worker_name' => explode('/~/', $code)[6],
                'job_zone' => explode('/~/', $code)[7],
                'spendings' => collect($spendings)->map(function($spending){
                    $spending = Toolbox::toObject($spending);
                    $spending->amountInSoles = (function() use ($spending){
                        return $spending->amount;
                    })();
                    $spending->amountInDollars = (function() use ($spending){
                        $date = new DateTime($spending->date);
                        return Exchanger::on($date)->convert($spending->amount,MoneyType::PEN, MoneyType::USD);
                    })();

                    return $spending;
                }),
            ];
        })->toArray(), null);


        $withTotals = collect($spendingsInSpan)->map(function($item){
            $daysWorked = 0;
            $daysNotWorked = 0;
            $amountInSoles = 0;
            $amountInDollars = 0;
            $dayWorkAmountInSoles = 0;
            $dayWorkAmountInDollars = 0;


            foreach ($item['spendings'] as $spending){
                $daysWorked += $spending->attendance_day->status === AttendanceStatus::Present->value ? 1 : 0;
                $daysNotWorked += $spending->attendance_day->status === AttendanceStatus::Absent->value ? 1 : 0;
                $amountInSoles += $spending->amountInSoles;
                $amountInDollars += $spending->amountInDollars;
            }


            $dayWorkAmountInSoles = $daysWorked === 0 ? 0 : $amountInSoles / $daysWorked;
            $dayWorkAmountInDollars = $daysWorked === 0 ? 0 : $amountInDollars / $daysWorked;

            unset($item['spendings']);

            $item['days_worked'] = $daysWorked;
            $item['days_not_worked'] = $daysNotWorked;
            $item['amount_in_soles'] = $amountInSoles;
            $item['amount_in_dollars'] = $amountInDollars;
            $item['day_work_amount_in_soles'] = $dayWorkAmountInSoles;
            $item['day_work_amount_in_dollars'] = $dayWorkAmountInDollars;

            return $item;
        });


        return $withTotals->toArray();
    }

    private function createTable():array{
        $spendings = $this->getWorkersData();

        $spendings = array_column($spendings, null);


        return [
            'headers' => [
                [
                    'title' => 'Fecha Creación Reporte',
                    'key' => 'attendance_created_at',
                ],
                [
                    'title' => 'Fecha Inicio Reporte',
                    'key' => 'attendance_from_date',
                ],
                [
                    'title' => 'Fecha Fin Reporte',
                    'key' => 'attendance_to_date',
                ],
                [
                    'title' => 'Supervisor',
                    'key' => 'supervisor',
                ],
                [
                    'title' => 'Trabajador',
                    'key' => 'worker_name',
                ],
                [
                    'title' => 'DNI Trabajador',
                    'key' => 'worker_dni',
                ],
                [
                    'title' => 'Dias Asistidos',
                    'key' => 'days_worked',
                ],
                [
                    'title' => 'Dias Inasistidos',
                    'key' => 'days_not_worked',
                ],
                [
                    'title' => 'Job',
                    'key' => 'job_code',
                ],
                [
                    'title' => 'Job Zone',
                    'key' => 'job_zone',
                ],
                [
                    'title' => 'Expense',
                    'key' => 'expense_code',
                ],
                [
                    'title' => 'Costo/Día (Dólares)',
                    'key' => 'day_work_amount_in_dollars',
                ],
                [
                    'title' => 'Costo/Día (Soles)',
                    'key' => 'day_work_amount_in_soles',
                ],
                [
                    'title' => 'Costo Total (Dólares)',
                    'key' => 'amount_in_dollars',
                ],
                [
                    'title' => 'Costo Total (Soles)',
                    'key' => 'amount_in_soles',
                ]
            ],
            'body' => $spendings,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'startDate' => $this->startDate->format('c'),
                'endDate' => $this->endDate->format('c'),
                'supervisor' => $this->supervisor,
                'jobCode' => $this->jobCode,
                'expenseCode' => $this->expenseCode,
                'workerDni' => $this->workerDni,
                'jobZone' => $this->jobZone,
            ],
        ];
    }
}
