<?php

namespace App\Services\AI;

use App\Exceptions\EmbeddingGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates real vector embeddings via the Hugging Face Inference API's
 * feature-extraction pipeline (default model: sentence-transformers/
 * all-MiniLM-L6-v2, 384 dimensions).
 *
 * Not related to HuggingFaceService in this same namespace, which is an
 * unrouted, reference-only chat-completion provider that hits a
 * different (and currently broken) HF endpoint. This class only handles
 * embeddings.
 */
class HuggingFaceEmbeddingService implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly ?string $apiToken,
        private readonly string $model,
        private readonly int $dimensions,
        private readonly string $endpoint,
    ) {}

    public function embed(string $text): array
    {
        $response = Http::withToken($this->apiToken)
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 2000, throw: false)
            ->post(rtrim($this->endpoint, '/') . '/' . $this->model . '/pipeline/feature-extraction', [
                'inputs' => $text,
                'options' => ['wait_for_model' => true],
            ]);

        if ($response->failed()) {
            Log::warning('Hugging Face embedding request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw new EmbeddingGenerationException(
                "Embedding request failed ({$response->status()}): " . Str::limit($response->body(), 300)
            );
        }

        return $this->normalizeVector($response->json());
    }

    public function getProviderName(): string
    {
        return 'Hugging Face (feature-extraction)';
    }

    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * HF's feature-extraction pipeline for a single sentence-transformers
     * input usually returns a flat float[] (already mean-pooled to the
     * sentence embedding). Some models/pipeline configs instead return a
     * token-level float[][] that needs mean pooling ourselves. Handle both.
     *
     * @return array<int, float>
     */
    private function normalizeVector(mixed $data): array
    {
        if (! is_array($data) || $data === []) {
            throw new EmbeddingGenerationException('Empty or malformed embedding response.');
        }

        if (is_array($data[0])) {
            // Token-level output: mean-pool across tokens.
            $length = count($data[0]);
            $sums = array_fill(0, $length, 0.0);

            foreach ($data as $tokenVector) {
                foreach ($tokenVector as $i => $value) {
                    $sums[$i] += (float) $value;
                }
            }

            $count = count($data);

            return array_map(fn ($sum) => $sum / $count, $sums);
        }

        return array_map(fn ($value) => (float) $value, $data);
    }
}
