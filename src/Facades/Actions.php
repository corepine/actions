<?php

declare(strict_types=1);

namespace Corepine\Actions\Facades;

use Corepine\Actions\Services\ActionsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Corepine\Actions\Services\ActionsManager app()
 * @method static string tablePrefix()
 * @method static string formatTableName(string $table)
 * @method static \Corepine\Actions\Services\ActionService builder()
 * @method static \Corepine\Actions\Services\ActionService for(\Illuminate\Database\Eloquent\Model $actionable)
 * @method static \Corepine\Actions\Services\ActionService by(\Illuminate\Database\Eloquent\Model|\Illuminate\Contracts\Auth\Authenticatable|null $actor = null)
 */
class Actions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActionsManager::class;
    }
}
