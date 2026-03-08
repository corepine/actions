<?php

declare(strict_types=1);

namespace Corepine\Actions\Casts;

use Corepine\Actions\Casts\Concerns\InteractsWithActionTypes;
use Corepine\Actions\Contracts\ActionTypeValues;

enum ActionType: string implements ActionTypeValues
{
    use InteractsWithActionTypes;

    case UPVOTE = 'upvote';
    case DOWNVOTE = 'downvote';
    case REACTION = 'reaction';
}
