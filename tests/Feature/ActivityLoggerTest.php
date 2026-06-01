<?php

use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;
use Jimoh\ActivityFeed\Tests\Support\TestModel;
use Jimoh\ActivityFeed\Tests\Support\TestUser;


it('logs a manual activity with description', function () {
    activity()->log('something happened');

    expect(Activity::count())->toBe(1)
        ->and(Activity::first()->description)->toBe('something happened');
});

it('logs a manual activity with a subject', function () {
    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();

    activity()->performedOn($model)->log('viewed');

    $activity = Activity::latest()->first();

    expect($activity->subject_type)->toBe($model->getMorphClass())
        ->and((string) $activity->subject_id)->toBe((string) $model->getKey());
});

it('logs a manual activity with a causer', function () {
    $user = TestUser::create(['name' => 'Admin', 'email' => 'admin@example.com']);

    activity()->causedBy($user)->log('did something');

    $activity = Activity::latest()->first();

    expect($activity->causer_type)->toBe($user->getMorphClass())
        ->and((string) $activity->causer_id)->toBe((string) $user->getKey());
});

it('stores extra properties', function () {
    activity()->withProperties(['invoice_id' => 42])->log('approved');

    expect(Activity::first()->properties)->toMatchArray(['invoice_id' => 42]);
});

it('stores tags', function () {
    activity()->withTag('billing')->withTag('admin')->log('approved');

    $tags = Activity::first()->tags;
    expect($tags)->toContain('billing')->toContain('admin');
});

it('logs to a custom log name', function () {
    activity()->inLog('admin_audit')->log('something');

    expect(Activity::first()->log_name)->toBe('admin_audit');
});

it('supports the fluent chain together', function () {
    $model = TestModel::create(['name' => 'Alice']);
    $user  = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com']);
    Activity::query()->delete();

    activity()
        ->performedOn($model)
        ->causedBy($user)
        ->withProperties(['amount' => 100])
        ->withTag('billing')
        ->inLog('admin_audit')
        ->log('approved invoice');

    $activity = Activity::first();

    expect($activity->description)->toBe('approved invoice')
        ->and($activity->log_name)->toBe('admin_audit')
        ->and($activity->subject_id)->toBe($model->getKey())
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->properties['amount'])->toBe(100)
        ->and($activity->tags)->toContain('billing');
});
