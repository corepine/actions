<?php

declare(strict_types=1);

namespace Corepine\Actions\Tests\Fixtures\Casts;

use Corepine\Actions\Casts\ActionType;

class CustomActionTypeCast extends ActionType
{
    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return ['bookmark'];
    }
}
