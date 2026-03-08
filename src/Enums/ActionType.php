<?php

declare(strict_types=1);

namespace Corepine\Actions\Enums;

enum ActionType: string
{
    case UPVOTE = 'upvote';
    case DOWNVOTE = 'downvote';
    case REACTION = 'reaction';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
