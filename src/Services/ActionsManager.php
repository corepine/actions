<?php

declare(strict_types=1);

namespace Corepine\Actions\Services;

use BackedEnum;
use Corepine\Actions\Casts\ActionType;
use Corepine\Actions\Contracts\ActionTypeValues;
use Corepine\Actions\Models\Action;
use Corepine\Actions\Models\ActionCount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
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
     * @return class-string<CastsAttributes|BackedEnum>
     */
    public function actionTypeCast(): string
    {
        $cast = config('corepine-actions.action_type_cast');

        if (! is_string($cast) || trim($cast) === '') {
            $cast = ActionType::class;
        }

        if (! class_exists($cast) && str_ends_with($cast, 'ActionTypeCast')) {
            $enumCandidate = preg_replace('/ActionTypeCast$/', 'ActionType', $cast);

            if (is_string($enumCandidate) && class_exists($enumCandidate)) {
                $cast = $enumCandidate;
            }
        }

        if (! class_exists($cast)) {
            throw new RuntimeException('corepine-actions.action_type_cast must be a valid Eloquent cast class or string-backed enum.');
        }

        $isCastClass = is_subclass_of($cast, CastsAttributes::class);
        $isBackedEnum = enum_exists($cast) && is_subclass_of($cast, BackedEnum::class);

        if (! $isCastClass && ! $isBackedEnum) {
            throw new RuntimeException('corepine-actions.action_type_cast must be a valid Eloquent cast class or string-backed enum.');
        }

        if ($isBackedEnum) {
            foreach ($cast::cases() as $case) {
                if (! is_string($case->value)) {
                    throw new RuntimeException('corepine-actions.action_type_cast enum must be string-backed.');
                }
            }
        }

        return $cast;
    }

    /**
     * @return array<int, string>
     */
    public function defaultActionTypes(): array
    {
        $cast = $this->actionTypeCast();

        if (is_subclass_of($cast, ActionTypeValues::class)) {
            return $this->normalizeActionTypes($cast::values());
        }

        if (method_exists($cast, 'values')) {
            $values = $cast::values();

            if (is_array($values)) {
                $types = $this->normalizeActionTypes($values);

                if ($types !== []) {
                    return $types;
                }
            }
        }

        if (! enum_exists($cast) || ! is_subclass_of($cast, BackedEnum::class)) {
            return ActionType::values();
        }

        $values = [];

        foreach ($cast::cases() as $case) {
            if (! is_string($case->value)) {
                continue;
            }

            $values[] = $case->value;
        }

        $types = $this->normalizeActionTypes($values);

        if ($types !== []) {
            return $types;
        }

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

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    protected function normalizeActionTypes(array $values): array
    {
        $types = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $normalized = strtolower(trim($value));

            if ($normalized === '' || in_array($normalized, ['like', 'dislike'], true)) {
                continue;
            }

            $types[] = $normalized;
        }

        return array_values(array_unique($types));
    }
}
