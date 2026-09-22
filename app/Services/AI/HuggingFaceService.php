<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Unrelated to HuggingFaceEmbeddingService in this same namespace, which
 * generates document/question embeddings via HF's feature-extraction
 * pipeline. This class is chat-completion only, unrouted, and kept for
 * reference (see ChatController::chatHF's docblock).
 */
class HuggingFaceService implements AIProviderInterface
{
    private string $apiToken;

    private string $model;

    private const API_ENDPOINT = 'https://huggingface.co/api/models/';

    public function __construct()
    {
        $this->apiToken = config('services.huggingface.token');
        $this->model = config('services.huggingface.chat_model');
    }

    public function sendMessage(string $message): array
    {
        $response = Http::withToken($this->apiToken)
            ->timeout(120)
            ->post(
                self::API_ENDPOINT . $this->model,
                [
                    'inputs' => $message,
                ]
            );

        if ($response->failed()) {
            Log::warning('Hugging Face chat API request failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message') ?? 'Unknown error occurred',
            ];
        }

        return [
            'success' => true,
            'reply' => $response->json(),
            'raw_response' => $response->json(),
        ];
    }

    public function getProviderName(): string
    {
        return 'Hugging Face';
    }
}
