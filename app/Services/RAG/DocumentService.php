<?php

namespace App\Services\RAG;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

class DocumentService
{
    public function __construct(protected DocumentIndexingService $indexingService)
    {
    }

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
        $rawText = $this->extractTextFromPdf($document->path);
        $pages = $this->splitIntoPages($rawText);
        $this->indexingService->storeParsedContent($document, $pages);

        foreach ($document->chunks()->get() as $chunk) {
            $this->indexingService->indexChunk($chunk);
        }
    }

    protected function extractTextFromPdf(string $path): string
    {
        $absolutePath = Storage::disk('local')->path($path);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException('Document file could not be found on disk.');
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($absolutePath);

        return $pdf->getText() ?: '';
    }

    protected function splitIntoPages(string $text): array
    {
        $pages = preg_split('/(?=\n{2,})/', trim($text));

        if ($pages === false) {
            return [$text];
        }

        $pages = array_values(array_filter(array_map('trim', $pages), fn ($page) => $page !== ''));

        return $pages !== [] ? $pages : [$text];
    }
}
