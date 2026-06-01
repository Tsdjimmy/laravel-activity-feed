<?php

use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;
use Jimoh\ActivityFeed\Tests\Support\TestModel;


it('logs a created event when a model is created', function () {
    $model = TestModel::create(['name' => 'Alice', 'status' => 'active']);

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('created')
        ->and($activity->description)->toBe('created')
        ->and($activity->subject_type)->toBe($model->getMorphClass())
        ->and((string) $activity->subject_id)->toBe((string) $model->getKey());
});

it('logs an updated event when a model is updated', function () {
    $model = TestModel::create(['name' => 'Alice', 'status' => 'active']);
    Activity::query()->delete(); // reset

    $model->update(['name' => 'Bob']);

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('updated');
});

it('captures a diff on update', function () {
    $model = TestModel::create(['name' => 'Alice', 'status' => 'active']);
    Activity::query()->delete();

    $model->update(['name' => 'Bob']);

    $activity = Activity::where('event', 'updated')->latest()->first();

    expect($activity->properties)->toHaveKey('old')
        ->and($activity->properties['old']['name'])->toBe('Alice')
        ->and($activity->properties['new']['name'])->toBe('Bob');
});

it('logs a deleted event when a model is deleted', function () {
    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();

    $model->delete();

    $activity = Activity::latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('deleted');
});

it('respects logAttributes whitelist', function () {
    $model = new class extends TestModel {
        protected array $logAttributes = ['name'];
    };
    $model->fill(['name' => 'Alice', 'status' => 'active'])->save();
    Activity::query()->delete();

    $model->update(['name' => 'Bob', 'status' => 'inactive']);

    $activity = Activity::where('event', 'updated')->latest()->first();

    expect($activity->properties['new'])->toHaveKey('name')
        ->and($activity->properties['new'])->not->toHaveKey('status');
});

it('respects ignoreAttributes blacklist', function () {
    $model = new class extends TestModel {
        protected array $ignoreAttributes = ['secret'];
    };
    $model->fill(['name' => 'Alice', 'secret' => 'top'])->save();
    Activity::query()->delete();

    $model->update(['name' => 'Bob', 'secret' => 'classified']);

    $activity = Activity::where('event', 'updated')->latest()->first();

    expect($activity->properties['new'])->toHaveKey('name')
        ->and($activity->properties['new'])->not->toHaveKey('secret');
});

it('does not capture timestamps in the diff', function () {
    $model = TestModel::create(['name' => 'Alice']);
    Activity::query()->delete();

    $model->update(['name' => 'Bob']);

    $activity = Activity::where('event', 'updated')->latest()->first();

    expect($activity->properties['new'])->not->toHaveKey('updated_at')
        ->and($activity->properties['new'])->not->toHaveKey('created_at');
});
