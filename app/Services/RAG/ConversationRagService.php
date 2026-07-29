<?php

namespace App\Services\RAG;

use App\Models\Chat;
use App\Models\Conversation;
use App\Models\Document;
use App\Services\AI\GroqService;
use Illuminate\Support\Collection;

class ConversationRagService
{
    public function __construct(
        protected GroqService $provider,
        protected DocumentIndexingService $indexingService,
    ) {
    }

    public function respond(string $message, ?int $conversationId = null, ?int $documentId = null): array
    {
        $conversation = $this->resolveConversation($conversationId);
        $context = [];

        if ($documentId) {
            $context = $this->buildContext($message, $documentId);
        }

        $systemPrompt = $this->buildSystemPrompt($context);
        $messages = $this->buildMessages($conversation, $message, $context);
        $result = $this->provider->createChatCompletion($messages);

        if (! $result['success']) {
            return $result;
        }

        Chat::create([
            'conversation_id' => $conversation->id,
            'document_id' => $documentId,
            'question' => $message,
            'answer' => $result['reply'],
        ]);

        return [
            'success' => true,
            'reply' => $result['reply'],
            'conversation_id' => $conversation->id,
            'document_id' => $documentId,
            'sources' => $context['sources'] ?? [],
        ];
    }

    protected function resolveConversation(?int $conversationId): Conversation
    {
        if ($conversationId) {
            return Conversation::find($conversationId) ?? Conversation::create();
        }

        return Conversation::create();
    }

    protected function buildContext(string $question, int $documentId): array
    {
        $retrieval = $this->indexingService->buildRetrievalContext($question, $documentId);
        $context = [];
        $sources = [];

        foreach ($retrieval as $item) {
            $chunk = $item['chunk'];
            $context[] = $chunk->content;
            $sources[] = [
                'document' => $chunk->document->original_name,
                'page' => $chunk->page_number,
            ];
        }

        return [
            'context' => $context,
            'sources' => $sources,
        ];
    }

    protected function buildSystemPrompt(array $context): string
    {
        $prompt = "You are an AI assistant. Answer using the provided document context whenever possible. If the answer is not clearly present, say that you could not find it in the uploaded document. Prefer concise, professional wording and preserve the document's meaning.";

        if (! empty($context['context'])) {
            $prompt .= "\n\nContext:\n" . implode("\n\n", $context['context']);
        }

        return $prompt;
    }

    protected function buildMessages(Conversation $conversation, string $message, array $context): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context),
            ],
        ];

        foreach ($conversation->chats()->latest('created_at')->take(6)->get()->reverse()->values() as $chat) {
            $messages[] = ['role' => 'user', 'content' => $chat->question];
            $messages[] = ['role' => 'assistant', 'content' => $chat->answer];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }
}