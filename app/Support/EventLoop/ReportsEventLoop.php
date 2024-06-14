<?php

namespace App\Support\EventLoop;

use App\Helpers\Enums\ReportStatus;
use Illuminate\Support\Collection;
use App\Models\Report;
use Carbon\Carbon;
use DateTime;
use App\Support\EventLoop\Notifications\Notification;



class ReportsEventLoop{
    const MAXIMUM_WAITING_HOURS_APPROVAL = 48;
    const MAXIMUM_WAITING_HOURS_RESTITUTION = 48;
    const MAXIMUM_WAITING_HOURS_FIX_REJECTED = 72;

    public static function getMessages(): array{
        $slowWaitingApprovalReports = self::getSlowWaitingApprovalReports();
        $slowWaitingRestitutedReports = self::getSlowWaitingRestitutedReports();
        $slowWaitingFixRejectedReports = self::getSlowWaitingFixRejectedReports();

        $messages = [];

        $getNames = function(Collection $collection){
            $names = $collection->groupBy('user_id')->map(function($item){
                return $item->first()->user->username;
            })->values()->toArray();

            $names = array_map(function($name){
                return '@' . $name;
            }, $names);
            return join(', ', $names);
        };

        if($slowWaitingApprovalReports->count() > 0){
            $names = $getNames($slowWaitingApprovalReports);
            $messages[] = [
                'title' => 'Esperando Aprobación 📥',
                'message' => '⚠️ ' . $slowWaitingApprovalReports->count() . ' reportes de ' . $names . ' están esperando por su aprobación por más de ' . self::MAXIMUM_WAITING_HOURS_APPROVAL . ' horas. Revísalos en la sección de reportes.',
                'type' => 'WaitingApprovalReports'
            ];
        }

        if($slowWaitingRestitutedReports->count() > 0){
            $names = $getNames($slowWaitingRestitutedReports);

            $messages[] = [
                'title' => 'Esperando Reembolso 💸',
                'message' => '⚠️ ' . $slowWaitingRestitutedReports->count() . ' reportes aprobados de ' . $names . ' están esperando reembolso por más de ' . self::MAXIMUM_WAITING_HOURS_RESTITUTION . ' horas. Revísalos en la sección de reportes.',
                'type' => 'WaitingRestitutedReports'
            ];
        }

        if($slowWaitingFixRejectedReports->count() > 0){
            $names = $getNames($slowWaitingFixRejectedReports);

            $messages[] = [
                'title' => 'Esperando Corrección 🛠️',
                'message' => '⚠️ ' . $slowWaitingFixRejectedReports->count() . ' reportes rechazados ' . $names . ' están esperando corrección por más de ' . self::MAXIMUM_WAITING_HOURS_FIX_REJECTED . ' horas. Revísalos en la sección de reportes.',
                'type' => 'WaitingFixRejectedReports'
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
