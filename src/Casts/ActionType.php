<?php

declare(strict_types=1);

namespace Corepine\Actions\Casts;

use Corepine\Actions\Casts\Concerns\InteractsWithActionTypes;
use Corepine\Actions\Contracts\ActionTypeValues;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ActionType implements CastsAttributes, ActionTypeValues
{
    use InteractsWithActionTypes;

    public const UPVOTE = 'upvote';
    public const DOWNVOTE = 'downvote';
    public const REACTION = 'reaction';

    /**
     * @return array<int, string>
     */
    public static function defaultTypes(): array
    {
        return [self::UPVOTE, self::DOWNVOTE, self::REACTION];
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return static::normalizeType($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $type = static::normalizeType($value);

        if ($type === null) {
            throw new RuntimeException("Invalid action type for [{$key}].");
        }

        if (! in_array($type, static::values(), true)) {
            throw new RuntimeException("Unsupported action type [{$type}] for [{$key}].");
        }

        return $type;
    }
}
