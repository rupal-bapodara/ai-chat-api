<?php

namespace App\Models;

use App\Casts\Vector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEmbedding extends Model
{
    protected $fillable = [
        'chunk_id',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => Vector::class,
        ];
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'chunk_id');
    }
}
