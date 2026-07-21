<?php

namespace App\Services\AI;

use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Support\Collection;

class ConversationService
{
    private const MAX_PREVIOUS_EXCHANGES = 12;

    public function __construct(private GroqService $provider)
    {
    }

    /**
     * Send user text along with the current conversation history.
     *
     * @param string $message
     * @param int|null $conversationId
     * @return array<string, mixed>
     */
    public function respond(string $message, ?int $conversationId = null): array
    {
        $conversation = $this->resolveConversation($conversationId);
        $recentChats = $this->loadRecentChats($conversation);
        $messages = $this->buildMessages($recentChats, $message);

        $result = $this->provider->createChatCompletion($messages);

        if (!$result['success']) {
            return $result;
        }

        Chat::create([
            'conversation_id' => $conversation->id,
            'question' => $message,
            'answer' => $result['reply'],
        ]);

        return [
            'success' => true,
            'reply' => $result['reply'],
            'conversation_id' => $conversation->id,
            'raw_response' => $result['raw_response'] ?? null,
        ];
    }

    protected function resolveConversation(?int $conversationId): Conversation
    {
        if ($conversationId) {
            return Conversation::find($conversationId) ?? Conversation::create();
        }

        return Conversation::create();
    }

    protected function loadRecentChats(Conversation $conversation): Collection
    {
        return $conversation->chats()
            ->latest('created_at')
            ->take(self::MAX_PREVIOUS_EXCHANGES)
            ->get()
            ->reverse()
            ->values();
    }

    protected function buildMessages(Collection $chats, string $currentMessage): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful AI assistant. Use the conversation history to answer the user accurately.'
            ]
        ];

        foreach ($chats as $chat) {
            $messages[] = [
                'role' => 'user',
                'content' => $chat->question
            ];
            $messages[] = [
                'role' => 'assistant',
                'content' => $chat->answer
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $currentMessage
        ];

        return $messages;
    }
}
