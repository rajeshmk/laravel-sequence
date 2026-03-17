<?php

declare(strict_types=1);

namespace Hatchyu\RollNumber;

use Illuminate\Support\ServiceProvider;
use Override;

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

            $this->publishes([
                __DIR__ . '/../database/migrations/2023_01_01_100000_create_roll_numbers.php' => database_path('migrations/2023_01_01_100000_create_roll_numbers.php'),
            ], 'roll-number-migrations');
        }
    }

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/roll-number.php', 'roll-number');
    }
}
