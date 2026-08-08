<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HuggingFaceService implements AIProviderInterface
{
    private string $apiToken;

    private string $model;

    private const API_ENDPOINT = 'https://huggingface.co/api/models/';

    public function __construct()
    {
        $this->apiToken = env('HF_API_TOKEN');
        $this->model = env('HF_MODEL', 'google/gemma-2-2b-it');
    }

    public function sendMessage(string $message): array
    {
        Log::info('Using Hugging Face Model: ' . $this->model);

        $response = Http::withToken($this->apiToken)
            ->timeout(120)
            ->post(
                self::API_ENDPOINT . $this->model,
                [
                    'inputs' => $message,
                ]
            );

        Log::info('Hugging Face API Response: ' . $response->body());

        if ($response->failed()) {
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
