<?php

namespace App\Support\EventLoop\Notifications;

use App\Models\User;
use App\Helpers\Toolbox;
use OneSignal;


class Notification{
    public string $title;
    public string $message;
    public array|null $data = [];

    public function __construct($title, $message, $data = []){
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;

        if (count($this->data) === 0){
            $this->data = null;
        }
    }


    public function sendNotificationToAdministrator()
    {
        $adminUser = User::where('username', 'admin')->first();

        OneSignal::sendNotificationToExternalUser(
            headings: $this->title,
            message: $this->message,
            userId: Toolbox::getOneSignalUserId($adminUser->id),
            data: $this->data
        );
    }

    public function sendNotificationToUserId($userId)
    {
        OneSignal::sendNotificationToExternalUser(
            headings: $this->title,
            message: $this->message,
            userId: Toolbox::getOneSignalUserId($userId),
            data: $this->data
        );
    }
}
