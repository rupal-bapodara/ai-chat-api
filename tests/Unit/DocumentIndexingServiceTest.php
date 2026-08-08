<?php

namespace Tests\Unit;

use App\Services\RAG\DocumentIndexingService;
use PHPUnit\Framework\TestCase;

class DocumentIndexingServiceTest extends TestCase
{
    public function test_it_chunks_text_into_reasonable_segments(): void
    {
        $service = new DocumentIndexingService;

        $text = str_repeat('Laravel is a framework for building web applications. ', 20);

        $chunks = $service->chunkText($text, 150);

        $this->assertNotEmpty($chunks);
        $this->assertGreaterThan(1, count($chunks));
        $this->assertLessThanOrEqual(150, mb_strlen($chunks[0]));
    }

    public function test_it_matches_resume_sections_like_professional_summary(): void
    {
        $service = new DocumentIndexingService;

        $score = $service->scoreChunkAgainstQuestion('professional summary', 'Professional Summary: Experienced software engineer with a strong record in Laravel and API delivery.');

        $this->assertGreaterThan(0.3, $score);
    }
}
