<?php

declare(strict_types=1);

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
    'table_prefix' => env('COREPINE_ACTIONS_TABLE_PREFIX', ''),

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
];
