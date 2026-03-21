<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Eznix86\AI\Memory\Commands\ExportMemories;
use Eznix86\AI\Memory\Commands\MemoryStats;
use Eznix86\AI\Memory\Commands\PruneMemories;
use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Support\ServiceProvider;

class MemoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/memory.php', 'memory');

        $this->app->singleton(MemoryManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/memory.php' => config_path('memory.php'),
            ], 'memory-config');

            $this->commands([
                PruneMemories::class,
                MemoryStats::class,
                ExportMemories::class,
            ]);
        }
    }
}
