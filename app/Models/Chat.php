<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_id',
        'question',
        'answer',
    ];

    /**
     * A chat belongs to a conversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
