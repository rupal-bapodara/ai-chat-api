<?php

namespace App\Providers;

use App\Services\AI\EmbeddingProviderInterface;
use App\Services\AI\HuggingFaceEmbeddingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EmbeddingProviderInterface::class, function () {
            return new HuggingFaceEmbeddingService(
                config('embeddings.huggingface.token'),
                config('embeddings.huggingface.model'),
                config('embeddings.huggingface.dimensions'),
                config('embeddings.huggingface.endpoint'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
