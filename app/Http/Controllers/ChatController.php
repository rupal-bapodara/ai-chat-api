<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use App\Http\Requests\DocumentUploadRequest;
use App\Models\Document;
use App\Services\AI\GeminiService;
use App\Services\AI\HuggingFaceService;
use App\Services\RAG\ConversationRagService;
use App\Services\RAG\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index()
    {
        $documents = Document::query()->latest()->get();

        return view('chat', compact('documents'));
    }

    public function upload(DocumentUploadRequest $request, DocumentService $documentService)
    {
        $documents = $documentService->storeUploadedDocuments($request->file('documents', []));

        return redirect()->back()->with('status', 'Uploaded ' . count($documents) . ' document(s) successfully.');
    }

    public function delete(Document $document)
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return redirect()->back()->with('status', 'Document deleted.');
    }

    /**
     * Google Gemini Integration
     *
     * Note:
     * Disabled by default because the free-tier quota was exhausted.
     * Kept for reference and future use.
     */
    public function chatGoogleGemini(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $service = new GeminiService;
        $result = $service->sendMessage($request->message);

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'reply' => $result['reply'],
        ]);
    }

    /**
     * Hugging Face Integration
     *
     * Note:
     * Initial implementation for learning purposes.
     * The legacy inference endpoint used in older tutorials is no longer
     * suitable in my environment, so Groq is used as the active provider.
     */
    public function chatHF(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $service = new HuggingFaceService;
        $result = $service->sendMessage($request->message);

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result['reply']);
    }

    public function chat(ChatRequest $request, ConversationRagService $conversationRagService)
    {
        $result = $conversationRagService->respond(
            $request->input('message'),
            $request->input('conversation_id'),
            $request->input('document_id')
        );

        Log::info('Chat response: ' . json_encode($result));

        if (! $result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'success' => true,
            'reply' => $result['reply'],
            'conversation_id' => $result['conversation_id'],
            'document_id' => $result['document_id'] ?? null,
            'sources' => $result['sources'] ?? [],
        ]);
    }
}