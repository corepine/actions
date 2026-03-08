<?php

declare(strict_types=1);

namespace Corepine\Actions\Contracts;

interface ActionTypeValues
{
    /**
     * @return array<int, string>
     */
    public static function values(): array;
}
