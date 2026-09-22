<?php

namespace Tests\Unit;

use App\Exceptions\EmbeddingGenerationException;
use App\Services\AI\HuggingFaceEmbeddingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class HuggingFaceEmbeddingServiceTest extends TestCase
{
    private function makeService(): HuggingFaceEmbeddingService
    {
        return new HuggingFaceEmbeddingService(
            'fake-token',
            'sentence-transformers/all-MiniLM-L6-v2',
            4,
            'https://router.huggingface.co/hf-inference/models',
        );
    }

    public function test_it_returns_a_flat_vector_unmodified(): void
    {
        Http::fake([
            '*' => Http::response([0.1, 0.2, 0.3, 0.4]),
        ]);

        $vector = $this->makeService()->embed('hello world');

        $this->assertSame([0.1, 0.2, 0.3, 0.4], $vector);
    }

    public function test_it_mean_pools_token_level_output(): void
    {
        Http::fake([
            '*' => Http::response([
                [1.0, 2.0],
                [3.0, 4.0],
            ]),
        ]);

        $vector = $this->makeService()->embed('hello world');

        $this->assertSame([2.0, 3.0], $vector);
    }

    public function test_it_throws_on_failure(): void
    {
        Sleep::fake();

        Http::fake([
            '*' => Http::response(['error' => 'model loading'], 503),
        ]);

        $this->expectException(EmbeddingGenerationException::class);

        $this->makeService()->embed('hello world');
    }
}
