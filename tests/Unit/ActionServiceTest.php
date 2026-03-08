<?php

declare(strict_types=1);

use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Models\Action;
use Corepine\Actions\Models\ActionCount;
use Corepine\Actions\Services\ActionService;
use Corepine\Actions\Tests\Fixtures\Enums\CustomActionType;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('toggles upvote on and off and syncs counter', function (): void {
    $user = User::query()->create(['name' => 'Alice']);
    $post = Post::query()->create(['title' => 'Hello', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    expect($service->upvote())->toBeTrue();
    expect($service->has(ActionType::UPVOTE))->toBeTrue();
    expect($service->count(ActionType::UPVOTE))->toBe(1);

    expect($service->upvote())->toBeFalse();
    expect($service->has(ActionType::UPVOTE))->toBeFalse();
    expect($service->count(ActionType::UPVOTE))->toBe(0);

    expect(Action::query()->count())->toBe(0);

    $counter = ActionCount::query()
        ->forActionable($post)
        ->where('type', ActionType::UPVOTE->value)
        ->first();

    expect($counter)->not->toBeNull();
    expect((int) $counter->count)->toBe(0);
});

it('switches upvote to downvote and keeps only one vote', function (): void {
    $user = User::query()->create(['name' => 'Bob']);
    $post = Post::query()->create(['title' => 'World', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $service->upvote();
    expect($service->downvote())->toBeTrue();

    expect($service->has(ActionType::UPVOTE))->toBeFalse();
    expect($service->has(ActionType::DOWNVOTE))->toBeTrue();

    expect($service->count(ActionType::UPVOTE))->toBe(0);
    expect($service->count(ActionType::DOWNVOTE))->toBe(1);
    expect(Action::query()->count())->toBe(1);

    $only = Action::query()->first();
    expect($only?->type)->toBe(ActionType::DOWNVOTE);
});

it('creates updates and removes reaction with correct counts', function (): void {
    $user = User::query()->create(['name' => 'Chris']);
    $post = Post::query()->create(['title' => 'React', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $created = $service->reaction('fire');

    expect($created)->not->toBeNull();
    expect($created?->type)->toBe(ActionType::REACTION);
    expect($service->count(ActionType::REACTION))->toBe(1);

    $updated = $service->reaction('heart');
    expect($updated)->not->toBeNull();
    expect($updated?->id)->toBe($created?->id);
    expect($service->count(ActionType::REACTION))->toBe(1);

    $stored = Action::query()->first();
    expect($stored?->data)->toBe('heart');

    $removed = $service->reaction(null);
    expect($removed)->toBeNull();
    expect($service->count(ActionType::REACTION))->toBe(0);
    expect(Action::query()->count())->toBe(0);
});

it('removes explicit action type and decrements its counter', function (): void {
    $user = User::query()->create(['name' => 'Dina']);
    $post = Post::query()->create(['title' => 'Delete', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $service->upvote();
    expect($service->has(ActionType::UPVOTE))->toBeTrue();

    $service->remove(ActionType::UPVOTE);

    expect($service->has(ActionType::UPVOTE))->toBeFalse();
    expect($service->count(ActionType::UPVOTE))->toBe(0);
});

it('falls back to actions table when counter row is missing and syncs count', function (): void {
    $actorOne = User::query()->create(['name' => 'Ena']);
    $actorTwo = User::query()->create(['name' => 'Finn']);
    $owner = User::query()->create(['name' => 'Gus']);
    $post = Post::query()->create(['title' => 'Fallback', 'user_id' => $owner->getKey()]);

    Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $actorOne->getMorphClass(),
        'actor_id' => $actorOne->getKey(),
        'type' => ActionType::UPVOTE->value,
        'data' => null,
    ]);

    Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $actorTwo->getMorphClass(),
        'actor_id' => $actorTwo->getKey(),
        'type' => ActionType::UPVOTE->value,
        'data' => null,
    ]);

    ActionCount::query()->forActionable($post)->delete();

    $service = (new ActionService())->for($post)->by($actorOne);

    expect(ActionCount::query()->count())->toBe(0);
    expect($service->count(ActionType::UPVOTE))->toBe(2);

    expect($service->syncCount(ActionType::UPVOTE))->toBe(2);

    $counter = ActionCount::query()
        ->forActionable($post)
        ->where('type', ActionType::UPVOTE->value)
        ->first();

    expect($counter)->not->toBeNull();
    expect((int) $counter->count)->toBe(2);
});

it('syncs all counters and includes default zero buckets', function (): void {
    $owner = User::query()->create(['name' => 'Han']);
    $actor = User::query()->create(['name' => 'Ivy']);
    $post = Post::query()->create(['title' => 'Sync all', 'user_id' => $owner->getKey()]);

    Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'actor_id' => $actor->getKey(),
        'type' => ActionType::UPVOTE->value,
        'data' => null,
    ]);

    $service = (new ActionService())->for($post)->by($actor);
    $result = $service->syncAllCounts();

    expect($result)->toMatchArray([
        'upvote' => 1,
        'downvote' => 0,
        'reaction' => 0,
    ]);

    expect($service->count(ActionType::UPVOTE))->toBe(1);
    expect($service->count(ActionType::DOWNVOTE))->toBe(0);
    expect($service->count(ActionType::REACTION))->toBe(0);
});

it('uses authenticated user when by() receives null', function (): void {
    $user = User::query()->create(['name' => 'Jack']);
    $post = Post::query()->create(['title' => 'Auth', 'user_id' => $user->getKey()]);

    auth()->login($user);

    $service = (new ActionService())->for($post)->by();

    expect($service->upvote())->toBeTrue();
    expect($service->has(ActionType::UPVOTE))->toBeTrue();
});

it('throws when actionable is missing', function (): void {
    $service = new ActionService();

    $service->count(ActionType::UPVOTE);
})->throws(RuntimeException::class, 'for($actionable) must be set before querying actions.');

it('throws when actor is missing for mutating methods', function (): void {
    $owner = User::query()->create(['name' => 'Kate']);
    $post = Post::query()->create(['title' => 'Guard', 'user_id' => $owner->getKey()]);

    $service = (new ActionService())->for($post);

    $service->upvote();
})->throws(RuntimeException::class, 'by($actor) or authenticated user must be available.');

it('keeps like/dislike helper methods mapped to upvote/downvote', function (): void {
    $user = User::query()->create(['name' => 'Lia']);
    $post = Post::query()->create(['title' => 'Helpers', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    expect($service->like())->toBeTrue();
    expect($service->has(ActionType::UPVOTE))->toBeTrue();

    expect($service->dislike())->toBeTrue();
    expect($service->has(ActionType::DOWNVOTE))->toBeTrue();
    expect($service->count(ActionType::UPVOTE))->toBe(0);
});

it('rejects deprecated like/dislike type strings', function (): void {
    $user = User::query()->create(['name' => 'Mina']);
    $post = Post::query()->create(['title' => 'No aliases', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $service->count('like');
})->throws(RuntimeException::class, 'Deprecated action types [like, dislike] are not supported. Use [upvote, downvote].');

it('supports custom action enums and syncAllCounts custom zero buckets', function (): void {
    config()->set('corepine-actions.enums.action_type', CustomActionType::class);

    $user = User::query()->create(['name' => 'Mia']);
    $post = Post::query()->create(['title' => 'Custom action', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    expect($service->toggle(CustomActionType::BOOKMARK))->toBeTrue();
    expect($service->has(CustomActionType::BOOKMARK))->toBeTrue();
    expect($service->count(CustomActionType::BOOKMARK))->toBe(1);

    $stored = Action::query()->first();
    expect($stored?->type)->toBe(CustomActionType::BOOKMARK);

    expect($service->toggle(CustomActionType::BOOKMARK))->toBeFalse();
    expect($service->count(CustomActionType::BOOKMARK))->toBe(0);

    $synced = $service->syncAllCounts([CustomActionType::BOOKMARK]);
    expect($synced)->toMatchArray(['bookmark' => 0]);
});

it('groups reactions with formatted counts and supports legacy payloads', function (): void {
    $owner = User::query()->create(['name' => 'Owner']);
    $post = Post::query()->create(['title' => 'Reaction groups', 'user_id' => $owner->getKey()]);

    $users = collect(range(1, 9))->map(fn (int $i) => User::query()->create(['name' => 'R' . $i]));

    foreach ($users->take(6) as $actor) {
        (new ActionService())->for($post)->by($actor)->reaction('👋');
    }

    foreach ($users->slice(6, 2) as $actor) {
        (new ActionService())->for($post)->by($actor)->reaction('❤️');
    }

    Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $users->get(8)?->getMorphClass(),
        'actor_id' => $users->get(8)?->getKey(),
        'type' => ActionType::REACTION->value,
        'data' => ['value' => '👋'],
    ]);

    $groups = (new ActionService())->for($post)->reactionGroups();

    expect($groups->all())->toBe([
        ['reaction' => '👋', 'count' => 7, 'formatted_count' => '7'],
        ['reaction' => '❤️', 'count' => 2, 'formatted_count' => '2'],
    ]);
});

it('syncs counters from action model created/deleted events', function (): void {
    $owner = User::query()->create(['name' => 'Boot Owner']);
    $actor = User::query()->create(['name' => 'Boot Actor']);
    $post = Post::query()->create(['title' => 'Boot sync', 'user_id' => $owner->getKey()]);

    $action = Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'actor_id' => $actor->getKey(),
        'type' => ActionType::UPVOTE->value,
        'data' => null,
    ]);

    $counter = ActionCount::query()
        ->forActionable($post)
        ->where('type', ActionType::UPVOTE->value)
        ->first();

    expect($counter)->not->toBeNull();
    expect((int) $counter->count)->toBe(1);

    $action->delete();

    $counter = ActionCount::query()
        ->forActionable($post)
        ->where('type', ActionType::UPVOTE->value)
        ->first();

    expect($counter)->not->toBeNull();
    expect((int) $counter->count)->toBe(0);
});

it('clears all actions and related counters for an actionable', function (): void {
    $owner = User::query()->create(['name' => 'Clear Owner']);
    $actorOne = User::query()->create(['name' => 'Clear A']);
    $actorTwo = User::query()->create(['name' => 'Clear B']);
    $post = Post::query()->create(['title' => 'Clear service', 'user_id' => $owner->getKey()]);

    (new ActionService())->for($post)->by($actorOne)->upvote();
    (new ActionService())->for($post)->by($actorTwo)->reaction('👋');

    expect(Action::query()->forActionable($post)->count())->toBe(2);
    expect(ActionCount::query()->forActionable($post)->count())->toBe(2);

    $deleted = (new ActionService())->for($post)->clear();

    expect($deleted)->toBe(2);
    expect(Action::query()->forActionable($post)->count())->toBe(0);
    expect(ActionCount::query()->forActionable($post)->count())->toBe(0);
});
