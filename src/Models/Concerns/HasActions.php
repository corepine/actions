<?php

declare(strict_types=1);

namespace Corepine\Actions\Models\Concerns;

use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Facades\Actions;
use Corepine\Actions\Models\Action;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Number;
use RuntimeException;

trait HasActions
{
    public function actions(): MorphMany
    {
        return $this->asActionableModel()->morphMany(Action::class, 'actionable');
    }

    public function upvotes(): MorphMany
    {
        return $this->actions()->where('type', ActionType::UPVOTE->value);
    }

    public function downvotes(): MorphMany
    {
        return $this->actions()->where('type', ActionType::DOWNVOTE->value);
    }

    public function likes(): MorphMany
    {
        return $this->upvotes();
    }

    public function dislikes(): MorphMany
    {
        return $this->downvotes();
    }

    public function reactions(): MorphMany
    {
        return $this->actions()->where('type', ActionType::REACTION->value);
    }

    public function upvoteBy(Model|Authenticatable|null $actor = null): bool
    {
        return Actions::for($this->asActionableModel())->by($actor)->upvote();
    }

    public function downvoteBy(Model|Authenticatable|null $actor = null): bool
    {
        return Actions::for($this->asActionableModel())->by($actor)->downvote();
    }

    public function likeBy(Model|Authenticatable|null $actor = null): bool
    {
        return $this->upvoteBy($actor);
    }

    public function dislikeBy(Model|Authenticatable|null $actor = null): bool
    {
        return $this->downvoteBy($actor);
    }

    public function reactBy(Model|Authenticatable|null $actor, ?string $value): ?Action
    {
        return Actions::for($this->asActionableModel())->by($actor)->reaction($value);
    }

    public function removeActionBy(ActionType|string $type, Model|Authenticatable|null $actor = null): void
    {
        Actions::for($this->asActionableModel())->by($actor)->remove($type);
    }

    public function hasActionBy(ActionType|string $type, Model|Authenticatable|null $actor = null): bool
    {
        return Actions::for($this->asActionableModel())->by($actor)->has($type);
    }

    public function upvotedBy(Model|Authenticatable|null $actor = null): bool
    {
        return $this->hasActionBy(ActionType::UPVOTE, $actor);
    }

    public function downvotedBy(Model|Authenticatable|null $actor = null): bool
    {
        return $this->hasActionBy(ActionType::DOWNVOTE, $actor);
    }

    public function likedBy(Model|Authenticatable|null $actor = null): bool
    {
        return $this->upvotedBy($actor);
    }

    public function dislikedBy(Model|Authenticatable|null $actor = null): bool
    {
        return $this->downvotedBy($actor);
    }

    public function actionCount(ActionType|string $type): int
    {
        return Actions::for($this->asActionableModel())->count($type);
    }

    public function upvotesCount(): int
    {
        return $this->actionCount(ActionType::UPVOTE);
    }

    public function downvotesCount(): int
    {
        return $this->actionCount(ActionType::DOWNVOTE);
    }

    public function likesCount(): int
    {
        return $this->upvotesCount();
    }

    public function dislikesCount(): int
    {
        return $this->downvotesCount();
    }

    public function reactionsCount(): int
    {
        return $this->actionCount(ActionType::REACTION);
    }

    public function formattedActionCount(ActionType|string $type, ?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
    {
        $resolvedCount = $count ?? $this->actionCount($type);

        try {
            $formatted = Number::abbreviate($resolvedCount, $precision, $maxPrecision);
        } catch (RuntimeException) {
            return (string) $resolvedCount;
        }

        return is_string($formatted) ? $formatted : (string) $resolvedCount;
    }

    public function formattedUpvotesCount(?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
    {
        return $this->formattedActionCount(ActionType::UPVOTE, $count, $precision, $maxPrecision);
    }

    public function formattedDownvotesCount(?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
    {
        return $this->formattedActionCount(ActionType::DOWNVOTE, $count, $precision, $maxPrecision);
    }

    public function formattedLikesCount(?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
    {
        return $this->formattedUpvotesCount($count, $precision, $maxPrecision);
    }

    public function formattedDislikesCount(?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
    {
        return $this->formattedDownvotesCount($count, $precision, $maxPrecision);
    }

    public function formattedReactionsCount(?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
    {
        return $this->formattedActionCount(ActionType::REACTION, $count, $precision, $maxPrecision);
    }

    public function syncActionCount(ActionType|string $type): int
    {
        return Actions::for($this->asActionableModel())->syncCount($type);
    }

    public function syncAllActionCounts(): array
    {
        return Actions::for($this->asActionableModel())->syncAllCounts();
    }

    protected function asActionableModel(): Model
    {
        if (! $this instanceof Model) {
            throw new RuntimeException('HasActions trait must be used on an Eloquent model.');
        }

        return $this;
    }
}
