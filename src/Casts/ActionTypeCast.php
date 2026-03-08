<?php

declare(strict_types=1);

namespace Corepine\Actions\Casts;

use BackedEnum;
use Corepine\Actions\Facades\Actions;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ActionTypeCast implements CastsAttributes
{
    /**
     * @return BackedEnum|string|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): BackedEnum|string|null
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return $value;
        }

        $enum = Actions::actionTypeEnum();

        return $enum::tryFrom($normalized) ?? $normalized;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! is_string($value) && ! $value instanceof BackedEnum) {
            throw new InvalidArgumentException('Action type must be a string or string-backed enum.');
        }

        /** @var BackedEnum|string $value */
        return Actions::resolveActionType($value);
    }
}
