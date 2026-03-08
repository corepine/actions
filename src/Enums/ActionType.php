<?php

declare(strict_types=1);

namespace Corepine\Actions\Enums;

enum ActionType: string
{
    case UPVOTE = 'like';
    case DOWNVOTE = 'dislike';
    case REACTION = 'reaction';

    // Backward-compatible aliases.
    public const LIKE = self::UPVOTE;
    public const DISLIKE = self::DOWNVOTE;

    public static function fromInput(string $type): ?self
    {
        return match (strtolower(trim($type))) {
            'upvote', 'like' => self::UPVOTE,
            'downvote', 'dislike' => self::DOWNVOTE,
            'reaction' => self::REACTION,
            default => null,
        };
    }
}
