<?php

declare(strict_types=1);

use Corepine\Actions\Facades\Actions;
use Corepine\Actions\Services\ActionService;
use Corepine\Actions\Services\ActionsManager;
use Corepine\Actions\Tests\Fixtures\Casts\CustomActionTypeCast;
use Corepine\Actions\Tests\Fixtures\Enums\CustomActionType;
use Corepine\Actions\Tests\Fixtures\Models\CustomAction;
use Corepine\Actions\Tests\Fixtures\Models\CustomActionCount;

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

it('resolves configured model classes from config', function (): void {
    config()->set('corepine-actions.models.action', CustomAction::class);
    config()->set('corepine-actions.models.action_count', CustomActionCount::class);

    expect(Actions::actionModel())->toBe(CustomAction::class);
    expect(Actions::actionCountModel())->toBe(CustomActionCount::class);
    expect(Actions::newActionModel())->toBeInstanceOf(CustomAction::class);
    expect(Actions::newActionCountModel())->toBeInstanceOf(CustomActionCount::class);
});

it('resolves built-in and custom action types without config aliases', function (): void {
    expect(Actions::defaultActionTypes())->toBe(['upvote', 'downvote', 'reaction']);
    expect(Actions::resolveActionType('upvote'))->toBe('upvote');
    expect(Actions::resolveActionType(CustomActionType::BOOKMARK))->toBe('bookmark');
});

it('resolves configurable action type cast class', function (): void {
    expect(Actions::actionTypeCast())->toBe(\Corepine\Actions\Casts\ActionTypeCast::class);

    config()->set('corepine-actions.action_type_cast', CustomActionTypeCast::class);

    expect(Actions::actionTypeCast())->toBe(CustomActionTypeCast::class);
});

it('rejects deprecated like/dislike type strings', function (): void {
    Actions::resolveActionType('like');
})->throws(RuntimeException::class, 'Deprecated action types [like, dislike] are not supported. Use [upvote, downvote].');

it('rejects empty action types', function (): void {
    Actions::resolveActionType('  ');
})->throws(RuntimeException::class, 'Action type cannot be empty.');

it('rejects invalid action type cast configuration', function (): void {
    $original = config('corepine-actions.action_type_cast');
    config()->set('corepine-actions.action_type_cast', 'stdClass');

    try {
        Actions::actionTypeCast();

        expect()->fail('Expected invalid action type cast config to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('corepine-actions.action_type_cast must be a valid Eloquent cast class.');
    } finally {
        config()->set('corepine-actions.action_type_cast', $original);
    }
});
