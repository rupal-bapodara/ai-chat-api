<?php

return [
    'max_upload_size' => env('RAG_MAX_UPLOAD_SIZE', 2048),
    'chunk_size' => env('RAG_CHUNK_SIZE', 800),
    'top_k' => env('RAG_TOP_K', 5),
    'documents_path' => env('RAG_DOCUMENTS_PATH', 'documents'),
];
