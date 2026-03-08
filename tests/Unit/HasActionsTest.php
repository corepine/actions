<?php

declare(strict_types=1);

use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Models\Action;
use Workbench\App\Models\ActionablePost;
use Workbench\App\Models\User;

it('toggles upvotes/downvotes through the has actions concern', function (): void {
    $user = User::query()->create(['name' => 'Amy']);
    $post = ActionablePost::query()->create(['title' => 'Trait', 'user_id' => $user->getKey()]);

    expect($post->upvoteBy($user))->toBeTrue();
    expect($post->upvotedBy($user))->toBeTrue();
    expect($post->likedBy($user))->toBeTrue();
    expect($post->upvotesCount())->toBe(1);
    expect($post->likesCount())->toBe(1);

    expect($post->downvoteBy($user))->toBeTrue();
    expect($post->downvotedBy($user))->toBeTrue();
    expect($post->dislikedBy($user))->toBeTrue();
    expect($post->upvotesCount())->toBe(0);
    expect($post->downvotesCount())->toBe(1);
    expect($post->dislikesCount())->toBe(1);

    expect($post->actions()->count())->toBe(1);
    expect($post->downvotes()->count())->toBe(1);
    expect($post->dislikes()->count())->toBe(1);

    $only = Action::query()->first();
    expect($only?->type)->toBe(ActionType::DOWNVOTE);
});

it('creates and removes reactions through the has actions concern', function (): void {
    $user = User::query()->create(['name' => 'Ben']);
    $post = ActionablePost::query()->create(['title' => 'React', 'user_id' => $user->getKey()]);

    $created = $post->reactBy($user, 'fire');

    expect($created)->not->toBeNull();
    expect($post->reactions()->count())->toBe(1);
    expect($post->reactionsCount())->toBe(1);

    $removed = $post->reactBy($user, null);

    expect($removed)->toBeNull();
    expect($post->reactions()->count())->toBe(0);
    expect($post->reactionsCount())->toBe(0);
});

it('formats action counts and allows passing explicit counts', function (): void {
    $user = User::query()->create(['name' => 'Nia']);
    $post = ActionablePost::query()->create(['title' => 'Format', 'user_id' => $user->getKey()]);

    $post->upvoteBy($user);

    expect($post->formattedUpvotesCount())->toBe('1');
    expect($post->formattedLikesCount())->toBe('1');

    expect($post->formattedActionCount(ActionType::UPVOTE, 2500))->toBe('2.5K');
    expect($post->formattedUpvotesCount(2500))->toBe('2.5K');
    expect($post->formattedLikesCount(2500))->toBe('2.5K');
    expect($post->formattedActionCount(ActionType::DOWNVOTE, 2500))->toBe('2.5K');
});

it('returns grouped reactions with render-ready formatted counts', function (): void {
    $owner = User::query()->create(['name' => 'Owner-2']);
    $post = ActionablePost::query()->create(['title' => 'Group render', 'user_id' => $owner->getKey()]);

    $users = collect(range(1, 8))->map(fn (int $i) => User::query()->create(['name' => 'G' . $i]));

    foreach ($users->take(6) as $actor) {
        $post->reactBy($actor, '👋');
    }

    foreach ($users->slice(6, 2) as $actor) {
        $post->reactBy($actor, '❤️');
    }

    $groups = $post->reactionGroups();

    expect($groups->all())->toBe([
        ['reaction' => '👋', 'count' => 6, 'formatted_count' => '6'],
        ['reaction' => '❤️', 'count' => 2, 'formatted_count' => '2'],
    ]);

    expect($post->formattedReactionsCount(2500))->toBe('2.5K');
});

it('clears actions and counters through concern helpers', function (): void {
    $owner = User::query()->create(['name' => 'Cleanup owner']);
    $actorOne = User::query()->create(['name' => 'Cleanup A']);
    $actorTwo = User::query()->create(['name' => 'Cleanup B']);
    $post = ActionablePost::query()->create(['title' => 'Cleanup trait', 'user_id' => $owner->getKey()]);

    $post->upvoteBy($actorOne);
    $post->reactBy($actorTwo, '❤️');

    expect(Action::query()->forActionable($post)->count())->toBe(2);
    expect(\Corepine\Actions\Models\ActionCount::query()->forActionable($post)->count())->toBe(2);

    $deleted = $post->clearActionsAndCounts();

    expect($deleted)->toBe(2);
    expect(Action::query()->forActionable($post)->count())->toBe(0);
    expect(\Corepine\Actions\Models\ActionCount::query()->forActionable($post)->count())->toBe(0);

    expect($post->deleteActionsAndCounts())->toBe(0);
});

it('auto-cleans actions and counters when actionable model is deleted', function (): void {
    $owner = User::query()->create(['name' => 'Cascade owner']);
    $actor = User::query()->create(['name' => 'Cascade actor']);
    $post = ActionablePost::query()->create(['title' => 'Cascade delete', 'user_id' => $owner->getKey()]);

    $post->upvoteBy($actor);
    $post->reactBy($actor, '🔥');

    expect(Action::query()->forActionable($post)->count())->toBe(2);
    expect(\Corepine\Actions\Models\ActionCount::query()->forActionable($post)->count())->toBe(2);

    $post->delete();

    expect(Action::query()->forActionable($post)->count())->toBe(0);
    expect(\Corepine\Actions\Models\ActionCount::query()->forActionable($post)->count())->toBe(0);
});
