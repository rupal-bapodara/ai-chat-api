<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The provider used to turn document chunks and questions into vectors
    | for similarity search. Bound to App\Services\AI\EmbeddingProviderInterface
    | in App\Providers\AppServiceProvider.
    |
    */

    'provider' => env('EMBEDDING_PROVIDER', 'huggingface'),

    'huggingface' => [
        'token' => env('HF_API_TOKEN'),
        'model' => env('EMBEDDING_MODEL', 'sentence-transformers/all-MiniLM-L6-v2'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 384),
        'endpoint' => env('HF_INFERENCE_ENDPOINT', 'https://router.huggingface.co/hf-inference/models'),
    ],

];
