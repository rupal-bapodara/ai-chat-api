<?php

namespace App\Services\RAG;

use App\Jobs\GenerateDocumentEmbeddingsJob;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function __construct(
        protected DocumentIndexingService $indexingService,
        protected PdfTextExtractor $extractor,
    ) {}

    public function storeUploadedDocuments(array $files, ?int $userId = null): array
    {
        $stored = [];

        foreach ($files as $file) {
            $stored[] = $this->storeSingleDocument($file, $userId);
        }

        return $stored;
    }

    public function storeSingleDocument(UploadedFile $file, ?int $userId = null): Document
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs(config('rag.documents_path'), $filename, 'local');

        $document = Document::create([
            'user_id' => $userId,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'path' => $path,
            'status' => 'processing',
        ]);

        $this->indexDocument($document);

        return $document;
    }

    public function indexDocument(Document $document): void
    {
        try {
            $pages = $this->extractor->extractPages(Storage::disk('local')->path($document->path));

            if ($pages === []) {
                throw new \RuntimeException('No extractable text found in the PDF.');
            }

            Log::debug('Extracted pages from document', ['document_id' => $document->id, 'page_count' => count($pages)]);

            $this->indexingService->storeParsedContent($document, $pages);
        } catch (\Throwable $e) {
            $document->update(['status' => 'failed']);
            Log::error("Text extraction failed for document {$document->id}", ['exception' => $e->getMessage()]);

            return;
        }

        // Embedding generation is slow external API work -- it runs in a
        // queued job, not inline in the upload request (.ai/rules/jobs.md).
        GenerateDocumentEmbeddingsJob::dispatch($document->id);
    }
}
