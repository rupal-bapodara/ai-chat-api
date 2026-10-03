<?php

return [
    'max_upload_size' => env('RAG_MAX_UPLOAD_SIZE', 2048),
    'chunk_size' => env('RAG_CHUNK_SIZE', 800),
    'top_k' => env('RAG_TOP_K', 8),
    'pdftotext_path' => env('PDFTOTEXT_PATH', '/usr/bin/pdftotext'),
    'pdfinfo_path' => env('PDFINFO_PATH', '/usr/bin/pdfinfo'),
    'documents_path' => env('RAG_DOCUMENTS_PATH', 'documents'),
];
