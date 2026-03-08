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
            static::defaultValues(),
            static::enumCaseValues(),
            static::additionalValues()
        );

        return static::normalizeValues($values);
    }

    /**
     * @return array<int, string>
     */
    public static function defaultValues(): array
    {
        return ['upvote', 'downvote', 'reaction'];
    }

    /**
     * @return array<int, string>
     */
    public static function additionalValues(): array
    {
        return [];
    }

    /**
     * @param  array<int, mixed>  $values
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
     * @return array<int, string>
     */
    protected static function enumCaseValues(): array
    {
        if (! method_exists(static::class, 'cases')) {
            return [];
        }

        $values = [];

        foreach (static::cases() as $case) {
            if (! $case instanceof BackedEnum || ! is_string($case->value)) {
                continue;
            }

            $values[] = $case->value;
        }

        return $values;
    }
}
