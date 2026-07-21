<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /**
     * Allow creation with no guarded attributes.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * A conversation contains many chat exchanges.
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class)->orderBy('created_at', 'asc');
    }
}
