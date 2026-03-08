<?php

declare(strict_types=1);

use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Models\Action;
use Corepine\Actions\Models\ActionCount;
use Corepine\Actions\Services\ActionService;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

it('toggles like on and off and syncs counter', function (): void {
    $user = User::query()->create(['name' => 'Alice']);
    $post = Post::query()->create(['title' => 'Hello', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    expect($service->like())->toBeTrue();
    expect($service->has('like'))->toBeTrue();
    expect($service->count('like'))->toBe(1);

    expect($service->like())->toBeFalse();
    expect($service->has('like'))->toBeFalse();
    expect($service->count('like'))->toBe(0);

    expect(Action::query()->count())->toBe(0);

    $counter = ActionCount::query()
        ->forActionable($post)
        ->where('type', ActionType::LIKE->value)
        ->first();

    expect($counter)->not->toBeNull();
    expect((int) $counter->count)->toBe(0);
});

it('switches like to dislike and keeps only one vote', function (): void {
    $user = User::query()->create(['name' => 'Bob']);
    $post = Post::query()->create(['title' => 'World', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $service->like();
    expect($service->dislike())->toBeTrue();

    expect($service->has(ActionType::LIKE))->toBeFalse();
    expect($service->has(ActionType::DISLIKE))->toBeTrue();

    expect($service->count('like'))->toBe(0);
    expect($service->count('dislike'))->toBe(1);
    expect(Action::query()->count())->toBe(1);

    $only = Action::query()->first();
    expect($only?->type)->toBe(ActionType::DISLIKE);
});

it('creates updates and removes reaction with correct counts', function (): void {
    $user = User::query()->create(['name' => 'Chris']);
    $post = Post::query()->create(['title' => 'React', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $created = $service->reaction('fire');

    expect($created)->not->toBeNull();
    expect($created?->type)->toBe(ActionType::REACTION);
    expect($service->count('reaction'))->toBe(1);

    $updated = $service->reaction('heart');
    expect($updated)->not->toBeNull();
    expect($updated?->id)->toBe($created?->id);
    expect($service->count('reaction'))->toBe(1);

    $stored = Action::query()->first();
    expect($stored?->data)->toBe('heart');

    $removed = $service->reaction(null);
    expect($removed)->toBeNull();
    expect($service->count('reaction'))->toBe(0);
    expect(Action::query()->count())->toBe(0);
});

it('removes explicit action type and decrements its counter', function (): void {
    $user = User::query()->create(['name' => 'Dina']);
    $post = Post::query()->create(['title' => 'Delete', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    $service->like();
    expect($service->has('like'))->toBeTrue();

    $service->remove('like');

    expect($service->has('like'))->toBeFalse();
    expect($service->count('like'))->toBe(0);
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
        'type' => ActionType::LIKE->value,
        'data' => null,
    ]);

    Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $actorTwo->getMorphClass(),
        'actor_id' => $actorTwo->getKey(),
        'type' => ActionType::LIKE->value,
        'data' => null,
    ]);

    $service = (new ActionService())->for($post)->by($actorOne);

    expect(ActionCount::query()->count())->toBe(0);
    expect($service->count('like'))->toBe(2);

    expect($service->syncCount('like'))->toBe(2);

    $counter = ActionCount::query()
        ->forActionable($post)
        ->where('type', ActionType::LIKE->value)
        ->first();

    expect($counter)->not->toBeNull();
    expect((int) $counter->count)->toBe(2);
});

it('syncs all counters and includes zero buckets', function (): void {
    $owner = User::query()->create(['name' => 'Han']);
    $actor = User::query()->create(['name' => 'Ivy']);
    $post = Post::query()->create(['title' => 'Sync all', 'user_id' => $owner->getKey()]);

    Action::query()->create([
        'actionable_type' => $post->getMorphClass(),
        'actionable_id' => $post->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'actor_id' => $actor->getKey(),
        'type' => ActionType::LIKE->value,
        'data' => null,
    ]);

    $service = (new ActionService())->for($post)->by($actor);
    $result = $service->syncAllCounts();

    expect($result)->toMatchArray([
        'like' => 1,
        'dislike' => 0,
        'reaction' => 0,
    ]);

    expect($service->count('like'))->toBe(1);
    expect($service->count('dislike'))->toBe(0);
    expect($service->count('reaction'))->toBe(0);
});

it('uses authenticated user when by() receives null', function (): void {
    $user = User::query()->create(['name' => 'Jack']);
    $post = Post::query()->create(['title' => 'Auth', 'user_id' => $user->getKey()]);

    auth()->login($user);

    $service = (new ActionService())->for($post)->by();

    expect($service->like())->toBeTrue();
    expect($service->has('like'))->toBeTrue();
});

it('throws when actionable is missing', function (): void {
    $service = new ActionService();

    $service->count('like');
})->throws(RuntimeException::class, 'for($actionable) must be set before querying actions.');

it('throws when actor is missing for mutating methods', function (): void {
    $owner = User::query()->create(['name' => 'Kate']);
    $post = Post::query()->create(['title' => 'Guard', 'user_id' => $owner->getKey()]);

    $service = (new ActionService())->for($post);

    $service->like();
})->throws(RuntimeException::class, 'by($actor) or authenticated user must be available.');

it('supports upvote/downvote aliases alongside like/dislike', function (): void {
    $user = User::query()->create(['name' => 'Lia']);
    $post = Post::query()->create(['title' => 'Aliases', 'user_id' => $user->getKey()]);

    $service = (new ActionService())->for($post)->by($user);

    expect($service->upvote())->toBeTrue();
    expect($service->has('upvote'))->toBeTrue();
    expect($service->has('like'))->toBeTrue();
    expect($service->count('upvote'))->toBe(1);

    expect($service->downvote())->toBeTrue();
    expect($service->has('downvote'))->toBeTrue();
    expect($service->has('dislike'))->toBeTrue();
    expect($service->count('downvote'))->toBe(1);
    expect($service->count('upvote'))->toBe(0);
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
