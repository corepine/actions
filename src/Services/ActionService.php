<?php

declare(strict_types=1);

namespace Corepine\Actions\Services;

use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Models\Action;
use Corepine\Actions\Models\ActionCount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

    public function like(): bool
    {
        return $this->toggleBinary(ActionType::LIKE);
    }

    public function dislike(): bool
    {
        return $this->toggleBinary(ActionType::DISLIKE);
    }

    public function reaction(?string $value): ?Action
    {
        $this->guardActionContext();

        $value = $value !== null ? trim($value) : null;

        return DB::transaction(function () use ($value): ?Action {
            $query = $this->baseActorActionQuery()
                ->where('type', ActionType::REACTION->value)
                ->lockForUpdate();

            /** @var Action|null $existing */
            $existing = $query->first();

            if ($value === null || $value === '') {
                if ($existing) {
                    $existing->delete();
                    $this->adjustCount(ActionType::REACTION, -1);
                }

                return null;
            }

            if ($existing) {
                $existing->data = ['value' => $value];
                $existing->save();

                return $existing->refresh();
            }

            $action = $this->createAction(ActionType::REACTION, ['value' => $value]);
            $this->adjustCount(ActionType::REACTION, 1);

            return $action;
        });
    }

    public function remove(ActionType|string $type): void
    {
        $this->guardActionContext();
        $type = $this->normalizeType($type);

        DB::transaction(function () use ($type): void {
            /** @var Action|null $existing */
            $existing = (clone $this->baseActorActionQuery())
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $this->adjustCount($type, -1);
            }
        });
    }

    public function has(ActionType|string $type): bool
    {
        $this->guardActionContext();
        $type = $this->normalizeType($type);

        return (clone $this->baseActorActionQuery())
            ->where('type', $type->value)
            ->exists();
    }

    public function count(ActionType|string $type): int
    {
        $this->guardTargetContext();
        $type = $this->normalizeType($type);

        $counter = ActionCount::query()
            ->forActionable($this->actionable)
            ->where('type', $type->value)
            ->first();

        if ($counter) {
            return (int) $counter->count;
        }

        return (int) $this->baseTargetActionQuery()
            ->where('type', $type->value)
            ->count();
    }

    public function syncCount(ActionType|string $type): int
    {
        $this->guardTargetContext();
        $type = $this->normalizeType($type);

        $count = (int) $this->baseTargetActionQuery()
            ->where('type', $type->value)
            ->count();

        $this->setCount($type, $count);

        return $count;
    }

    public function syncAllCounts(): array
    {
        $this->guardTargetContext();

        $counts = $this->baseTargetActionQuery()
            ->select('type', DB::raw('count(*) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $result = [];

        foreach (ActionType::cases() as $type) {
            $count = (int) ($counts[$type->value] ?? 0);
            $this->setCount($type, $count);
            $result[$type->value] = $count;
        }

        return $result;
    }

    protected function toggleBinary(ActionType $type): bool
    {
        $this->guardActionContext();

        if (! in_array($type, [ActionType::LIKE, ActionType::DISLIKE], true)) {
            throw new RuntimeException('toggleBinary only supports like/dislike.');
        }

        $opposite = $type === ActionType::LIKE ? ActionType::DISLIKE : ActionType::LIKE;

        return DB::transaction(function () use ($type, $opposite): bool {
            /** @var Action|null $existing */
            $existing = (clone $this->baseActorActionQuery())
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $this->adjustCount($type, -1);

                return false;
            }

            /** @var Action|null $oppositeExisting */
            $oppositeExisting = (clone $this->baseActorActionQuery())
                ->where('type', $opposite->value)
                ->lockForUpdate()
                ->first();

            if ($oppositeExisting) {
                $oppositeExisting->delete();
                $this->adjustCount($opposite, -1);
            }

            $this->createAction($type);
            $this->adjustCount($type, 1);

            return true;
        });
    }

    protected function createAction(ActionType $type, array|string|null $data = null): Action
    {
        return Action::query()->create([
            'actionable_type' => $this->actionable->getMorphClass(),
            'actionable_id' => $this->actionable->getKey(),
            'actor_type' => $this->actor->getMorphClass(),
            'actor_id' => $this->actor->getKey(),
            'type' => $type->value,
            'data' => $data,
        ]);
    }

    protected function adjustCount(ActionType $type, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $table = (new ActionCount())->getTable();
        $timestamp = now();

        $attributes = [
            'actionable_type' => $this->actionable->getMorphClass(),
            'actionable_id' => $this->actionable->getKey(),
            'type' => $type->value,
        ];

        DB::table($table)->upsert(
            [array_merge($attributes, [
                'count' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])],
            ['actionable_type', 'actionable_id', 'type'],
            ['updated_at']
        );

        $query = DB::table($table)->where($attributes);

        if ($delta > 0) {
            $query->increment('count', $delta, ['updated_at' => $timestamp]);

            return;
        }

        $query->where('count', '>', 0)
            ->decrement('count', abs($delta), ['updated_at' => $timestamp]);
    }

    protected function setCount(ActionType $type, int $count): void
    {
        $table = (new ActionCount())->getTable();
        $timestamp = now();

        DB::table($table)->upsert(
            [[
                'actionable_type' => $this->actionable->getMorphClass(),
                'actionable_id' => $this->actionable->getKey(),
                'type' => $type->value,
                'count' => max(0, $count),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['actionable_type', 'actionable_id', 'type'],
            ['count', 'updated_at']
        );
    }

    protected function normalizeType(ActionType|string $type): ActionType
    {
        if ($type instanceof ActionType) {
            return $type;
        }

        $normalized = ActionType::tryFrom(strtolower(trim($type)));

        if (! $normalized) {
            throw new RuntimeException('Invalid action type: ' . $type);
        }

        return $normalized;
    }

    protected function baseActorActionQuery(): Builder
    {
        return Action::query()
            ->where('actionable_type', $this->actionable->getMorphClass())
            ->where('actionable_id', $this->actionable->getKey())
            ->where('actor_type', $this->actor->getMorphClass())
            ->where('actor_id', $this->actor->getKey());
    }

    protected function baseTargetActionQuery(): Builder
    {
        return Action::query()
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
}
