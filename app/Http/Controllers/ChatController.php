<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AI\GroqService;
use App\Services\AI\GeminiService;
use App\Services\AI\HuggingFaceService;
use App\Models\Chat;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
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
            'message' => 'required|string'
        ]);

        $service = new GeminiService();
        $result = $service->sendMessage($request->message);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json([
            'reply' => $result['reply']
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
            'message' => 'required|string'
        ]);

        $service = new HuggingFaceService();
        $result = $service->sendMessage($request->message);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result['reply']);
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $service = new GroqService();
        $result = $service->sendMessage($request->message);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        Chat::create([
            'question' => $request->message,
            'answer' => $result['reply']
        ]);

        return response()->json([
            'success' => true,
            'reply' => $result['reply']
        ]);
    }
}