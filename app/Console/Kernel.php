<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Support\EventLoop\ReportsEventLoop;
use App\Support\EventLoop\RecordsEventLoop;
use App\Support\EventLoop\WalletEventLoop;
use App\Support\EventLoop\Notifications\Notifications;
use App\Support\EventLoop\Notifications\Notification;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('attachments:clear-unreachable')->dailyAt('01:45')->timezone('America/Lima');

        $schedule->command('backup:run')->dailyAt('02:00')->timezone('America/Lima')->appendOutputTo('storage/logs/backup.log');

        $schedule->command('backup:clean')->dailyAt('02:15')->timezone('America/Lima');
        $schedule->command('backup:sync-remote')->dailyAt('02:30')->timezone('America/Lima');
        $schedule->command('backup:clear-remote-trash-bin')->dailyAt('02:45')->timezone('America/Lima');

        $schedule->call(function(){
            Notifications::sendNotificationsToAdministrator(RecordsEventLoop::getNotifications('TrendingOnSpendings'));
        })->weekly()->sundays()->at('11:00')->timezone('America/Lima');

        $schedule->call(function(){
            Notifications::sendNotificationsToAdministrator(RecordsEventLoop::getNotifications('TrendingOnTimmingSubmittedAndApproved'));
            Notifications::sendNotificationsToAdministrator(RecordsEventLoop::getNotifications('TrendingOnTimmingApprovedAndRestituted'));
        })->weekly()->sundays()->at('11:30')->timezone('America/Lima');

        $schedule->call(function(){
            Notifications::sendNotificationsToAdministrator(WalletEventLoop::getNotifications('NegativeBalances'));
        })->weekly()->thursdays()->at('11:30')->timezone('America/Lima');

        $schedule->call(function(){
            Notifications::sendNotificationsToUsersTargets(WalletEventLoop::getNotifications('MiddleMonthTrending'));
        })->monthlyOn(25, '11:45')->timezone('America/Lima');

        $schedule->call(function(){
            Notifications::sendNotificationsToUsersTargets(WalletEventLoop::getNotifications('FinalMonthTrending'));
        })->monthlyOn(8, '11:45')->timezone('America/Lima');

        $schedule->call(function(){
            Notifications::sendNotificationsToAdministrator(ReportsEventLoop::getNotifications());
        })->daily()->at('19:15')->timezone('America/Lima');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
