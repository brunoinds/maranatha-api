<?php

namespace App\Support\EventLoop\Notifications;

use App\Models\User;
use App\Helpers\Toolbox;
use OneSignal;


class Notification{
    public string $title;
    public string $message;
    public array|null $data = [];
    public User|null $userTarget = null;

    public function __construct($title, $message, $data = [], $userTarget = null){
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->userTarget = $userTarget;

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

    public function sendToUserTarget() :void
    {
        if ($this->userTarget === null){
            return;
        }

        $this->sendNotificationToUserId($this->userTarget->id);
    }
}
