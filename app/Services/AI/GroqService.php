<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService implements AIProviderInterface
{
    private string $apiKey;
    private string $model;
    private const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY');
        $this->model = env('GROQ_MODEL', 'mixtral-8x7b-32768');
    }

    public function sendMessage(string $message): array
    {
        return $this->createChatCompletion([
            [
                'role' => 'system',
                'content' => 'You are a helpful AI assistant.'
            ],
            [
                'role' => 'user',
                'content' => $message
            ]
        ]);
    }

    public function createChatCompletion(array $messages): array
    {
        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post(self::API_ENDPOINT, [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ]);

        Log::info('Groq API Response: ' . $response->body());

        if ($response->failed()) {
            return [
                'success' => false,
                'error' => $response->json('error.message') ?? 'Unknown error occurred'
            ];
        }

        return [
            'success' => true,
            'reply' => data_get($response->json(), 'choices.0.message.content', 'No response'),
            'raw_response' => $response->json(),
        ];
    }

    public function getProviderName(): string
    {
        return 'Groq';
    }
}
