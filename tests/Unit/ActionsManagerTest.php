<?php

declare(strict_types=1);

use Corepine\Actions\Facades\Actions;
use Corepine\Actions\Services\ActionService;
use Corepine\Actions\Services\ActionsManager;

it('exposes table helpers through the actions facade service', function (): void {
    config()->set('corepine-actions.table_prefix', 'cp_');

    expect(Actions::tablePrefix())->toBe('cp_');
    expect(Actions::formatTableName('actions'))->toBe('cp_actions');
    expect(Actions::app())->toBe(app(ActionsManager::class));
    expect(app('actions'))->toBe(app(ActionsManager::class));
    expect(Actions::app()->tablePrefix())->toBe('cp_');
});

it('builds action services through the actions facade', function (): void {
    expect(Actions::builder())->toBeInstanceOf(ActionService::class);
});
