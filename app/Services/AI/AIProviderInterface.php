<?php

namespace App\Services\AI;

use Illuminate\Http\Client\Response;

interface AIProviderInterface
{
    /**
     * Send a message to the AI provider and get a response
     */
    public function sendMessage(string $message): array;

    /**
     * Get the provider name
     */
    public function getProviderName(): string;
}
