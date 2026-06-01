<?php

use Jimoh\ActivityFeed\Jobs\WriteActivityLog;
use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;
use Illuminate\Support\Facades\Queue;


it('dispatches a WriteActivityLog job when queue is enabled', function () {
    config(['activity-feed.queue' => true]);
    Queue::fake();

    activity()->log('queued action');

    Queue::assertPushed(WriteActivityLog::class);
    expect(Activity::count())->toBe(0); // not written inline
});

it('writes inline when queue is disabled', function () {
    config(['activity-feed.queue' => false]);

    activity()->log('inline action');

    expect(Activity::count())->toBe(1);
});

it('dispatches to the configured queue name', function () {
    config([
        'activity-feed.queue'      => true,
        'activity-feed.queue_name' => 'high-priority',
    ]);
    Queue::fake();

    activity()->log('queued on named queue');

    Queue::assertPushedOn('high-priority', WriteActivityLog::class);
});

it('WriteActivityLog job writes to the database when handled', function () {
    $payload = [
        'log_name'         => 'default',
        'description'      => 'from job',
        'subject_type'     => null,
        'subject_id'       => null,
        'subject_snapshot' => null,
        'causer_type'      => null,
        'causer_id'        => null,
        'properties'       => null,
        'event'            => null,
        'ip_address'       => null,
        'user_agent'       => null,
        'tags'             => null,
    ];

    (new WriteActivityLog($payload))->handle();

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->description)->toBe('from job');
});
