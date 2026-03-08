<?php

declare(strict_types=1);

namespace Corepine\Actions\Facades;

use Illuminate\Support\Facades\Facade;

class Actions extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'corepine-actions';
    }
}

