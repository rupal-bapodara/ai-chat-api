<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'documents' => 'required|array',
            'documents.*' => 'required|file|mimes:pdf|max:' . (config('rag.max_upload_size', 2048)),
        ];
    }
}
