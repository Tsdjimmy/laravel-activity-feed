<?php

use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;


it('deletes activity records older than the given days', function () {
    // 3 old records
    Activity::insert([
        ['log_name' => 'default', 'description' => 'old 1', 'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100)],
        ['log_name' => 'default', 'description' => 'old 2', 'created_at' => now()->subDays(95),  'updated_at' => now()->subDays(95)],
        ['log_name' => 'default', 'description' => 'old 3', 'created_at' => now()->subDays(91),  'updated_at' => now()->subDays(91)],
    ]);

    // 2 recent records
    Activity::insert([
        ['log_name' => 'default', 'description' => 'recent 1', 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)],
        ['log_name' => 'default', 'description' => 'recent 2', 'created_at' => now()->subDays(5),  'updated_at' => now()->subDays(5)],
    ]);

    $this->artisan('activity:prune', ['--days' => 90])
        ->expectsOutputToContain('Pruned 3 activity log record(s) older than 90 day(s).')
        ->assertExitCode(0);

    expect(Activity::count())->toBe(2);
});

it('uses the config prune_days when --days is not provided', function () {
    config(['activity-feed.prune_days' => 30]);

    Activity::insert([
        ['log_name' => 'default', 'description' => 'old', 'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)],
    ]);

    Activity::insert([
        ['log_name' => 'default', 'description' => 'recent', 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10)],
    ]);

    $this->artisan('activity:prune')
        ->expectsOutputToContain('Pruned 1 activity log record(s) older than 30 day(s).')
        ->assertExitCode(0);

    expect(Activity::count())->toBe(1);
});

it('outputs zero when there is nothing to prune', function () {
    $this->artisan('activity:prune', ['--days' => 90])
        ->expectsOutputToContain('Pruned 0 activity log record(s)')
        ->assertExitCode(0);
});

it('returns failure when days is zero or negative', function () {
    $this->artisan('activity:prune', ['--days' => 0])
        ->assertExitCode(1);
});
