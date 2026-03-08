<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('publishes config and migrations using actions install command', function (): void {
    $configPath = config_path('corepine-actions.php');
    $actionsMigrationPath = database_path('migrations/2026_03_08_000000_create_actions_table.php');
    $countsMigrationPath = database_path('migrations/2026_03_08_000100_create_action_counts_table.php');
    $actionTypePath = app_path('Enums/ActionType.php');

    File::delete($configPath);
    File::delete($actionsMigrationPath);
    File::delete($countsMigrationPath);
    File::delete($actionTypePath);

    $this->artisan('actions:install')
        ->assertExitCode(0);

    expect(File::exists($configPath))->toBeTrue();
    expect(File::exists($actionsMigrationPath))->toBeTrue();
    expect(File::exists($countsMigrationPath))->toBeTrue();
    expect(File::exists($actionTypePath))->toBeTrue();
});

it('supports migrate option on actions install command', function (): void {
    $this->artisan('actions:install', ['--migrate' => true])
        ->assertExitCode(0);
});
