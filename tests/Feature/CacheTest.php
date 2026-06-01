<?php

use Jimoh\ActivityFeed\Cachers\ActivityCacher;
use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;
use Jimoh\ActivityFeed\Tests\Support\TestModel;
use Illuminate\Support\Facades\Cache;


beforeEach(function () {
    config(['activity-feed.cache' => true]);
    Cache::flush();
    Activity::query()->delete();
});

it('bypasses cache entirely when cache is disabled', function () {
    config(['activity-feed.cache' => false]);

    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();

    activity()->performedOn($model)->log('viewed');

    $cacher = app(ActivityCacher::class);

    // No cache key should exist
    $key = "activity_feed:subject:{$model->getMorphClass()}:{$model->getKey()}:default:1";
    $safeKey = str_replace('\\', '_', $key);

    expect(Cache::has($safeKey))->toBeFalse();
});

it('returns cached result on second forSubject() call', function () {
    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();
    activity()->performedOn($model)->log('viewed');

    $cacher = app(ActivityCacher::class);
    $query1 = Activity::forSubject($model);
    $result1 = $cacher->rememberSubject(
        $query1,
        $model->getMorphClass(),
        $model->getKey(),
        'default',
        1,
        15
    );

    // Insert a new activity directly (bypassing logger cache invalidation)
    Activity::create([
        'log_name'    => 'default',
        'description' => 'second',
        'subject_type' => $model->getMorphClass(),
        'subject_id'   => $model->getKey(),
    ]);

    $query2 = Activity::forSubject($model);
    $result2 = $cacher->rememberSubject(
        $query2,
        $model->getMorphClass(),
        $model->getKey(),
        'default',
        1,
        15
    );

    // Cache hit: both pages return the same count (1, not 2)
    expect($result1->total())->toBe(1)
        ->and($result2->total())->toBe(1);
});

it('invalidates cache after a new activity is logged via logger', function () {
    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();
    activity()->performedOn($model)->log('first');

    $cacher = app(ActivityCacher::class);

    // Prime the cache
    $cacher->rememberSubject(
        Activity::forSubject($model),
        $model->getMorphClass(),
        $model->getKey(),
        'default',
        1,
        15
    );

    // This should invalidate the cache
    activity()->performedOn($model)->log('second');

    // Re-query: cache was busted so we should see 2 records
    $result = $cacher->rememberSubject(
        Activity::forSubject($model),
        $model->getMorphClass(),
        $model->getKey(),
        'default',
        1,
        15
    );

    expect($result->total())->toBe(2);
});

it('manual forget() clears the cache for a subject', function () {
    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();
    activity()->performedOn($model)->log('first');

    $cacher = app(ActivityCacher::class);

    $cacher->rememberSubject(
        Activity::forSubject($model),
        $model->getMorphClass(),
        $model->getKey(),
        'default',
        1,
        15
    );

    Activity::create([
        'log_name'     => 'default',
        'description'  => 'direct insert',
        'subject_type' => $model->getMorphClass(),
        'subject_id'   => $model->getKey(),
    ]);

    $cacher->forget($model);

    $result = $cacher->rememberSubject(
        Activity::forSubject($model),
        $model->getMorphClass(),
        $model->getKey(),
        'default',
        1,
        15
    );

    expect($result->total())->toBe(2);
});
