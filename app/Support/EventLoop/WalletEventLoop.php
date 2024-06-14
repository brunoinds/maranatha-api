<?php

namespace App\Support\EventLoop;

use App\Helpers\Enums\ReportStatus;
use Illuminate\Support\Collection;
use App\Models\Report;
use App\Support\Assistants\BalanceAssistant;
use Carbon\Carbon;
use DateTime;
use App\Support\EventLoop\Notifications\Notification;
use App\Models\User;
use App\Helpers\Toolbox;
use App\Models\Expense;

class WalletEventLoop{
    public static function getMessages(): array{
        $negativeBalances = self::getNegativeBalances();
        $middleMonthTrending = self::getMiddleMonthTrending();
        $finalMonthTrending = self::getFinalMonthTrending();

        $getNames = function(Collection $collection){
            $names = $collection->map(function($item) {
                return ['user_id' => $item->user->id, 'item' => $item];
            })->groupBy('user_id')->map(function($item){
                return $item->first()['item']->user->username;
            })->values()->toArray();

            $names = array_map(function($name){
                return '@' . $name;
            }, $names);
            return join(', ', $names);
        };

        $messages = [];

        if($negativeBalances->count() > 0){
            $names = $getNames($negativeBalances);
            $messages[] = [
                'title' => 'Billeteras en negativo 💵',
                'message' => '⚠️ Hay ' . $negativeBalances->count() . ' billeteras en negativo. Ellas pertenencen a ' .  $names . '. Revísalas en la sección "Billeteras"',
                'type' => 'NegativeBalances',
                'data' => [
                    'deepLink' =>  env('APP_WEB_URL') . '/management?goTo=wallets'
                ]
            ];
        }

        if($middleMonthTrending->count() > 0){
            $middleMonthTrending->each(function($item) use (&$messages){

                $messageLines = [
                    'Hasta ahora estos son tus gastos en ' . $item['month']['name'] . ':',
                    '- 📅 Total mensual: S/. ' . number_format($item['monthlyBalance']['debtsAccummulated'], 2),
                    '- 🌞 Promedio diario: S/. ' . number_format($item['monthlyBalance']['averageDaily'], 2),
                    '- 📊 Top 3 expenses: ' . join(', ', array_map(function($expense){
                        return $expense['name'] . ' (S/. ' . number_format($expense['amount'], 2) . ')';
                    }, $item['monthlyBalance']['expenses'])),
                    '- 🗓️ Acumulado anual: S/. ' . number_format($item['annualBalance']['debtsAccumulated'], 2),
                    'Puedes ver más detalles en la sección "Mi Billetera"'
                ];

                $messages[] = [
                    'title' => '📈 Tendencia de gastos en ' . $item['month']['name'],
                    'message' => join("\n", $messageLines),
                    'type' => 'MiddleMonthTrending',
                    'user' => $item['user'],
                    'data' => [
                        'deepLink' =>  env('APP_WEB_URL') . '/my-wallet'
                    ]
                ];
            });
        }

        if($finalMonthTrending->count() > 0){
            $finalMonthTrending->each(function($item) use (&$messages){
                $messageLines = [
                    'Estos han sido tus gastos en el mes anterior:',
                    '- 📅 Total mensual: S/. ' . number_format($item['monthlyBalance']['debtsAccummulated'], 2),
                    '- 🌞 Promedio diario: S/. ' . number_format($item['monthlyBalance']['averageDaily'], 2),
                    '- 📊 Top 3 expenses: ' . join(', ', array_map(function($expense){
                        return $expense['name'] . ' (S/. ' . number_format($expense['amount'], 2) . ')';
                    }, $item['monthlyBalance']['expenses'])),
                    '- 🗓️ Acumulado anual: S/. ' . number_format($item['annualBalance']['debtsAccumulated'], 2),
                    'Puedes ver más detalles en la sección "Mi Billetera"'
                ];


                $messages[] = [
                        'title' => '📈 Tendencia de gastos en ' . $item['month']['name'],
                        'message' => join("\n", $messageLines),
                        'type' => 'FinalMonthTrending',
                        'user' => $item['user'],
                        'data' => [
                            'deepLink' =>  env('APP_WEB_URL') . '/my-wallet'
                        ]
                    ];
                });
            }

        return $messages;
    }

    public static function getNotifications(string | null $ofType = null): Collection{
        return collect(self::getMessages())->filter(function($message) use ($ofType){
            return $ofType === null || $message['type'] === $ofType;
        })->map(function($message){
            $notification = new Notification($message['title'], $message['message'], isset($message['data']) ? $message['data'] : [], isset($message['user']) ? $message['user'] : null);
            return $notification;
        });
    }




    private static function getNegativeBalances(): Collection
    {
        $usersBalances = [];
        User::each(function($user) use (&$usersBalances){
            $usersBalances[] = Toolbox::toObject(BalanceAssistant::generateUserBalanceByYear($user, Carbon::now()->year));
        });

        $usersBalances = collect($usersBalances);

        $items = $usersBalances->filter(function($item) {
            return $item->totals->balance < 0;
        });

        return $items;
    }

    private static function getMiddleMonthTrending(): Collection
    {
        //Estamos a la mitad del mes y estos son tus gastos
        //Valor total gasto: $1000, Promedio diario: $50, Expenses que más ha gastado
        //Gasto acumulado del año

        \Carbon\Carbon::setLocale('es');
        $usersBalances = [];
        User::each(function($user) use (&$usersBalances){
            $yearBalance = Toolbox::toObject(BalanceAssistant::generateUserBalanceByYear($user, Carbon::now()->year));
            $monthBalance = Toolbox::toObject(BalanceAssistant::generateUserBalanceByMonthYear($user, Carbon::now()->month, Carbon::now()->year));

            $montlyHighExpenses = collect($monthBalance->spendings->by_expenses)->sortByDesc('amount')->take(3)->map(function($item){
                return [
                    'code' => $item->code,
                    'name' => Expense::where('code', $item->code)->first()->name,
                    'amount' => $item->amount
                ];
            })->toArray();

            $usersBalances[] = [
                'user' => $user,
                'annualBalance' => [
                    'debtsAccumulated' => $yearBalance->totals->debit,
                ],
                'monthlyBalance' => [
                    'expenses' => $montlyHighExpenses,
                    'averageDaily' => round($monthBalance->totals->debit / Carbon::now()->daysInMonth, 1),
                    'debtsAccummulated' => $monthBalance->totals->debit,
                ],
                'month' => [
                    'name' => Carbon::now()->monthName,
                ]
            ];
        });

        $usersBalances = collect($usersBalances);

        return $usersBalances;
    }

    public static function getFinalMonthTrending(): Collection
    {
        \Carbon\Carbon::setLocale('es');
        $usersBalances = [];
        User::each(function($user) use (&$usersBalances){
            $carbon = Carbon::now();
            $date = $carbon->subMonth();

            $yearBalance = Toolbox::toObject(BalanceAssistant::generateUserBalanceByYear($user, $date->year));
            $monthBalance = Toolbox::toObject(BalanceAssistant::generateUserBalanceByMonthYear($user, $date->month, $date->year));

            $montlyHighExpenses = collect($monthBalance->spendings->by_expenses)->sortByDesc('amount')->take(3)->map(function($item){
                return [
                    'code' => $item->code,
                    'name' => Expense::where('code', $item->code)->first()->name,
                    'amount' => $item->amount
                ];
            })->toArray();

            $usersBalances[] = [
                'user' => $user,
                'annualBalance' => [
                    'debtsAccumulated' => $yearBalance->totals->debit,
                ],
                'monthlyBalance' => [
                    'expenses' => $montlyHighExpenses,
                    'averageDaily' => round($monthBalance->totals->debit / $date->daysInMonth, 1),
                    'debtsAccummulated' => $monthBalance->totals->debit,
                ],
                'month' => [
                    'name' => $date->monthName,
                ],
            ];
        });

        $usersBalances = collect($usersBalances);

        return $usersBalances;
    }
}
