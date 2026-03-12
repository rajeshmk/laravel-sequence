<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber;

use Illuminate\Support\ServiceProvider;

class RollNumberServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__ . '/../config/roll-number.php' => config_path('roll-number.php'),
            ], 'config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/roll-number.php', 'roll-number');
    }
}
