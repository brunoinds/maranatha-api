<?php

namespace App\Support\Generators\Records\Reports;

use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Enums\ReportStatus;
use App\Helpers\Toolbox;
use App\Models\Report;

use App\Support\Assistants\WorkersAssistant;
use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Invoice;
use Brunoinds\SunatDolarLaravel\Exchange;


class RecordReportsByTime
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $country = null;
    private string|null $moneyType = null;
    private string|null $type = null;
    
    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param string $options['country']
     * @param string $options['moneyType']
     * @param string $options['type']
     */
    
    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->country = $options['country'];
        $this->moneyType = $options['moneyType'];
        $this->type = $options['type'];
    }

    private function getReportsData():Collection
    {
        //Get filtered reports data:

        $reportsInSpan = Report::where('created_at', '>=', $this->startDate->format('c'))->where('created_at', '<=', $this->endDate->format('c'));


        if ($this->country !== null){
            $reportsInSpan = $reportsInSpan->where('country', '=', $this->country);
        }

        if ($this->moneyType !== null){
            $reportsInSpan = $reportsInSpan->where('money_type', '=', $this->moneyType);
        }

        if ($this->type !== null){
            $reportsInSpan = $reportsInSpan->where('type', '=', $this->type);
        }

        $reportsInSpan = $reportsInSpan->get();

        return $reportsInSpan;
    }

    private function createTable():array{
        $reports = $this->getReportsData();

        $indicators = [
            'average_between_created_and_submitted' => [
                'count' => 0,
                'total' => 0,
                'average' => 0,
            ],
            'average_between_submitted_and_approved' => [
                'count' => 0,
                'total' => 0,
                'average' => 0,
            ],
            'average_between_approved_and_restituted' => [
                'count' => 0,
                'total' => 0,
                'average' => 0,
            ],
            'average_between_submitted_and_restituted' => [
                'count' => 0,
                'total' => 0,
                'average' => 0,
            ],
        ];


        $reports->each(function($item) use (&$indicators){
            if ($item->submitted_at !== null){
                $indicators['average_between_created_and_submitted']['count']++;
                $indicators['average_between_created_and_submitted']['total'] += Carbon::createFromDate(new DateTime($item->created_at))->diffInHours(Carbon::createFromDate(new DateTime($item->submitted_at)));
                $indicators['average_between_created_and_submitted']['average'] = $indicators['average_between_created_and_submitted']['total'] / $indicators['average_between_created_and_submitted']['count'];
            }
            if ($item->approved_at !== null){
                $indicators['average_between_submitted_and_approved']['count']++;
                $indicators['average_between_submitted_and_approved']['total'] += Carbon::createFromDate(new DateTime($item->submitted_at))->diffInHours(Carbon::createFromDate(new DateTime($item->approved_at)));
                $indicators['average_between_submitted_and_approved']['average'] = $indicators['average_between_submitted_and_approved']['total'] / $indicators['average_between_submitted_and_approved']['count'];
            }
            if ($item->restituted_at !== null){
                $indicators['average_between_approved_and_restituted']['count']++;
                $indicators['average_between_approved_and_restituted']['total'] += Carbon::createFromDate(new DateTime($item->approved_at))->diffInHours(Carbon::createFromDate(new DateTime($item->restituted_at)));
                $indicators['average_between_approved_and_restituted']['average'] = $indicators['average_between_approved_and_restituted']['total'] / $indicators['average_between_approved_and_restituted']['count'];

                $indicators['average_between_submitted_and_restituted']['count']++;
                $indicators['average_between_submitted_and_restituted']['total'] += Carbon::createFromDate(new DateTime($item->submitted_at))->diffInHours(Carbon::createFromDate(new DateTime($item->restituted_at)));
                $indicators['average_between_submitted_and_restituted']['average'] = $indicators['average_between_submitted_and_restituted']['total'] / $indicators['average_between_submitted_and_restituted']['count'];
            }
        });


        $body = collect($indicators)->map(function($indicator, $key){
            return [
                'indicator' => $key,
                'average_time_in_hours' => $indicator['average'],
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'Indicador',
                    'key' => 'indicator',
                ],
                [
                    'title' => 'Tiempo Promedio (Horas)',
                    'key' => 'average_time_in_hours',
                ],
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
                'type' => $this->type
            ],
        ];
    }
}