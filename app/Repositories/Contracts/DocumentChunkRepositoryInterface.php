<?php

namespace App\Repositories\Contracts;

use App\Models\DocumentChunk;

interface DocumentChunkRepositoryInterface
{
    /**
     * Find the top-K chunks belonging to a document, nearest to the given
     * embedding by cosine distance (pgvector's `<=>` operator, ascending
     * = most similar first).
     *
     * @param  array<int, float>  $embedding
     * @return array<int, array{chunk: DocumentChunk, distance: float}>
     */
    public function findNearestByDocument(int $documentId, array $embedding, int $topK): array;
}
