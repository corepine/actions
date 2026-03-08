# Corepine Actions

`corepine/actions` is a Laravel package for polymorphic user actions (like, dislike, reaction) with synced aggregate counters.

## Install

```bash
composer require corepine/actions
php artisan vendor:publish --tag=corepine-actions-config
php artisan migrate
```

## Quick Start

```php
use Corepine\Actions\Facades\Actions;

$isLiked = Actions::for($comment)->by($user)->like(); // true = liked, false = removed
$isDisliked = Actions::for($comment)->by($user)->dislike();

Actions::for($comment)->by($user)->reaction('fire');
Actions::for($comment)->by($user)->reaction(null); // remove reaction

$likes = Actions::for($comment)->count('like');
$dislikes = Actions::for($comment)->count('dislike');
```

## Counter Consistency

Counters are stored in `action_counts` and updated in the same database transaction as action writes.

If you need to rebuild counters for a specific model:

```php
Actions::for($comment)->syncAllCounts();
```

## Tables

- `actions`: one row per actor + actionable + type
- `action_counts`: aggregate count per actionable + type
