# laravel-activity-feed

[![Tests](https://github.com/tsdjimmy/laravel-activity-feed/actions/workflows/tests.yml/badge.svg)](https://github.com/tsdjimmy/laravel-activity-feed/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jimoh/laravel-activity-feed.svg)](https://packagist.org/packages/jimoh/laravel-activity-feed)
[![PHP Version](https://img.shields.io/packagist/php-v/jimoh/laravel-activity-feed.svg)](https://packagist.org/packages/jimoh/laravel-activity-feed)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/jimoh/laravel-activity-feed.svg)](LICENSE)

A Laravel package that logs model events and manual activity to an `activity_log` table. Includes a fluent query builder, model-change diff viewer, optional queue driver, intelligent caching, and an optional Filament v3 panel plugin (coming soon).

---

## Requirements

- PHP 8.2+
- Laravel 11+

---

## Installation

```bash
composer require jimoh/laravel-activity-feed
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=activity-feed-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=activity-feed-config
```

---

## Database schema

```
┌─────────────────────────────────────────────────────────────┐
│                        activity_log                         │
├──────────────────┬──────────────────────────────────────────┤
│ id               │ bigint, primary key                      │
│ log_name         │ string  default:'default'  ← index       │
│ description      │ string                                   │
│ subject_type     │ string, nullable  ┐                      │
│ subject_id       │ bigint, nullable  ┘← morphs index        │
│ subject_snapshot │ json, nullable                           │
│ causer_type      │ string, nullable  ┐                      │
│ causer_id        │ bigint, nullable  ┘← morphs index        │
│ properties       │ json, nullable  (old/new diff goes here) │
│ event            │ string, nullable                         │
│ ip_address       │ string(45), nullable                     │
│ user_agent       │ string(1024), nullable                   │
│ tags             │ json, nullable                           │
│ created_at       │ timestamp  ← index (pruning + scopes)    │
│ updated_at       │ timestamp                                │
└──────────────────┴──────────────────────────────────────────┘
```

---

## Write flow

```
activity()->log('...')
        │
        ▼
  queue enabled?
   ┌────┴────┐
  YES       NO
   │         │
   ▼         ▼
WriteActivityLog    ActivityLogger::writeToDb()
  job dispatched         │
  (3 retries,            ▼
   10s backoff)    activity_log table
        │
        ▼  (on job handle)
  ActivityLogger::writeToDb()
        │
        ▼
  activity_log table

After every write (inline or queued dispatch):
  ActivityCacher::forget($subject)  ←── cache invalidated
```

---

## Basic usage

### Automatic model logging (trait)

Add the `LogsActivity` trait to any Eloquent model. It will automatically log `created`, `updated`, and `deleted` events, including a before/after diff on updates.

```php
use Jimoh\ActivityFeed\Traits\LogsActivity;

class Invoice extends Model
{
    use LogsActivity;

    // Optional: whitelist — only these fields appear in diffs
    protected array $logAttributes = ['status', 'amount'];

    // Optional: blacklist — never appear in diffs
    protected array $ignoreAttributes = ['internal_notes'];
}
```

### Manual activity logging

Use the `activity()` helper or the `Activity` facade:

```php
activity()
    ->performedOn($invoice)
    ->causedBy($user)
    ->withProperties(['amount' => 1500])
    ->withTag('billing')
    ->inLog('admin_audit')
    ->log('approved invoice');
```

```php
use Jimoh\ActivityFeed\Facades\Activity;

Activity::performedOn($invoice)->causedBy($user)->log('sent reminder');
```

---

## Querying the feed

```php
use Jimoh\ActivityFeed\Models\Activity;

// All activity for a model
Activity::forSubject($invoice)->get();

// All activity caused by a user
Activity::causedBy($user)->get();

// Filter by log name
Activity::inLog('admin_audit')->get();

// Filter by tag
Activity::withTag('billing')->get();

// Time scopes
Activity::today()->get();
Activity::thisWeek()->get();

// Chainable
Activity::forSubject($invoice)->inLog('admin_audit')->latest()->paginate(25);
```

---

## Viewing diffs

```php
$activity = Activity::forSubject($invoice)->where('event', 'updated')->first();

$diff = $activity->getDiff();
// [
//   'status' => ['old' => 'pending', 'new' => 'approved'],
//   'amount' => ['old' => 1000,      'new' => 1500],
// ]
```

---

## Config reference

Publish with `php artisan vendor:publish --tag=activity-feed-config`.

| Key | Default | Env var | Description |
|---|---|---|---|
| `default_log_name` | `"default"` | — | Default value for `log_name` |
| `model` | `Activity::class` | — | Eloquent model class for the log table |
| `queue` | `false` | — | Enable async writing via queue |
| `queue_connection` | `"default"` | `ACTIVITY_QUEUE_CONNECTION` | Queue connection name |
| `queue_name` | `"default"` | `ACTIVITY_QUEUE_NAME` | Queue name |
| `cache` | `false` | — | Enable feed caching |
| `cache_store` | `null` | `ACTIVITY_CACHE_STORE` | Cache store (null = app default) |
| `cache_ttl` | `300` | `ACTIVITY_CACHE_TTL` | Cache TTL in seconds |
| `prune_days` | `90` | — | Days to retain logs (used by `activity:prune`) |
| `capture_snapshot` | `false` | — | Store full model snapshot on each event |
| `capture_ip` | `true` | — | Capture IP address from the request |
| `capture_user_agent` | `true` | — | Capture User-Agent from the request |
| `subject_returns_soft_deleted` | `true` | — | Include soft-deleted subjects in relations |

---

## Pruning old records

```bash
php artisan activity:prune          # uses prune_days from config
php artisan activity:prune --days=30
```

Records are deleted in chunks of 1 000 to avoid locking the table.

---

## High-traffic setup

For applications with heavy write traffic, enable the queue driver and add a Redis cache to reduce database pressure.

### `.env`

```env
ACTIVITY_QUEUE_CONNECTION=redis
ACTIVITY_QUEUE_NAME=activity

ACTIVITY_CACHE_STORE=redis
ACTIVITY_CACHE_TTL=600
```

### `config/activity-feed.php`

```php
'queue' => true,
'cache' => true,
```

### Supervisor worker

```ini
[program:activity-worker]
command=php /var/www/artisan queue:work redis --queue=activity --tries=3
autostart=true
autorestart=true
```

When `queue = true`, `activity()->log()` dispatches a `WriteActivityLog` job (3 retries, 10 s backoff) instead of writing inline. When `cache = true`, `forSubject()` and `causedBy()` results are cached per page. Cache invalidation is automatic on every new write; manual invalidation is also available:

```php
use Jimoh\ActivityFeed\Cachers\ActivityCacher;

app(ActivityCacher::class)->forget($invoice);
```

Drivers that support cache tags (Redis, Memcached) use tag-based invalidation. File and database drivers fall back to a key registry.

---

## Filament v3 plugin

Coming soon.

---

## Author

**Oluwasegun Jimoh** — [github.com/tsdjimmy](https://github.com/tsdjimmy) — oluwasegunjimoh@gmail.com

---

## License

MIT
