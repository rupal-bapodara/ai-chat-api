<?php

namespace App\Services\RAG;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentContent;
use App\Models\DocumentEmbedding;
use App\Repositories\Contracts\DocumentChunkRepositoryInterface;
use App\Services\AI\EmbeddingProviderInterface;

class DocumentIndexingService
{
    public function __construct(
        protected EmbeddingProviderInterface $embeddingProvider,
        protected DocumentChunkRepositoryInterface $chunkRepository,
    ) {}

    public function chunkText(string $text, int $chunkSize = 800): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text));

        if ($normalized === null || trim($normalized) === '') {
            return [];
        }

        $words = preg_split('/\s+/', $normalized);

        if ($words === false || count($words) === 0) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current . ' ' . $word);

            if (mb_strlen($candidate) <= $chunkSize) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = trim($current);
            }

            $current = $word;
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn ($chunk) => mb_strlen(trim($chunk)) > 0));
    }

    public function storeParsedContent(Document $document, array $pages): void
    {
        foreach ($pages as $pageNumber => $content) {
            DocumentContent::create([
                'document_id' => $document->id,
                'page_number' => (int) $pageNumber,
                'content' => trim($content),
            ]);
        }

        $chunkSize = (int) config('rag.chunk_size', 800);
        $chunks = [];
        foreach ($pages as $pageNumber => $content) {
            $pageChunks = $this->chunkText($content, $chunkSize);
            foreach ($pageChunks as $index => $chunk) {
                $chunks[] = [
                    'document_id' => $document->id,
                    'chunk_index' => count($chunks),
                    'page_number' => (int) $pageNumber,
                    'content' => $chunk,
                ];
            }
        }

        if ($chunks !== []) {
            DocumentChunk::insert($chunks);
        }

        // Embeddings aren't generated yet at this point -- that happens in
        // GenerateDocumentEmbeddingsJob, dispatched by DocumentService.
        // 'indexed' is only correct once every chunk has a real vector.
        $document->update(['status' => 'processing']);
    }

    /**
     * Generate and store a real embedding vector for a chunk. Idempotent:
     * safe to call again on retry (e.g. after a partial job failure)
     * without creating duplicate embedding rows for the same chunk.
     */
    public function indexChunk(DocumentChunk $chunk): void
    {
        $embedding = $this->embeddingProvider->embed($chunk->content);

        DocumentEmbedding::updateOrCreate(
            ['chunk_id' => $chunk->id],
            ['embedding' => $embedding]
        );
    }

    /**
     * Retrieve the top-K chunks of a document most relevant to a question,
     * ranked by real cosine similarity via pgvector (not keyword overlap).
     */
    public function buildRetrievalContext(string $question, int $documentId): array
    {
        $questionEmbedding = $this->embeddingProvider->embed($question);
        $topK = (int) config('rag.top_k', 5);

        $results = $this->chunkRepository->findNearestByDocument($documentId, $questionEmbedding, $topK);

        return array_map(fn (array $row) => [
            'chunk' => $row['chunk'],
            'score' => 1 - $row['distance'], // cosine distance -> similarity
        ], $results);
    }
}
