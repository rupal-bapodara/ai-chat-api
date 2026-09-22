<?php

namespace App\Services\AI;

use App\Exceptions\EmbeddingGenerationException;

interface EmbeddingProviderInterface
{
    /**
     * Turn a piece of text into a fixed-length embedding vector.
     *
     * @return array<int, float>
     *
     * @throws EmbeddingGenerationException
     */
    public function embed(string $text): array;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;

    /**
     * The number of dimensions every vector returned by embed() has.
     * Must match the `vector(N)` column size in document_embeddings.
     */
    public function getDimensions(): int;
}
