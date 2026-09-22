<?php

namespace App\Repositories\Eloquent;

use App\Models\DocumentChunk;
use App\Repositories\Contracts\DocumentChunkRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the pgvector similarity query for document chunks. This is
 * the kind of non-trivial, reused query logic that justifies a Repository
 * per .ai/rules/architecture.md -- ordering by vector distance can't be
 * expressed through plain Eloquent and needs raw SQL against the ANN
 * index, so it belongs behind an interface rather than inline in a
 * Service.
 */
class DocumentChunkRepository extends BaseRepository implements DocumentChunkRepositoryInterface
{
    public function __construct(DocumentChunk $model)
    {
        parent::__construct($model);
    }

    public function findNearestByDocument(int $documentId, array $embedding, int $topK): array
    {
        $vectorLiteral = '[' . implode(',', $embedding) . ']';

        // Order in Postgres, via the HNSW index, rather than loading every
        // chunk into PHP and scoring it there -- that's the whole point of
        // using pgvector. The explicit ::vector cast is required because
        // PDO binds parameters as untyped text and Postgres can't resolve
        // the <=> operator between vector and unknown without it.
        $rows = DB::table('document_chunks')
            ->join('document_embeddings', 'document_embeddings.chunk_id', '=', 'document_chunks.id')
            ->where('document_chunks.document_id', $documentId)
            ->select('document_chunks.id as chunk_id')
            ->selectRaw('document_embeddings.embedding <=> ?::vector as distance', [$vectorLiteral])
            ->orderByRaw('document_embeddings.embedding <=> ?::vector', [$vectorLiteral])
            ->limit($topK)
            ->get();

        $chunks = DocumentChunk::with('document')
            ->whereIn('id', $rows->pluck('chunk_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn ($row) => [
                'chunk' => $chunks->get($row->chunk_id),
                'distance' => (float) $row->distance,
            ])
            ->filter(fn (array $row) => $row['chunk'] !== null)
            ->values()
            ->all();
    }
}
