<?php

namespace App\Support\Generators\Records\General;

use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Toolbox;

use App\Support\Assistants\WorkersAssistant;
use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use App\Models\Invoice;

use App\Models\Attendance;
use App\Helpers\Enums\MoneyType;
use App\Support\Exchange\Exchanger;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryProductItem;
use App\Models\AttendanceDayWorker;
use App\Helpers\Enums\InventoryProductItemStatus;



class RecordGeneralRecords
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $country = null;
    private string|null $moneyType = null;
    private string|null $type = null;
    private string|null $expenseCode = null;
    private string|null $jobCode = null;

    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param string $options['country']
     * @param string $options['moneyType']
     * @param string $options['type']
     * @param string|null $options['expenseCode']
     * @param string|null $options['jobCode']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->country = $options['country'];
        $this->moneyType = $options['moneyType'];
        $this->type = $options['type'];
        $this->expenseCode = $options['expenseCode'];
        $this->jobCode = $options['jobCode'];
    }

    private function getInvoicesData():Collection
    {
        //Get filtered reports data:
        $invoicesInSpan = Invoice::query()
                    ->with(['report', 'report.user'])
                    ->join('reports', 'invoices.report_id', '=', 'reports.id')
                    ->join('jobs', 'invoices.job_code', '=', 'jobs.code')
                    ->where('invoices.date', '>=', $this->startDate)
                    ->where('invoices.date', '<=', $this->endDate)
                    ->where(function($query){
                        $query->where('reports.status', '=', 'Approved')
                                ->orWhere('reports.status', '=', 'Restituted');
                    });


        if ($this->expenseCode !== null){
            $invoicesInSpan = $invoicesInSpan->where('expense_code', '=', $this->expenseCode);
        }

        if ($this->jobCode !== null){
            $invoicesInSpan = $invoicesInSpan->where('job_code', '=', $this->jobCode);
        }


        if ($this->country !== null){
            $invoicesInSpan = $invoicesInSpan->where('country', '=', $this->country);
        }

        if ($this->moneyType !== null){
            $invoicesInSpan = $invoicesInSpan->where('money_type', '=', $this->moneyType);
        }

        $invoicesInSpan = $invoicesInSpan->get();


        return collect($invoicesInSpan)->map(function($invoice){
            return [
                'type' => 'Reporte',
                'username' => $invoice['report']['user']['username'],
                'created_at' => Carbon::parse($invoice['report']['submitted_at'])->format('d/m/Y'),
                'ticket_type' => $invoice['type'] === 'Bill' ? 'Boleta' : 'Factura',

                'ticket_number' => $invoice['ticket_number'],
                'ticket_start_date' => Carbon::parse($invoice['date'])->format('d/m/Y'),
                'ticket_end_date' => Carbon::parse($invoice['date'])->format('d/m/Y'),

                'description' => $invoice['description'],
                'country' => $invoice['country'],

                'job_code' => $invoice['job_code'],
                'expense_code' => $invoice['expense_code'],

                'money_type' => $invoice['report']['money_type'],
                'amount_in_original' => number_format($invoice['amount'], 2),

                'money_exchange_rate' => number_format(Exchanger::on(new DateTime($invoice['date']))->convert(1, $invoice['report']['money_type'], MoneyType::USD), 2),
                'amount_in_dollars' => number_format($invoice->amountInDollars(), 2),
            ];
        });
    }

    private function getAttendancesData():Collection
    {
        $workersSpendings = collect(WorkersAssistant::getWorkersSpendings())->map(function($workerSpendings){
            return $workerSpendings['spendings'];
        })->flatten(1);

        $spendingsInSpan = $workersSpendings->where('date_day', '>=', $this->startDate->format('Y-m-d'))->where('date_day', '<=', $this->endDate->format('Y-m-d'));


        if ($this->jobCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.code', '=', $this->jobCode);
        }

        if ($this->expenseCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('expense.code', '=', $this->expenseCode);
        }

        if ($this->country !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.country', '=', $this->country);
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
            $attendanceFromDate = $attendance->from_date;
            $attendanceToDate = $attendance->to_date;

            return [
                'attendance_id' => $attendanceId,
                'attendance_created_at' => explode('/~/', $code)[1],
                'user' => $attendance->user->name,
                'username' => $attendance->user->username,
                'description' => $attendance->description,
                'attendance_from_date' => $attendanceFromDate,
                'attendance_to_date' => $attendanceToDate,
                'country' => $attendance->job->country,
                'job_code' => explode('/~/', $code)[2],
                'expense_code' => explode('/~/', $code)[3],
                'worker_dni' => explode('/~/', $code)[4],
                'supervisor' => explode('/~/', $code)[5],
                'worker_name' => explode('/~/', $code)[6],
                'worker_name_code' => implode('', array_map(function($name){
                    if (strlen($name) > 0){
                        return $name[0];
                    }else{
                        return '';
                    }
                }, explode(' ', str_replace(',', '', explode('/~/', $code)[6])))),
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

                    $spending->amountOriginalData = (function() use ($spending){
                        return $spending->payment->amount_data;
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


            $amountInCurrencies = MoneyType::toAssociativeArray(0);


            foreach ($item['spendings'] as $spending){
                $daysWorked += $spending->attendance_day->status === AttendanceStatus::Present->value ? 1 : 0;
                $daysNotWorked += $spending->attendance_day->status === AttendanceStatus::Absent->value ? 1 : 0;
                $amountInSoles += $spending->amountInSoles;
                $amountInDollars += $spending->amountInDollars;

                $amountInCurrencies[$spending->amountOriginalData->money_type] += $spending->amountOriginalData->amount;
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


            foreach ($amountInCurrencies as $currency => $amount){
                $amount = number_format($amount, 2, '.', '');

                if ($currency === MoneyType::PYG->value){
                    $amount = round($amount, 0);
                }

                $item['parcial_amount_in_' . strtolower($currency)] = $amount;
            }

            return $item;
        });


        return collect($withTotals->toArray())->map(function($attendance){
            return [
                'type' => 'Asistencia',
                'username' => $attendance['username'],
                'created_at' => Carbon::parse($attendance['attendance_created_at'])->format('d/m/Y'),
                'ticket_type' => 'Registro',

                'ticket_number' => $attendance['worker_dni'] . '-' . $attendance['worker_name_code'],
                'ticket_start_date' => Carbon::parse($attendance['attendance_from_date'])->format('d/m/Y'),
                'ticket_end_date' => Carbon::parse($attendance['attendance_to_date'])->format('d/m/Y'),

                'description' => $attendance['description'],
                'country' => $attendance['country'],

                'job_code' => $attendance['job_code'],
                'expense_code' => $attendance['expense_code'],

                'money_type' => MoneyType::PEN,
                'amount_in_original' => number_format($attendance['amount_in_soles'], 2),

                'money_exchange_rate' => number_format(Exchanger::on(new DateTime($attendance['attendance_from_date']))->convert(1, MoneyType::PEN, MoneyType::USD), 2),
                'amount_in_dollars' => number_format($attendance['amount_in_dollars'], 2),
            ];
        });
    }

    private function getInventoryData(): Collection
    {
        $options = [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'moneyType' => $this->moneyType,
            'country' => $this->country,
            'expenseCode' => $this->expenseCode,
            'jobCode' => $this->jobCode,
        ];

        $list = [];

        $incomes = (function() use ($options){
            $query = InventoryWarehouseIncome::query();

            if ($options['startDate'] !== null){
                $query = $query->where('date', '>=', $options['startDate']);
            }
            if ($options['endDate'] !== null){
                $query = $query->where('date', '<=', $options['endDate']);
            }
            if ($options['moneyType'] !== null){
                $query = $query->where('currency', $options['moneyType']);
            }
            if ($options['expenseCode'] !== null){
                $query = $query->where('expense_code', $options['expenseCode']);
            }
            if ($options['jobCode'] !== null){
                $query = $query->where('job_code', $options['jobCode']);
            }
            if ($options['country'] !== null){
                $query = $query->whereHas('warehouse', function($query) use ($options){
                    $query->where('country', $options['country']);
                });
            }

            return $query->orderBy('date');
        })();



        $incomes->each((function($income) use (&$list, $options){
            $productsIdsInIncome = $income->items()
                ->groupBy('inventory_product_id')
                ->select('inventory_product_id')
                ->pluck('inventory_product_id')
                ->unique();

            $productsIdsInIncome->each((function($productId) use ($income, &$list, $options){
                $productItems = $income->items()->where('inventory_product_id', $productId);

                $product = InventoryProduct::find($productId);

                if ($product->is_loanable){
                    return;
                }


                $balance = [
                    'quantity' => (clone $productItems)->count(),
                    'amount' => (clone $productItems)->first()?->buy_amount ?? 0,
                    'total' => (clone $productItems)->count() * ((clone $productItems)->first()?->buy_amount ?? 0),
                ];

                $outcomes = (function() use ($income, $product, $options){
                    $query = InventoryWarehouseOutcome::query();
                    if ($options['startDate'] !== null){
                        $query = $query->where('date', '>=', $options['startDate']);
                    }
                    if ($options['endDate'] !== null){
                        $query = $query->where('date', '<=', $options['endDate']);
                    }

                    return $query->orderBy('date')->get();
                })();

                $outcomes->each((function($outcome) use ($income, &$list, &$balance, $product){
                    $itemsSold = $outcome->items()->where('inventory_warehouse_income_id', $income->id)
                                                ->where('status', InventoryProductItemStatus::Sold)
                                                ->where('inventory_product_id', $product->id);

                    if ($itemsSold->count() === 0){
                        return;
                    }

                    //Balance:
                    $balance['quantity'] -= $itemsSold->count();
                    $balance['amount'] = $itemsSold->first()->sell_amount;
                    $balance['total'] -= $itemsSold->count() * $itemsSold->first()->sell_amount;


                    $amount = $itemsSold->count() * $itemsSold->first()->sell_amount;
                    $list[] = [
                        'type' => 'Inventario',
                        'username' => $outcome->user->username,
                        'created_at' => Carbon::parse($outcome->created_at)->format('d/m/Y'),
                        'ticket_type' => 'Boleta',

                        'ticket_number' => (function() use ($outcome){
                            return 'SAL-#00' . $outcome->id . '-' . $outcome->ticket_number;
                        })(),
                        'ticket_start_date' => Carbon::parse($outcome->date)->format('d/m/Y'),
                        'ticket_end_date' => Carbon::parse($outcome->date)->format('d/m/Y'),

                        'description' => $outcome->products->map(function($product){
                            return $product->name . ' - ' . $product->category;
                        })->unique()->implode(', '),
                        'country' => $outcome->warehouse->country,

                        'job_code' => $outcome->job_code,
                        'expense_code' => $outcome->expense_code,

                        'money_type' => $income->currency,
                        'amount_in_original' => number_format($amount, 2),

                        'money_exchange_rate' => number_format(Exchanger::on(new DateTime($income->date))->convert(1, $income->currency, MoneyType::USD), 2),
                        'amount_in_dollars' => number_format(Exchanger::on(new DateTime($income->date))->convert($amount, $income->currency, MoneyType::USD), 2),
                    ];
                }));
            }));
        }));

        return collect($list);
    }

    private function createTable():array
    {
        $body = collect([]);

        if ($this->type){
            if ($this->type === 'Reports'){
                $invoices = $this->getInvoicesData();
                $body = $body->merge($invoices);
            }elseif ($this->type === 'Attendances'){
                $attendances = $this->getAttendancesData();
                $body = $body->merge($attendances);
            }elseif ($this->type === 'Inventory'){
                $inventory = $this->getInventoryData();
                $body = $body->merge($inventory);
            }
        }else{
            $invoices = $this->getInvoicesData();
            $attendances = $this->getAttendancesData();
            $inventory = $this->getInventoryData();

            $body = collect($invoices)->merge($attendances)->merge($inventory);
        }



        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'Tipo',
                    'key' => 'type',
                ],
                [
                    'title' => 'Usuario',
                    'key' => 'username',
                ],
                [
                    'title' => 'Fecha de Creación',
                    'key' => 'created_at',
                ],
                [
                    'title' => 'Tipo de Boleta',
                    'key' => 'ticket_type',
                ],
                [
                    'title' => 'Número de Boleta',
                    'key' => 'ticket_number',
                ],
                [
                    'title' => 'Fecha de Inicio',
                    'key' => 'ticket_start_date',
                ],
                [
                    'title' => 'Fecha de Fin',
                    'key' => 'ticket_end_date',
                ],
                [
                    'title' => 'Descripción',
                    'key' => 'description',
                ],
                [
                    'title' => 'País',
                    'key' => 'country',
                ],
                [
                    'title' => 'Código de Trabajo',
                    'key' => 'job_code',
                ],
                [
                    'title' => 'Código de Gasto',
                    'key' => 'expense_code',
                ],
                [
                    'title' => 'Tipo de Moneda',
                    'key' => 'money_type',
                ],
                [
                    'title' => 'Monto',
                    'key' => 'amount_in_original',
                ],
                [
                    'title' => 'Tipo de Cambio',
                    'key' => 'money_exchange_rate',
                ],
                [
                    'title' => 'Monto en Dólares',
                    'key' => 'amount_in_dollars',
                ]
            ],
            'body' => $body,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'startDate' => $this->startDate->format('c'),
                'endDate' => $this->endDate->format('c'),
                'country' => $this->country,
                'moneyType' => $this->moneyType,
                'expenseCode' => $this->expenseCode,
                'jobCode' => $this->jobCode,
                'type' => $this->type,
            ],
        ];
    }
}
