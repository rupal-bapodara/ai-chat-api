<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'page_number',
        'content',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(DocumentEmbedding::class, 'chunk_id');
    }
}
