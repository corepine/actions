<?php

declare(strict_types=1);

namespace Corepine\Actions;

use Corepine\Actions\Services\ActionsManager;
use Illuminate\Support\ServiceProvider;

class ActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/corepine-actions.php', 'corepine-actions');

        $this->app->singleton(ActionsManager::class, static fn (): ActionsManager => new ActionsManager());
        $this->app->alias(ActionsManager::class, 'actions');
        $this->app->alias(ActionsManager::class, 'corepine-actions');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/corepine-actions.php' => config_path('corepine-actions.php'),
        ], 'corepine-actions-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'corepine-actions-migrations');
    }
}
