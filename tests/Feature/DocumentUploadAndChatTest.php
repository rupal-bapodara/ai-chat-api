<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadAndChatTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEmbeddingVector(): array
    {
        return array_fill(0, 384, 0.01);
    }

    private function fakeHttp(): void
    {
        Http::fake([
            'router.huggingface.co/*' => Http::response($this->fakeEmbeddingVector()),
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'PostgreSQL with pgvector powers the vector search here.']],
                ],
            ]),
        ]);
    }

    public function test_uploading_a_pdf_indexes_it_and_chat_answers_using_it(): void
    {
        Storage::fake('local');
        $this->fakeHttp();

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf');
        copy(__DIR__ . '/../Fixtures/sample.pdf', $tempPath);

        $file = new UploadedFile(
            $tempPath,
            'sample.pdf',
            'application/pdf',
            null,
            true
        );

        $uploadResponse = $this->post('/upload', ['documents' => [$file]]);
        $uploadResponse->assertRedirect();

        $document = Document::firstOrFail();
        $this->assertSame('indexed', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->indexed_at);
        $this->assertGreaterThan(0, $document->chunks()->count());
        $this->assertGreaterThan(0, $document->chunks()->has('embedding')->count());

        $chatResponse = $this->postJson('/chat', [
            'message' => 'What does the system use for vector search?',
            'document_id' => $document->id,
        ]);

        $chatResponse->assertOk();
        $chatResponse->assertJsonStructure(['success', 'reply', 'conversation_id', 'document_id', 'sources']);
        $chatResponse->assertJsonPath('success', true);
        $chatResponse->assertJsonPath('document_id', $document->id);
        $this->assertNotEmpty($chatResponse->json('sources'));
    }

    public function test_chat_returns_502_when_the_provider_fails(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'upstream down']], 500),
        ]);

        $response = $this->postJson('/chat', ['message' => 'hello']);

        $response->assertStatus(502);
    }
}
