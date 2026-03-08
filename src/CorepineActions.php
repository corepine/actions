<?php

declare(strict_types=1);

namespace Corepine\Actions;

class CorepineActions
{
    public static function tablePrefix(): string
    {
        return (string) config('corepine-actions.table_prefix', '');
    }

    public static function formatTableName(string $table): string
    {
        return static::tablePrefix() . $table;
    }
}

