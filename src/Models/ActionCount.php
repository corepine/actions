<?php

declare(strict_types=1);

namespace Corepine\Actions\Models;

use Corepine\Actions\Casts\ActionTypeCast;
use Corepine\Actions\Facades\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActionCount extends Model
{
    protected $fillable = [
        'actionable_id',
        'actionable_type',
        'type',
        'count',
    ];

    protected $casts = [
        'type' => ActionTypeCast::class,
        'count' => 'int',
    ];

    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->table = Actions::formatTableName('action_counts');
        }

        parent::__construct($attributes);
    }

    public function scopeForActionable(Builder $query, Model $actionable): Builder
    {
        return $query
            ->where('actionable_id', $actionable->getKey())
            ->where('actionable_type', $actionable->getMorphClass());
    }
}
