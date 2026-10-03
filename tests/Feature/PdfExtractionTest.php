<?php

namespace Tests\Feature;

use App\Repositories\Contracts\DocumentChunkRepositoryInterface;
use App\Services\AI\EmbeddingProviderInterface;
use App\Services\RAG\DocumentIndexingService;
use App\Services\RAG\PdfTextExtractor;
use Mockery;
use Tests\TestCase;

class PdfExtractionTest extends TestCase
{
    public function test_first_chunk_of_page_one_contains_the_header_text(): void
    {
        $pages = app(PdfTextExtractor::class)->extractPages(__DIR__ . '/../Fixtures/resume.pdf');

        $this->assertSame([1, 2], array_keys($pages));

        $service = new DocumentIndexingService(
            Mockery::mock(EmbeddingProviderInterface::class),
            Mockery::mock(DocumentChunkRepositoryInterface::class),
        );
        $firstChunk = $service->chunkText($pages[1], 800)[0];

        $this->assertStringContainsString('RUPAL BAPODARA', $firstChunk);
        $this->assertStringContainsString('rupal@example.com', $firstChunk);
        $this->assertStringContainsString('linkedin.com/in/rupal', $firstChunk);
        $this->assertStringStartsWith('RUPAL BAPODARA', $firstChunk);
    }

    public function test_short_lines_are_not_dropped_by_the_chunker(): void
    {
        $service = new DocumentIndexingService(
            Mockery::mock(EmbeddingProviderInterface::class),
            Mockery::mock(DocumentChunkRepositoryInterface::class),
        );

        $this->assertSame(['Porbandar, Gujarat'], $service->chunkText('Porbandar, Gujarat'));
    }
}
