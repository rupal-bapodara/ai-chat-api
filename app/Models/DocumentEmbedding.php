<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEmbedding extends Model
{
    protected $fillable = [
        'chunk_id',
        'embedding',
    ];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'chunk_id');
    }
}
