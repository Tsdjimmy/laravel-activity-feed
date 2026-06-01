<?php

use Jimoh\ActivityFeed\Models\Activity;
use Jimoh\ActivityFeed\Tests\Support\TestCase;
use Jimoh\ActivityFeed\Tests\Support\TestModel;


it('getDiff() returns an empty array when there are no properties', function () {
    activity()->log('no diff');

    expect(Activity::first()->getDiff())->toBe([]);
});

it('getDiff() returns a field-keyed diff', function () {
    $model = TestModel::create(['name' => 'Alice', 'status' => 'active']);
    Activity::query()->delete();

    $model->update(['name' => 'Bob']);

    $activity = Activity::where('event', 'updated')->first();
    $diff     = $activity->getDiff();

    expect($diff)->toHaveKey('name')
        ->and($diff['name']['old'])->toBe('Alice')
        ->and($diff['name']['new'])->toBe('Bob');
});

it('getDiff() handles partial old/new gracefully', function () {
    $activity = Activity::create([
        'log_name'    => 'default',
        'description' => 'manual diff',
        'properties'  => ['old' => ['x' => 1], 'new' => ['x' => 2, 'y' => 3]],
    ]);

    $diff = $activity->getDiff();

    expect($diff['x'])->toBe(['old' => 1, 'new' => 2])
        ->and($diff['y']['old'])->toBeNull()
        ->and($diff['y']['new'])->toBe(3);
});
