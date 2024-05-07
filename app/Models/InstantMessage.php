<?php

namespace App\Models;

use App\Helpers\Enums\InstantMessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstantMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'body',
        'from_user_id',
        'to_user_id',
        'replies_to',
        'received_at',
        'read_at',
        'played_at',
        'type',
        'attachment',
        'metadata',
        'sent_at',
        'status'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'from_user_id' => 'integer',
        'to_user_id' => 'integer',
        'replies_to' => 'integer',
        'received_at' => 'datetime',
        'read_at' => 'datetime',
        'played_at' => 'datetime',
        'type' => InstantMessageType::class,
        'attachment' => 'array',
        'metadata' => 'array',
    ];



    public function sender()
    {
        return $this->belongsTo(User::class, 'from_user_id', 'id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'to_user_id', 'id');
    }

    public function repliesTo()
    {
        return $this->belongsTo(InstantMessage::class, 'replies_to', 'id');
    }
}
