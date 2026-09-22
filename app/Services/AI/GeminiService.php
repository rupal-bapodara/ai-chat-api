<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiService implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model = config('services.gemini.model');
    }

    public function sendMessage(string $message): array
    {
        $response = Http::post(
            self::API_ENDPOINT . $this->model . ':generateContent?key=' . $this->apiKey,
            [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $message,
                            ],
                        ],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            Log::warning('Gemini API request failed', [
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
            'reply' => data_get($response->json(), 'candidates.0.content.parts.0.text', 'No response'),
            'raw_response' => $response->json(),
        ];
    }

    public function getProviderName(): string
    {
        return 'Google Gemini';
    }
}
