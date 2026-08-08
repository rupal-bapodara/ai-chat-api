<?php

namespace App\Services\RAG;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentContent;
use App\Models\DocumentEmbedding;

class DocumentIndexingService
{
    protected array $stopWords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'from',
        'give', 'give me', 'i', 'in', 'is', 'it', 'me', 'of', 'on', 'or', 'our',
        'the', 'their', 'this', 'to', 'what', 'with', 'you', 'your',
    ];

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

        $chunks = [];
        foreach ($pages as $pageNumber => $content) {
            $pageChunks = $this->chunkText($content);
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

        $document->update(['status' => 'indexed', 'indexed_at' => now()]);
    }

    public function createEmbedding(string $text): array
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === '') {
            return [];
        }

        $tokens = $this->tokenize($normalized);
        $counts = [];

        foreach ($tokens as $token) {
            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }

        return $counts;
    }

    public function indexChunk(DocumentChunk $chunk): void
    {
        $embedding = $this->createEmbedding($chunk->content);

        DocumentEmbedding::create([
            'chunk_id' => $chunk->id,
            'embedding' => json_encode($embedding),
        ]);
    }

    public function buildRetrievalContext(string $question, int $documentId): array
    {
        $document = Document::findOrFail($documentId);
        $chunks = $document->chunks()->with('embedding')->get();

        $questionEmbedding = $this->createEmbedding($question);
        $scored = [];

        foreach ($chunks as $chunk) {
            $embedding = $chunk->embedding ? json_decode($chunk->embedding->embedding, true, 512, JSON_THROW_ON_ERROR) : [];
            $score = $this->scoreChunkAgainstQuestion($question, $chunk->content);
            $scored[] = [
                'chunk' => $chunk,
                'score' => $score,
            ];
        }

        usort($scored, fn ($left, $right) => $right['score'] <=> $left['score']);

        return array_slice($scored, 0, 5);
    }

    public function scoreChunkAgainstQuestion(string $question, string $chunk): float
    {
        $questionTokens = $this->tokenize($this->normalizeText($question));
        $chunkTokens = $this->tokenize($this->normalizeText($chunk));

        if ($questionTokens === [] || $chunkTokens === []) {
            return 0.0;
        }

        $questionSet = array_fill_keys($questionTokens, true);
        $overlap = 0;

        foreach ($chunkTokens as $token) {
            if (isset($questionSet[$token])) {
                $overlap++;
            }
        }

        $phraseBoost = 0.0;
        $normalizedQuestion = implode(' ', $questionTokens);
        $normalizedChunk = implode(' ', $chunkTokens);

        if ($normalizedQuestion !== '' && str_contains($normalizedChunk, $normalizedQuestion)) {
            $phraseBoost = 0.4;
        }

        return min(1.0, (($overlap / max(1, count($questionTokens))) * 0.8) + $phraseBoost);
    }

    public function cosineSimilarity(array $a, array $b): float
    {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($keys as $key) {
            $valueA = $a[$key] ?? 0;
            $valueB = $b[$key] ?? 0;
            $dot += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    protected function normalizeText(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? ''));
    }

    protected function tokenize(string $text): array
    {
        $tokens = preg_split('/\s+/', $this->normalizeText($text));

        if ($tokens === false) {
            return [];
        }

        return array_values(array_filter($tokens, fn (string $token) => $token !== '' && ! in_array($token, $this->stopWords, true)));
    }
}
