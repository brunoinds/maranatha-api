<?php

namespace App\Support\EventLoop;

use App\Helpers\Enums\ReportStatus;
use Illuminate\Support\Collection;
use App\Models\Report;
use Carbon\Carbon;
use DateTime;
use App\Support\Generators\Records\Users\RecordUsersByCosts;
use App\Support\Generators\Records\Reports\RecordReportsByTime;
use App\Support\EventLoop\Notifications\Notification;

class RecordsEventLoop{
    const MINIMAL_DIFFERENCE_TO_NOTIFY_ON_SPENDINGS = 10;
    const MINIMAL_DIFFERENCE_TO_NOTIFY_ON_TIMMING = 3;

    public static function getMessages(): array{
        $trendingOnReportsSpendings = self::getTrendingOnReportsSpendings();
        $trendingOnReportsTimmingSubmittedAndApproved = self::getTrendingOnReportsTimmingSubmittedAndApproved();
        $trendingOnReportsTimmingApprovedAndRestituted = self::getTrendingOnReportsTimmingApprovedAndRestituted();

        $messages = [];
        if($trendingOnReportsSpendings !== null && ($trendingOnReportsSpendings['difference']['percentage'] >= self::MINIMAL_DIFFERENCE_TO_NOTIFY_ON_SPENDINGS || $trendingOnReportsSpendings['difference']['percentage'] <= -self::MINIMAL_DIFFERENCE_TO_NOTIFY_ON_SPENDINGS)){
            $messages[] = [
                'title' => (function() use ($trendingOnReportsSpendings){
                    if ($trendingOnReportsSpendings['difference']['percentage'] < 0){
                        return '📉 Disminución en gastos';
                    } else {
                        return '📈 Aumento en gastos';
                    }
                })(),
                'message' => (function() use ($trendingOnReportsSpendings){
                    if ($trendingOnReportsSpendings['difference']['percentage'] < 0){
                        return 'Gastos con boletas/facturas: disminución del ' . number_format(abs($trendingOnReportsSpendings['difference']['percentage']), 1) . '%. Gastado este mes: $' . number_format($trendingOnReportsSpendings['current']['amount'], 2) . ', mes anterior: $' . number_format($trendingOnReportsSpendings['previous']['amount'], 2) . '.';
                    } else {
                        return 'Gastos con boletas/facturas: aumento del ' . number_format($trendingOnReportsSpendings['difference']['percentage'], 1) . '%. Gastado este mes: $' . number_format($trendingOnReportsSpendings['current']['amount'], 2) . ', mes anterior: $' . number_format($trendingOnReportsSpendings['previous']['amount'], 2) . '.';
                    }
                })(),
                'type' => 'TrendingOnSpendings'
            ];
        }

        if ($trendingOnReportsTimmingSubmittedAndApproved !== null && ($trendingOnReportsTimmingSubmittedAndApproved['difference']['percentage'] >= self::MINIMAL_DIFFERENCE_TO_NOTIFY_ON_TIMMING || $trendingOnReportsTimmingSubmittedAndApproved['difference']['percentage'] <= -self::MINIMAL_DIFFERENCE_TO_NOTIFY_ON_TIMMING)){
            $messages[] = [
                'title' => (function() use ($trendingOnReportsTimmingSubmittedAndApproved){
                    if ($trendingOnReportsTimmingSubmittedAndApproved['difference']['percentage'] < 0){
                        return '⬆️⏰ Tiempo aprobación de reportes';
                    } else {
                        return '⬇️⏰ Tiempo aprobación de reportes';
                    }
                })(),
                'message' => (function() use ($trendingOnReportsTimmingSubmittedAndApproved){
                    if ($trendingOnReportsTimmingSubmittedAndApproved['difference']['percentage'] < 0){
                        return 'El tiempo promedio de aprobación de reportes ha disminuido en un ' . number_format(abs($trendingOnReportsTimmingSubmittedAndApproved['difference']['percentage']), 0) . '% en comparación al mes pasado. Hasta ahora, el tiempo promedio ha sido de ' . number_format($trendingOnReportsTimmingSubmittedAndApproved['current']['amount'], 0) . ' horas, siendo que en el mismo periodo en el mes anterior fue de ' . number_format($trendingOnReportsTimmingSubmittedAndApproved['previous']['amount'], 0) . ' horas.';
                    } else {
                        return 'El tiempo promedio de aprobación de reportes ha aumentado en un ' . number_format($trendingOnReportsTimmingSubmittedAndApproved['difference']['percentage'], 0) . '% en comparación al mes pasado. Hasta ahora, el tiempo promedio ha sido de ' . number_format($trendingOnReportsTimmingSubmittedAndApproved['current']['amount'], 0) . ' horas, siendo que en el mismo periodo en el mes anterior fue de ' . number_format($trendingOnReportsTimmingSubmittedAndApproved['previous']['amount'], 0) . ' horas.';
                    }
                })(),
                'type' => 'TrendingOnTimmingSubmittedAndApproved'
            ];
        }

        if ($trendingOnReportsTimmingApprovedAndRestituted !== null && ($trendingOnReportsTimmingApprovedAndRestituted['difference']['percentage'] >= self::MINIMAL_DIFFERENCE_TO_NOTIFY_ON_TIMMING || $trendingOnReportsTimmingApprovedAndRestituted['difference']['percentage'] <= -self::MINIMAL_DIFFERENCE_TO_NOTIFY_ON_TIMMING)){
            $messages[] = [
                'title' => (function() use ($trendingOnReportsTimmingApprovedAndRestituted){
                    if ($trendingOnReportsTimmingApprovedAndRestituted['difference']['percentage'] < 0){
                        return '⬆️⏰ Tiempo reembolso de reportes';
                    } else {
                        return '⬇️⏰ Tiempo reembolso de reportes';
                    }
                })(),
                'message' => (function() use ($trendingOnReportsTimmingApprovedAndRestituted){
                    if ($trendingOnReportsTimmingApprovedAndRestituted['difference']['percentage'] < 0){
                        return 'El tiempo promedio entre la aprobación y el reembolso de reportes ha disminuido en un ' . number_format(abs($trendingOnReportsTimmingApprovedAndRestituted['difference']['percentage']), 0) . '% en comparación al mes pasado. Hasta ahora, el tiempo promedio ha sido de ' . number_format($trendingOnReportsTimmingApprovedAndRestituted['current']['amount'], 0) . ' horas, siendo que en el mismo periodo en el mes anterior fue de ' . number_format($trendingOnReportsTimmingApprovedAndRestituted['previous']['amount'], 0) . ' horas.';
                    } else {
                        return 'El tiempo promedio entre la aprobación y el reembolso de reportes ha aumentado en un ' . number_format($trendingOnReportsTimmingApprovedAndRestituted['difference']['percentage'], 0) . '% en comparación al mes pasado. Hasta ahora, el tiempo promedio ha sido de ' . number_format($trendingOnReportsTimmingApprovedAndRestituted['current']['amount'], 0) . ' horas, siendo que en el mismo periodo en el mes anterior fue de ' . number_format($trendingOnReportsTimmingApprovedAndRestituted['previous']['amount'], 0) . ' horas.';
                    }
                })(),
                'type' => 'TrendingOnTimmingApprovedAndRestituted'
            ];
        }

        return $messages;
    }

    public static function getNotifications(string | null $ofType = null): Collection{
        return collect(self::getMessages())->filter(function($message) use ($ofType){
            return $ofType === null || $message['type'] === $ofType;
        })->map(function($message){
            $notification = new Notification($message['title'], $message['message']);
            return $notification;
        });
    }




    private static function getTrendingOnReportsSpendings(): array|null
    {
        $previousMonthTimeSpan = [
            'startDate' => Carbon::now()->subMonth()->startOfMonth()->toDateTime(),
            'endDate' => Carbon::now()->subMonth()->toDateTime()
        ];

        $currentMonthTimeSpan = [
            'startDate' => Carbon::now()->startOfMonth()->toDateTime(),
            'endDate' => Carbon::now()->toDateTime(),
        ];


        $previousMonth = new RecordUsersByCosts([
            'startDate' => $previousMonthTimeSpan['startDate'],
            'endDate' => $previousMonthTimeSpan['endDate'],
            'jobCode' => null,
            'expenseCode' => null,
            'type' => null,
            'userId' => null,
        ]);

        $currentMonth = new RecordUsersByCosts([
            'startDate' => $currentMonthTimeSpan['startDate'],
            'endDate' => $currentMonthTimeSpan['endDate'],
            'jobCode' => null,
            'expenseCode' => null,
            'type' => null,
            'userId' => null,
        ]);

        $previousMonth = $previousMonth->generate();
        $currentMonth = $currentMonth->generate();


        $previousMonthSpendings = (function() use ($previousMonth){
            return collect($previousMonth['data']['body'])->filter(function($item){
                return $item['type'] !== 'Workers';
            })->sum('amount_in_dollars');
        })();
        $currentMonthSpendings = (function() use ($currentMonth){
            return collect($currentMonth['data']['body'])->filter(function($item){
                return $item['type'] !== 'Workers';
            })->sum('amount_in_dollars');
        })();

        $differenceInAmount = $currentMonthSpendings - $previousMonthSpendings;

        if ($previousMonthSpendings === 0 || $currentMonthSpendings === 0){
            return null;
        }


        $differenceInPercentage = ($differenceInAmount / $previousMonthSpendings) * 100;

        return [
            'previous' => [
                'amount' => $previousMonthSpendings,
            ],
            'current' => [
                'amount' => $currentMonthSpendings,
            ],
            'difference' => [
                'amount' => $differenceInAmount,
                'percentage' => $differenceInPercentage,
            ],
        ];
    }

    private static function getTrendingOnReportsTimmingSubmittedAndApproved(): array|null
    {
        $previousMonthTimeSpan = [
            'startDate' => Carbon::now()->subMonth()->startOfMonth()->toDateTime(),
            'endDate' => Carbon::now()->subMonth()->toDateTime()
        ];

        $currentMonthTimeSpan = [
            'startDate' => Carbon::now()->startOfMonth()->toDateTime(),
            'endDate' => Carbon::now()->toDateTime(),
        ];


        $previousMonth = new RecordReportsByTime([
            'startDate' => $previousMonthTimeSpan['startDate'],
            'endDate' => $previousMonthTimeSpan['endDate'],
            'country' => null,
            'moneyType' => null,
            'type' => null,
        ]);

        $currentMonth = new RecordReportsByTime([
            'startDate' => $currentMonthTimeSpan['startDate'],
            'endDate' => $currentMonthTimeSpan['endDate'],
            'country' => null,
            'moneyType' => null,
            'type' => null,
        ]);

        $previousMonth = $previousMonth->generate();
        $currentMonth = $currentMonth->generate();


        $previousMonthTimming = (function() use ($previousMonth){
            return collect($previousMonth['data']['body'])->filter(function($item){
                return $item['indicator'] === 'average_between_submitted_and_approved';
            })->sum('average_time_in_hours');
        })();
        $currentMonthTimming = (function() use ($currentMonth){
            return collect($currentMonth['data']['body'])->filter(function($item){
                return $item['indicator'] === 'average_between_submitted_and_approved';
            })->sum('average_time_in_hours');
        })();


        $differenceInAmount = $currentMonthTimming - $previousMonthTimming;

        if ($previousMonthTimming === 0 || $currentMonthTimming === 0){
            return null;
        }


        $differenceInPercentage = ($differenceInAmount / $previousMonthTimming) * 100;

        return [
            'previous' => [
                'amount' => $previousMonthTimming,
            ],
            'current' => [
                'amount' => $currentMonthTimming,
            ],
            'difference' => [
                'amount' => $differenceInAmount,
                'percentage' => $differenceInPercentage,
            ],
        ];
    }

    private static function getTrendingOnReportsTimmingApprovedAndRestituted(): array|null
    {
        $previousMonthTimeSpan = [
            'startDate' => Carbon::now()->subMonth()->startOfMonth()->toDateTime(),
            'endDate' => Carbon::now()->subMonth()->toDateTime()
        ];

        $currentMonthTimeSpan = [
            'startDate' => Carbon::now()->startOfMonth()->toDateTime(),
            'endDate' => Carbon::now()->toDateTime(),
        ];


        $previousMonth = new RecordReportsByTime([
            'startDate' => $previousMonthTimeSpan['startDate'],
            'endDate' => $previousMonthTimeSpan['endDate'],
            'country' => null,
            'moneyType' => null,
            'type' => null,
        ]);

        $currentMonth = new RecordReportsByTime([
            'startDate' => $currentMonthTimeSpan['startDate'],
            'endDate' => $currentMonthTimeSpan['endDate'],
            'country' => null,
            'moneyType' => null,
            'type' => null,
        ]);

        $previousMonth = $previousMonth->generate();
        $currentMonth = $currentMonth->generate();


        $previousMonthTimming = (function() use ($previousMonth){
            return collect($previousMonth['data']['body'])->filter(function($item){
                return $item['indicator'] === 'average_between_approved_and_restituted';
            })->sum('average_time_in_hours');
        })();
        $currentMonthTimming = (function() use ($currentMonth){
            return collect($currentMonth['data']['body'])->filter(function($item){
                return $item['indicator'] === 'average_between_approved_and_restituted';
            })->sum('average_time_in_hours');
        })();

        $differenceInAmount = $currentMonthTimming - $previousMonthTimming;

        if ($previousMonthTimming === 0 || $currentMonthTimming === 0){
            return null;
        }


        $differenceInPercentage = ($differenceInAmount / $previousMonthTimming) * 100;

        return [
            'previous' => [
                'amount' => $previousMonthTimming,
            ],
            'current' => [
                'amount' => $currentMonthTimming,
            ],
            'difference' => [
                'amount' => $differenceInAmount,
                'percentage' => $differenceInPercentage,
            ],
        ];
    }
}


/*
Event list:
- New trend on reports spending
- New trend on envio y reembolso
- New trend on aprobacion y reembolso
*/
