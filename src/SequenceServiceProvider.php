<?php

declare(strict_types=1);

namespace Hatchyu\Sequence;

use Illuminate\Support\ServiceProvider;
use Override;

class SequenceServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__ . '/../config/sequence.php' => config_path('sequence.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../database/migrations/2023_01_01_100000_create_sequences.php' => database_path('migrations/2023_01_01_100000_create_sequences.php'),
            ], 'sequence-migrations');
        }
    }

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sequence.php', 'sequence');
    }
}
