<?php

namespace App\Support\EventLoop;

use App\Helpers\Enums\ReportStatus;
use Illuminate\Support\Collection;
use App\Models\Report;
use Carbon\Carbon;
use DateTime;


/*
$adminUser = User::where('username', 'admin')->first();

OneSignal::sendNotificationToExternalUser(
    headings: "Nuevo reporte recibido 📥",
    message: $user->name . " ha enviado un nuevo reporte de " . Toolbox::moneyPrefix($report->money_type->value) . ' ' . number_format($report->amount(), 2) . " y está esperando por su aprobación.",
    userId: Toolbox::getOneSignalUserId($adminUser->id),
    data: [
        'deepLink' => $notificationUrlOnUserReports
    ]
);
*/
class ReportsEventLoop{
    const MAXIMUM_WAITING_HOURS_APPROVAL = 24;
    const MAXIMUM_WAITING_HOURS_RESTITUTION = 24;
    const MAXIMUM_WAITING_HOURS_FIX_REJECTED = 72;

    public static function getMessages(): array{
        $slowWaitingApprovalReports = self::getSlowWaitingApprovalReports();
        $slowWaitingRestitutedReports = self::getSlowWaitingRestitutedReports();
        $slowWaitingFixRejectedReports = self::getSlowWaitingFixRejectedReports();

        $messages = [];
        if($slowWaitingApprovalReports->count() > 0){
            $messages[] = [
                'title' => $slowWaitingApprovalReports->count() . ' reportes esperando aprobación 📥',
                'message' => 'Hay ' . $slowWaitingApprovalReports->count() . ' reportes esperando aprobación por más de ' . self::MAXIMUM_WAITING_HOURS_APPROVAL . ' horas. Revísalos en la sección de reportes.'
            ];
        }

        if($slowWaitingRestitutedReports->count() > 0){
            $messages[] = [
                'title' => $slowWaitingRestitutedReports->count() . ' reportes esperando reembolso 💸',
                'message' => 'Hay ' . $slowWaitingRestitutedReports->count() . ' reportes esperando reembolso por más de ' . self::MAXIMUM_WAITING_HOURS_RESTITUTION . ' horas. Revísalos en la sección de reportes.'
            ];
        }

        if($slowWaitingFixRejectedReports->count() > 0){
            $messages[] = [
                'title' => $slowWaitingFixRejectedReports->count() . ' reportes esperando corrección 🛠️',
                'message' => 'Hay ' . $slowWaitingFixRejectedReports->count() . ' reportes rechazados que están esperando corrección por más de ' . self::MAXIMUM_WAITING_HOURS_FIX_REJECTED . ' horas. Revísalos en la sección de reportes.'
            ];
        }

        return $messages;
    }




    private static function getSlowWaitingApprovalReports(): Collection
    {
        $reports = Report::where('status', ReportStatus::Submitted->value)->get();

        $reports = $reports->filter(function($item) {
            $timeSpanInHours = Carbon::createFromDate(new DateTime($item->submitted_at))->diffInHours(Carbon::now());
            return $timeSpanInHours >= self::MAXIMUM_WAITING_HOURS_APPROVAL;
        });

        return $reports;
    }

    private static function getSlowWaitingRestitutedReports(): Collection
    {
        $reports = Report::where('status', ReportStatus::Approved->value)->get();

        $reports = $reports->filter(function($item) {
            $timeSpanInHours = Carbon::createFromDate(new DateTime($item->approved_at))->diffInHours(Carbon::now());
            return $timeSpanInHours >= self::MAXIMUM_WAITING_HOURS_RESTITUTION;
        });

        return $reports;
    }

    private static function getSlowWaitingFixRejectedReports(): Collection
    {
        $reports = Report::where('status', ReportStatus::Rejected->value)->get();

        $reports = $reports->filter(function($item) {
            $timeSpanInHours = Carbon::createFromDate(new DateTime($item->rejected_at))->diffInHours(Carbon::now());
            return $timeSpanInHours >= self::MAXIMUM_WAITING_HOURS_FIX_REJECTED;
        });

        return $reports;
    }
}


/*
Event list:
- If there is any Report waiting approval more than 24h
- If there is any Report waiting refund more than 24h
- If there is any Report


- If there is any Wallet in alert

*/
