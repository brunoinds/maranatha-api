<?php


namespace App\Support\Assistants;

use App\Models\InstantMessage;
use App\Models\User;

class InstantMessagesConversationAssistant{
    public static function getConversationMessagesQuery(User $fromUser, User $toUser): \Illuminate\Database\Eloquent\Builder
    {
        $query = InstantMessage::where(function ($query) use ($fromUser, $toUser) {
            $query->where('from_user_id', $fromUser->id)
                ->where('to_user_id', $toUser->id);
        })->orWhere(function ($query) use ($fromUser, $toUser) {
            $query->where('from_user_id', $toUser->id)
                ->where('to_user_id', $fromUser->id);
        })->orderBy('id', 'desc');


        return $query;
    }
}
