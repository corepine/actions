<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Corepine\Actions\Models\Concerns\HasActions;

class ActionablePost extends Post
{
    use HasActions;
}
