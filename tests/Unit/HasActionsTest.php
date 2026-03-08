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
