<?php

declare(strict_types=1);

namespace Corepine\Actions\Tests\Fixtures\Casts;

use Corepine\Actions\Casts\Concerns\InteractsWithActionTypes;
use Corepine\Actions\Contracts\ActionTypeValues;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CustomActionTypeCast implements CastsAttributes, ActionTypeValues
{
    use InteractsWithActionTypes;

    /**
     * @return array<int, string>
     */
    public static function additionalValues(): array
    {
        return ['bookmark'];
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
