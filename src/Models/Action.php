<?php

declare(strict_types=1);

namespace Corepine\Actions\Models;

use BackedEnum;
use Corepine\Actions\Casts\ActionType;
use Corepine\Actions\Facades\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

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
        if ($this->table === null) {
            $this->table = Actions::formatTableName('actions');
        }

        parent::__construct($attributes);

        $this->mergeCasts([
            'type' => Actions::actionTypeCast(),
        ]);
    }

    protected static function booted(): void
    {
        static::created(static function (self $action): void {
            static::adjustCount($action, 1);
        });

        static::deleted(static function (self $action): void {
            static::adjustCount($action, -1);
        });
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
            ->where('actor_id', $actor->getKey())
            ->where('actor_type', $actor->getMorphClass());
    }

    public function scopeWithoutActor(Builder $query, Model $actor): Builder
    {
        return $query->where(function (Builder $inner) use ($actor): void {
            $inner->where('actor_id', '<>', $actor->getKey())
                ->orWhere('actor_type', '<>', $actor->getMorphClass());
        });
    }

    public function scopeForActionable(Builder $query, Model $actionable): Builder
    {
        return $query
            ->where('actionable_id', $actionable->getKey())
            ->where('actionable_type', $actionable->getMorphClass());
    }

    protected static function adjustCount(self $action, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $typeValue = $action->getAttribute('type');
        $type = $typeValue instanceof BackedEnum ? (string) $typeValue->value : (string) $typeValue;
        $actionableType = (string) $action->getAttribute('actionable_type');
        $actionableId = $action->getAttribute('actionable_id');

        if ($type === '' || $actionableType === '' || $actionableId === null) {
            return;
        }

        $table = Actions::newActionCountModel()->getTable();
        $timestamp = now();

        $attributes = [
            'actionable_type' => $actionableType,
            'actionable_id' => $actionableId,
            'type' => $type,
        ];

        DB::table($table)->upsert(
            [array_merge($attributes, [
                'count' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])],
            ['actionable_type', 'actionable_id', 'type'],
            ['updated_at']
        );

        $query = DB::table($table)->where($attributes);

        if ($delta > 0) {
            $query->increment('count', $delta, ['updated_at' => $timestamp]);

            return;
        }

        $query->where('count', '>', 0)
            ->decrement('count', abs($delta), ['updated_at' => $timestamp]);
    }
}
