<?php

use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;
use Jimoh\ActivityFeed\Tests\Support\TestModel;
use Jimoh\ActivityFeed\Tests\Support\TestUser;


beforeEach(function () {
    Activity::query()->delete();
});

it('forSubject() returns only activities for a given subject', function () {
    $model1 = TestModel::create(['name' => 'A']);
    $model2 = TestModel::create(['name' => 'B']);
    Activity::query()->delete();

    activity()->performedOn($model1)->log('viewed');
    activity()->performedOn($model2)->log('viewed');

    expect(Activity::forSubject($model1)->count())->toBe(1)
        ->and(Activity::forSubject($model2)->count())->toBe(1);
});

it('causedBy() returns only activities caused by a given user', function () {
    $user1 = TestUser::create(['name' => 'Alice', 'email' => 'a@example.com']);
    $user2 = TestUser::create(['name' => 'Bob',   'email' => 'b@example.com']);

    activity()->causedBy($user1)->log('did something');
    activity()->causedBy($user2)->log('did another thing');

    expect(Activity::causedBy($user1)->count())->toBe(1)
        ->and(Activity::causedBy($user2)->count())->toBe(1);
});

it('inLog() filters by log name', function () {
    activity()->inLog('admin_audit')->log('action');
    activity()->inLog('default')->log('other action');

    expect(Activity::inLog('admin_audit')->count())->toBe(1);
});

it('withTag() filters by tag', function () {
    activity()->withTag('billing')->log('paid');
    activity()->withTag('security')->log('login');

    expect(Activity::withTag('billing')->count())->toBe(1);
});

it('today() returns only activities from today', function () {
    activity()->log('today action');

    // insert() bypasses Eloquent timestamps so created_at is honoured
    Activity::insert([
        'log_name'    => 'default',
        'description' => 'old action',
        'created_at'  => now()->subDays(2)->toDateTimeString(),
        'updated_at'  => now()->subDays(2)->toDateTimeString(),
    ]);

    expect(Activity::today()->count())->toBe(1);
});

it('thisWeek() returns only activities from this week', function () {
    activity()->log('this week action');

    Activity::insert([
        'log_name'    => 'default',
        'description' => 'old action',
        'created_at'  => now()->subWeeks(2)->toDateTimeString(),
        'updated_at'  => now()->subWeeks(2)->toDateTimeString(),
    ]);

    expect(Activity::thisWeek()->count())->toBe(1);
});
