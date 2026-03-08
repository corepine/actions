<?php

declare(strict_types=1);

namespace Corepine\Actions\Services;

use BackedEnum;
use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Models\Action;
use Corepine\Actions\Models\ActionCount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ActionsManager
{
    public function app(): self
    {
        return $this;
    }

    public function tablePrefix(): string
    {
        return (string) config('corepine-actions.table_prefix', '');
    }

    public function formatTableName(string $table): string
    {
        return $this->tablePrefix() . $table;
    }

    /**
     * @return class-string<Model>
     */
    public function actionModel(): string
    {
        $model = config('corepine-actions.models.action', Action::class);

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('corepine-actions.models.action must be an Eloquent model class.');
        }

        return $model;
    }

    /**
     * @return class-string<Model>
     */
    public function actionCountModel(): string
    {
        $model = config('corepine-actions.models.action_count', ActionCount::class);

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('corepine-actions.models.action_count must be an Eloquent model class.');
        }

        return $model;
    }

    public function newActionModel(): Model
    {
        $model = $this->actionModel();

        return new $model();
    }

    public function newActionCountModel(): Model
    {
        $model = $this->actionCountModel();

        return new $model();
    }

    /**
     * @return array<int, string>
     */
    public function defaultActionTypes(): array
    {
        return ActionType::values();
    }

    public function resolveActionType(ActionType|BackedEnum|string $type): string
    {
        if ($type instanceof ActionType || $type instanceof BackedEnum) {
            if (! is_string($type->value)) {
                throw new RuntimeException('Action type enums must use string-backed values.');
            }

            $normalized = strtolower(trim($type->value));

            if ($normalized === '') {
                throw new RuntimeException('Action type cannot be empty.');
            }

            return $normalized;
        }

        $normalized = strtolower(trim($type));

        if ($normalized === '') {
            throw new RuntimeException('Action type cannot be empty.');
        }

        if (in_array($normalized, ['like', 'dislike'], true)) {
            throw new RuntimeException('Deprecated action types [like, dislike] are not supported. Use [upvote, downvote].');
        }

        return $normalized;
    }

    public function builder(): ActionService
    {
        return new ActionService();
    }

    public function for(Model $actionable): ActionService
    {
        return $this->builder()->for($actionable);
    }

    public function by(Model|Authenticatable|null $actor = null): ActionService
    {
        return $this->builder()->by($actor);
    }
}
