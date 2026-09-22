<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\RAG\DocumentIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a real vector embedding for every not-yet-embedded chunk of a
 * document. Embedding calls are slow external API requests, so they run
 * here rather than inline in the upload request (see .ai/rules/jobs.md).
 */
class GenerateDocumentEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public readonly int $documentId) {}

    public function handle(DocumentIndexingService $indexingService): void
    {
        $document = Document::findOrFail($this->documentId);
        $document->update(['status' => 'processing']);

        // whereDoesntHave('embedding') makes this idempotent: a retry after
        // a partial failure only re-embeds chunks that don't have one yet.
        foreach ($document->chunks()->whereDoesntHave('embedding')->get() as $chunk) {
            $indexingService->indexChunk($chunk);
        }

        $document->update(['status' => 'indexed', 'indexed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        Document::whereKey($this->documentId)->update(['status' => 'failed']);

        Log::error("Embedding generation failed for document {$this->documentId}", [
            'exception' => $exception?->getMessage(),
        ]);
    }
}
