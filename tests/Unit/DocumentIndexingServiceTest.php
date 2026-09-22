<?php

namespace Tests\Unit;

use App\Repositories\Contracts\DocumentChunkRepositoryInterface;
use App\Services\AI\EmbeddingProviderInterface;
use App\Services\RAG\DocumentIndexingService;
use Mockery;
use PHPUnit\Framework\TestCase;

class DocumentIndexingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_chunks_text_into_reasonable_segments(): void
    {
        $service = new DocumentIndexingService(
            Mockery::mock(EmbeddingProviderInterface::class),
            Mockery::mock(DocumentChunkRepositoryInterface::class),
        );

        $text = str_repeat('Laravel is a framework for building web applications. ', 20);

        $chunks = $service->chunkText($text, 150);

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(1, count($chunks));
        $this->assertLessThanOrEqual(150, mb_strlen($chunks[0]));
    }
}
