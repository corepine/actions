<?php

declare(strict_types=1);

namespace Corepine\Actions\Models;

use Corepine\Actions\Enums\ActionType;
use Corepine\Actions\Facades\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Action extends Model
{
    protected $fillable = [
        'actor_id',
        'actor_type',
        'actionable_id',
        'actionable_type',
        'type',
        'data',
    ];

    protected $casts = [
        'type' => ActionType::class,
        'data' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = Actions::formatTableName('actions');

        parent::__construct($attributes);
    }

    public function actionable(): MorphTo
    {
        return $this->morphTo(null, 'actionable_type', 'actionable_id', 'id');
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor', 'actor_type', 'actor_id', 'id');
    }

    public function scopeWhereActor(Builder $query, Model $actor): Builder
    {
        return $query
            ->where('actor_id', (string) $actor->getKey())
            ->where('actor_type', $actor->getMorphClass());
    }

    public function scopeWithoutActor(Builder $query, Model $actor): Builder
    {
        return $query->where(function (Builder $inner) use ($actor): void {
            $inner->where('actor_id', '<>', (string) $actor->getKey())
                ->orWhere('actor_type', '<>', $actor->getMorphClass());
        });
    }

    public function scopeForActionable(Builder $query, Model $actionable): Builder
    {
        return $query
            ->where('actionable_id', (string) $actionable->getKey())
            ->where('actionable_type', $actionable->getMorphClass());
    }
}
