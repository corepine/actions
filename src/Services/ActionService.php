<?php

declare(strict_types=1);

namespace Corepine\Actions\Services;

use BackedEnum;
use Corepine\Actions\Casts\ActionType;
use Corepine\Actions\Facades\Actions;
use Corepine\Actions\Models\Action;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use RuntimeException;

class ActionService
{
    protected ?Model $actionable = null;

    protected ?Model $actor = null;

    public function for(Model $actionable): static
    {
        $this->actionable = $actionable;

        return $this;
    }

    public function on(Model $actionable): static
    {
        return $this->for($actionable);
    }

    public function by(Model|Authenticatable|null $actor = null): static
    {
        if ($actor === null) {
            $actor = auth()->user();
        }

        $this->actor = $actor instanceof Model ? $actor : null;

        return $this;
    }

    public function actingAs(Model|Authenticatable|null $actor = null): static
    {
        return $this->by($actor);
    }

    public function toggle(BackedEnum|string $type, BackedEnum|string|null $opposite = null): bool
    {
        $this->guardActionContext();

        $type = $this->normalizeType($type);
        $opposite = $opposite !== null ? $this->normalizeType($opposite) : null;

        if ($opposite === $type) {
            $opposite = null;
        }

        return DB::transaction(function () use ($type, $opposite): bool {
            /** @var Action|null $existing */
            $existing = (clone $this->baseActorActionQuery())
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();

                return false;
            }

            if ($opposite !== null) {
                /** @var Action|null $oppositeExisting */
                $oppositeExisting = (clone $this->baseActorActionQuery())
                    ->where('type', $opposite)
                    ->lockForUpdate()
                    ->first();

                if ($oppositeExisting) {
                    $oppositeExisting->delete();
                }
            }

            $this->createAction($type);

            return true;
        });
    }

    public function upvote(): bool
    {
        return $this->toggle(ActionType::UPVOTE, ActionType::DOWNVOTE);
    }

    public function downvote(): bool
    {
        return $this->toggle(ActionType::DOWNVOTE, ActionType::UPVOTE);
    }

    public function like(): bool
    {
        return $this->upvote();
    }

    public function dislike(): bool
    {
        return $this->downvote();
    }

    public function reaction(?string $value): ?Action
    {
        $this->guardActionContext();

        $value = $value !== null ? trim($value) : null;

        return DB::transaction(function () use ($value): ?Action {
            $query = $this->baseActorActionQuery()
                ->where('type', $this->normalizeType(ActionType::REACTION))
                ->lockForUpdate();

            /** @var Action|null $existing */
            $existing = $query->first();

            if ($value === null || $value === '') {
                if ($existing) {
                    $existing->delete();
                }

                return null;
            }

            if ($existing) {
                $existing->data = $value;
                $existing->save();

                return $existing->refresh();
            }

            return $this->createAction($this->normalizeType(ActionType::REACTION), $value);
        });
    }

    public function remove(BackedEnum|string $type): void
    {
        $this->guardActionContext();
        $type = $this->normalizeType($type);

        DB::transaction(function () use ($type): void {
            /** @var Action|null $existing */
            $existing = (clone $this->baseActorActionQuery())
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
            }
        });
    }

    public function has(BackedEnum|string $type): bool
    {
        $this->guardActionContext();
        $type = $this->normalizeType($type);

        return (clone $this->baseActorActionQuery())
            ->where('type', $type)
            ->exists();
    }

    public function count(BackedEnum|string $type): int
    {
        $this->guardTargetContext();
        $type = $this->normalizeType($type);

        $counter = (clone $this->baseTargetCountQuery())
            ->where('type', $type)
            ->first();

        if ($counter) {
            return (int) $counter->count;
        }

        return (int) $this->baseTargetActionQuery()
            ->where('type', $type)
            ->count();
    }

    public function syncCount(BackedEnum|string $type): int
    {
        $this->guardTargetContext();
        $type = $this->normalizeType($type);

        $count = (int) $this->baseTargetActionQuery()
            ->where('type', $type)
            ->count();

        $this->setCount($type, $count);

        return $count;
    }

    /**
     * @param  array<int, BackedEnum|string>  $types
     * @return array<string, int>
     */
    public function syncAllCounts(array $types = []): array
    {
        $this->guardTargetContext();

        $counts = $this->baseTargetActionQuery()
            ->select('type', DB::raw('count(*) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $allTypes = array_values(array_unique(array_merge(
            Actions::defaultActionTypes(),
            $this->normalizeTypes($types),
            array_keys($counts->toArray())
        )));

        $result = [];

        foreach ($allTypes as $type) {
            $count = (int) ($counts[$type] ?? 0);
            $this->setCount($type, $count);
            $result[$type] = $count;
        }

        return $result;
    }

    public function clear(): int
    {
        $this->guardTargetContext();

        return DB::transaction(function (): int {
            $deletedActions = (clone $this->baseTargetActionQuery())->delete();

            (clone $this->baseTargetCountQuery())->delete();

            return $deletedActions;
        });
    }

    /**
     * @return Collection<int, array{reaction: string, count: int, formatted_count: string}>
     */
    public function reactionGroups(int $precision = 1, ?int $maxPrecision = 1): Collection
    {
        $this->guardTargetContext();

        $totals = [];
        $reactionType = $this->normalizeType(ActionType::REACTION);

        foreach (
            $this->baseTargetActionQuery()
                ->where('type', $reactionType)
                ->select('data')
                ->cursor() as $reactionAction
        ) {
            /** @var Action $reactionAction */
            $reaction = $this->extractReactionValue($reactionAction->data);

            if ($reaction === null) {
                continue;
            }

            $totals[$reaction] = ($totals[$reaction] ?? 0) + 1;
        }

        arsort($totals, SORT_NUMERIC);

        return Collection::make($totals)
            ->map(function (int $count, string $reaction) use ($precision, $maxPrecision): array {
                return [
                    'reaction' => $reaction,
                    'count' => $count,
                    'formatted_count' => $this->formatCount($count, $precision, $maxPrecision),
                ];
            })
            ->values();
    }

    protected function createAction(string $type, array|string|null $data = null): Action
    {
        /** @var class-string<Model> $actionModel */
        $actionModel = Actions::actionModel();

        /** @var Action $action */
        $action = $actionModel::query()->create([
            'actionable_type' => $this->actionable->getMorphClass(),
            'actionable_id' => $this->actionable->getKey(),
            'actor_type' => $this->actor->getMorphClass(),
            'actor_id' => $this->actor->getKey(),
            'type' => $type,
            'data' => $data,
        ]);

        return $action;
    }

    protected function setCount(string $type, int $count): void
    {
        $table = Actions::newActionCountModel()->getTable();
        $timestamp = now();

        DB::table($table)->upsert(
            [[
                'actionable_type' => $this->actionable->getMorphClass(),
                'actionable_id' => $this->actionable->getKey(),
                'type' => $type,
                'count' => max(0, $count),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['actionable_type', 'actionable_id', 'type'],
            ['count', 'updated_at']
        );
    }

    protected function normalizeType(BackedEnum|string $type): string
    {
        return Actions::resolveActionType($type);
    }

    /**
     * @param  array<int, BackedEnum|string>  $types
     * @return array<int, string>
     */
    protected function normalizeTypes(array $types): array
    {
        $normalized = [];

        foreach ($types as $type) {
            $normalized[] = $this->normalizeType($type);
        }

        return $normalized;
    }

    protected function actionQuery(): Builder
    {
        /** @var class-string<Model> $actionModel */
        $actionModel = Actions::actionModel();

        return $actionModel::query();
    }

    protected function actionCountQuery(): Builder
    {
        /** @var class-string<Model> $countModel */
        $countModel = Actions::actionCountModel();

        return $countModel::query();
    }

    protected function baseActorActionQuery(): Builder
    {
        return $this->actionQuery()
            ->where('actionable_type', $this->actionable->getMorphClass())
            ->where('actionable_id', $this->actionable->getKey())
            ->where('actor_type', $this->actor->getMorphClass())
            ->where('actor_id', $this->actor->getKey());
    }

    protected function baseTargetActionQuery(): Builder
    {
        return $this->actionQuery()
            ->where('actionable_type', $this->actionable->getMorphClass())
            ->where('actionable_id', $this->actionable->getKey());
    }

    protected function baseTargetCountQuery(): Builder
    {
        return $this->actionCountQuery()
            ->where('actionable_type', $this->actionable->getMorphClass())
            ->where('actionable_id', $this->actionable->getKey());
    }

    protected function guardTargetContext(): void
    {
        if (! $this->actionable) {
            throw new RuntimeException('for($actionable) must be set before querying actions.');
        }
    }

    protected function guardActionContext(): void
    {
        $this->guardTargetContext();

        if (! $this->actor) {
            throw new RuntimeException('by($actor) or authenticated user must be available.');
        }
    }

    protected function formatCount(int $count, int $precision = 1, ?int $maxPrecision = 1): string
    {
        try {
            $formatted = Number::abbreviate($count, $precision, $maxPrecision);
        } catch (RuntimeException) {
            return (string) $count;
        }

        return is_string($formatted) ? $formatted : (string) $count;
    }

    protected function extractReactionValue(mixed $data): ?string
    {
        $value = null;

        if (is_array($data)) {
            $value = $data['value'] ?? null;
        } elseif (is_string($data)) {
            $value = $data;
        }

        if ($value === null) {
            return null;
        }

        $reaction = trim((string) $value);

        return $reaction === '' ? null : $reaction;
    }
}
