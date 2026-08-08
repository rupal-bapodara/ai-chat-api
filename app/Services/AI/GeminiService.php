<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService implements AIProviderInterface
{
    private string $apiKey;

    private string $model;

    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $this->model = env('GEMINI_MODEL', 'gemini-2.0-flash-lite');
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

        Log::info('Gemini API Response: ' . $response->body());

        if ($response->failed()) {
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
