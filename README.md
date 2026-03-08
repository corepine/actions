# Corepine Actions

`corepine/actions` is a Laravel package for polymorphic user actions (upvotes, downvotes, reactions) with synced aggregate counters.

`like/dislike` aliases are still supported for backward compatibility.

## Install

```bash
composer require corepine/actions
php artisan actions:install
```

Optional flags:

```bash
php artisan actions:install --migrate
php artisan actions:install --force
```

## Quick Start (Service / Facade)

```php
use Corepine\Actions\Facades\Actions;

$isUpvoted = Actions::for($comment)->by($user)->upvote(); // true = set, false = removed
$isDownvoted = Actions::for($comment)->by($user)->downvote();

Actions::for($comment)->by($user)->reaction('fire');
Actions::for($comment)->by($user)->reaction(null); // remove reaction

$upvotes = Actions::for($comment)->count('upvote');
$downvotes = Actions::for($comment)->count('downvote');
```

## HasActions Concern

Use the built-in concern on your actionable models:

```php
use Corepine\Actions\Models\Concerns\HasActions;

class Comment extends Model
{
    use HasActions;
}
```

Then call helpers directly from the model:

```php
$comment->upvoteBy($user);
$comment->downvoteBy($user);
$comment->reactBy($user, 'fire');

$comment->upvotedBy($user);
$comment->upvotesCount();
$comment->formattedUpvotesCount();
$comment->syncAllActionCounts();
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
