<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
            'document_id' => 'nullable|integer|exists:documents,id',
        ];
    }
}
