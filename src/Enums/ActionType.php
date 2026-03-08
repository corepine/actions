<?php

declare(strict_types=1);

namespace Corepine\Actions\Enums;

enum ActionType: string
{
    case LIKE = 'like';
    case DISLIKE = 'dislike';
    case REACTION = 'reaction';
}

