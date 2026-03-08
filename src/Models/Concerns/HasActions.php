<?php

declare(strict_types=1);

namespace Corepine\Actions\Models\Concerns;

use BackedEnum;
use Corepine\Actions\Casts\ActionType;
use Corepine\Actions\Facades\Actions;
use Corepine\Actions\Models\Action;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use RuntimeException;

trait HasActions
{
    public static function bootHasActions(): void
    {
        static::deleted(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            Actions::for($model)->clear();
        });
    }

    public function actions(): MorphMany
    {
        /** @var class-string<Model> $actionModel */
        $actionModel = Actions::actionModel();

        return $this->asActionableModel()->morphMany($actionModel, 'actionable');
    }

    public function upvotes(): MorphMany
    {
        return $this->actions()->where('type', Actions::resolveActionType(ActionType::UPVOTE));
    }

    public function downvotes(): MorphMany
    {
        return $this->actions()->where('type', Actions::resolveActionType(ActionType::DOWNVOTE));
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
        return $this->actions()->where('type', Actions::resolveActionType(ActionType::REACTION));
    }

    public function toggleActionBy(BackedEnum|string $type, Model|Authenticatable|null $actor = null, BackedEnum|string|null $opposite = null): bool
    {
        return Actions::for($this->asActionableModel())->by($actor)->toggle($type, $opposite);
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

    public function removeActionBy(BackedEnum|string $type, Model|Authenticatable|null $actor = null): void
    {
        Actions::for($this->asActionableModel())->by($actor)->remove($type);
    }

    public function hasActionBy(BackedEnum|string $type, Model|Authenticatable|null $actor = null): bool
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

    public function actionCount(BackedEnum|string $type): int
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

    /**
     * @return Collection<int, array{reaction: string, count: int, formatted_count: string}>
     */
    public function reactionGroups(int $precision = 1, ?int $maxPrecision = 1): Collection
    {
        return Actions::for($this->asActionableModel())->reactionGroups($precision, $maxPrecision);
    }

    public function formattedActionCount(BackedEnum|string $type, ?int $count = null, int $precision = 1, ?int $maxPrecision = 1): string
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

    public function clearActionsAndCounts(): int
    {
        return Actions::for($this->asActionableModel())->clear();
    }

    public function deleteActionsAndCounts(): int
    {
        return $this->clearActionsAndCounts();
    }

    public function syncActionCount(BackedEnum|string $type): int
    {
        return Actions::for($this->asActionableModel())->syncCount($type);
    }

    /**
     * @param  array<int, BackedEnum|string>  $types
     * @return array<string, int>
     */
    public function syncAllActionCounts(array $types = []): array
    {
        return Actions::for($this->asActionableModel())->syncAllCounts($types);
    }

    protected function asActionableModel(): Model
    {
        if (! $this instanceof Model) {
            throw new RuntimeException('HasActions trait must be used on an Eloquent model.');
        }

        return $this;
    }
}
