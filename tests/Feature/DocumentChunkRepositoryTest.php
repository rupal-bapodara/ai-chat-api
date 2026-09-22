<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentEmbedding;
use App\Repositories\Eloquent\DocumentChunkRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentChunkRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 384;

    private function vector(int $hotIndex, float $value = 1.0): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);
        $vector[$hotIndex] = $value;

        return $vector;
    }

    private function makeChunk(Document $document, int $index, array $embedding): DocumentChunk
    {
        $chunk = DocumentChunk::create([
            'document_id' => $document->id,
            'chunk_index' => $index,
            'page_number' => 1,
            'content' => "chunk-{$index}",
        ]);

        DocumentEmbedding::create([
            'chunk_id' => $chunk->id,
            'embedding' => $embedding,
        ]);

        return $chunk;
    }

    public function test_it_orders_chunks_by_cosine_distance_ascending(): void
    {
        $document = Document::create([
            'filename' => 'doc.pdf',
            'original_name' => 'doc.pdf',
            'file_size' => 100,
            'path' => 'documents/doc.pdf',
            'status' => 'indexed',
        ]);

        $query = $this->vector(0); // [1, 0, 0, ...]

        $identical = $this->makeChunk($document, 0, $this->vector(0));      // distance 0
        $orthogonal = $this->makeChunk($document, 1, $this->vector(1));     // distance 1
        $opposite = $this->makeChunk($document, 2, $this->vector(0, -1.0)); // distance 2

        $repository = new DocumentChunkRepository(new DocumentChunk);

        $results = $repository->findNearestByDocument($document->id, $query, 3);

        $this->assertCount(3, $results);
        $this->assertSame($identical->id, $results[0]['chunk']->id);
        $this->assertSame($orthogonal->id, $results[1]['chunk']->id);
        $this->assertSame($opposite->id, $results[2]['chunk']->id);

        $this->assertEqualsWithDelta(0.0, $results[0]['distance'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $results[1]['distance'], 0.0001);
        $this->assertEqualsWithDelta(2.0, $results[2]['distance'], 0.0001);
    }

    public function test_it_limits_to_top_k(): void
    {
        $document = Document::create([
            'filename' => 'doc.pdf',
            'original_name' => 'doc.pdf',
            'file_size' => 100,
            'path' => 'documents/doc.pdf',
            'status' => 'indexed',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->makeChunk($document, $i, $this->vector($i));
        }

        $repository = new DocumentChunkRepository(new DocumentChunk);

        $results = $repository->findNearestByDocument($document->id, $this->vector(0), 2);

        $this->assertCount(2, $results);
    }

    public function test_it_only_returns_chunks_for_the_given_document(): void
    {
        $documentA = Document::create([
            'filename' => 'a.pdf', 'original_name' => 'a.pdf', 'file_size' => 1, 'path' => 'a.pdf', 'status' => 'indexed',
        ]);
        $documentB = Document::create([
            'filename' => 'b.pdf', 'original_name' => 'b.pdf', 'file_size' => 1, 'path' => 'b.pdf', 'status' => 'indexed',
        ]);

        $this->makeChunk($documentA, 0, $this->vector(0));
        $this->makeChunk($documentB, 0, $this->vector(0));

        $repository = new DocumentChunkRepository(new DocumentChunk);

        $results = $repository->findNearestByDocument($documentA->id, $this->vector(0), 10);

        $this->assertCount(1, $results);
    }
}
