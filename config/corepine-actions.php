<?php

declare(strict_types=1);

use Corepine\Actions\Casts\ActionType;
use Corepine\Actions\Models\Action;
use Corepine\Actions\Models\ActionCount;

return [
    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | If you run this package in a shared database, set a prefix so tables
    | become e.g. "cp_actions" and "cp_action_counts".
    |
    */
    'table_prefix' => env('COREPINE_ACTIONS_TABLE_PREFIX', 'corepine_'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | You may replace the default models with your own classes.
    |
    */
    'models' => [
        'action' => Action::class,
        'action_count' => ActionCount::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    |
    | Cast class used for `actions.type` and `action_counts.type`.
    | Defaults to the package cast.
    |
    */
    'action_type_cast' => ActionType::class,

    /*
    |--------------------------------------------------------------------------
    | Action Types
    |--------------------------------------------------------------------------
    |
    | Add custom action types without creating a custom cast class.
    | These are merged with the default: upvote, downvote, reaction.
    |
    */
    'action_types' => [],
];
