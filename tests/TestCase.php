<?php

declare(strict_types=1);

namespace Corepine\Actions\Tests;

use Corepine\Actions\ActionsServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        tap($app['config'], function (Repository $config): void {
            $config->set('app.env', 'testing');
            $config->set('app.debug', true);
            $config->set('app.timezone', 'UTC');

            $config->set('database.default', 'testbench');
            $config->set('database.connections.testbench', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]);

            $config->set('auth.providers.users.model', \Workbench\App\Models\User::class);
        });

        Model::shouldBeStrict();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../workbench/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
