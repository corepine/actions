<?php

declare(strict_types=1);

namespace Corepine\Actions\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class ActionsManager
{
    public function app(): self
    {
        return $this;
    }

    public function tablePrefix(): string
    {
        return (string) config('corepine-actions.table_prefix', '');
    }

    public function formatTableName(string $table): string
    {
        return $this->tablePrefix() . $table;
    }

    public function builder(): ActionService
    {
        return new ActionService();
    }

    public function for(Model $actionable): ActionService
    {
        return $this->builder()->for($actionable);
    }

    public function by(Model|Authenticatable|null $actor = null): ActionService
    {
        return $this->builder()->by($actor);
    }
}
