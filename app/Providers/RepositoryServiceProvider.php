<?php

namespace App\Providers;

use App\Repositories\Contracts\DocumentChunkRepositoryInterface;
use App\Repositories\Eloquent\DocumentChunkRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository interface -> implementation bindings. New repositories
     * are registered here, not in ad-hoc service providers or inline
     * app()->bind() calls (see .ai/rules/architecture.md).
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DocumentChunkRepositoryInterface::class => DocumentChunkRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
