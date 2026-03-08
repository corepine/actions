<?php

declare(strict_types=1);

namespace Corepine\Actions\Casts\Concerns;

use BackedEnum;

trait InteractsWithActionTypes
{
    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        $values = array_merge(
            static::defaultTypes(),
            static::types(),
            static::configuredTypes()
        );

        return static::normalizeValues($values);
    }

    /**
     * @return array<int, string>
     */
    public static function defaultTypes(): array
    {
        return ['upvote', 'downvote', 'reaction'];
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function configuredTypes(): array
    {
        $configured = config('corepine-actions.action_types', []);

        return is_array($configured) ? $configured : [];
    }

    /**
     * @param  array<int, mixed> $values
     * @return array<int, string>
     */
    protected static function normalizeValues(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $type = strtolower(trim($value));

            if ($type === '' || in_array($type, ['like', 'dislike'], true)) {
                continue;
            }

            $normalized[] = $type;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return string|null
     */
    protected static function normalizeType(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            if (! is_string($value->value)) {
                return null;
            }

            $value = $value->value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($normalized === '' || in_array($normalized, ['like', 'dislike'], true)) {
            return null;
        }

        return $normalized;
    }
}
