<?php

namespace App\Support\EventLoop\Notifications;

use App\Models\User;
use App\Helpers\Toolbox;
use OneSignal;
use Illuminate\Support\Collection;

class Notifications{
    public static function sendNotificationsToAdministrator(Collection $notications)
    {

        $notications = $notications->each(function($notification){
            return $notification->sendNotificationToAdministrator();
        });
    }
}
