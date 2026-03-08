# Corepine Actions

`corepine/actions` is a Laravel package for polymorphic user actions (upvotes, downvotes, reactions) with synced aggregate counters.

## Install

```bash
composer require corepine/actions
php artisan actions:install
```

This publishes:
- `config/corepine-actions.php`
- actions migrations
- `app/Enums/CustomActionType.php` stub (for typed custom actions)

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

Actions::for($comment)->by($user)->reaction('👋');
Actions::for($comment)->by($user)->reaction(null); // remove reaction

$upvotes = Actions::for($comment)->count('upvote');
$downvotes = Actions::for($comment)->count('downvote');
```

## Custom Action Types

Use the published enum stub `app/Enums/CustomActionType.php` and add your own cases:

```php
namespace App\Enums;

enum CustomActionType: string
{
    case BOOKMARK = 'bookmark';
}
```

Then use typed custom actions directly:

```php
use App\Enums\CustomActionType;

Actions::for($comment)->by($user)->toggle(CustomActionType::BOOKMARK);
Actions::for($comment)->count(CustomActionType::BOOKMARK);
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
$comment->reactBy($user, '👋');

$comment->upvotedBy($user);
$comment->upvotesCount();
$comment->formattedUpvotesCount();
$comment->syncAllActionCounts();

// Manual cleanup for this actionable:
$comment->clearActionsAndCounts();

// Alias:
$comment->deleteActionsAndCounts();
```

When the actionable model is deleted, `HasActions` auto-cleans related `actions` and `action_counts` rows.

## Reaction Groups (Render Ready)

For emoji reaction chips like `👋 6` or `❤️ 2.5K`, use grouped reactions:

```php
$groups = $comment->reactionGroups();

// Each item has:
// ['reaction' => '👋', 'count' => 6, 'formatted_count' => '6']
// ['reaction' => '❤️', 'count' => 2500, 'formatted_count' => '2.5K']
```

If you already have a precomputed count, format it directly:

```php
$comment->formattedReactionsCount(2500); // 2.5K
```

## Counter Consistency

Counters are stored in `action_counts` and updated automatically from `Action` model created/deleted events (including service writes).

If you need to rebuild counters for a specific model:

```php
Actions::for($comment)->syncAllCounts();
```

If you want `syncAllCounts()` to include custom zero-bucket types, append them:

```php
Actions::for($comment)->syncAllCounts([CustomActionType::BOOKMARK]);
```

If you need to delete everything for one actionable and keep tables in sync:

```php
Actions::for($comment)->clear();
```

## Tables

- `actions`: one row per actor + actionable + type
- `action_counts`: aggregate count per actionable + type
