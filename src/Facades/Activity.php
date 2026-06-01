<?php

namespace Jimoh\ActivityFeed\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Jimoh\ActivityFeed\ActivityLogger performedOn(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Jimoh\ActivityFeed\ActivityLogger causedBy(\Illuminate\Database\Eloquent\Model $causer)
 * @method static \Jimoh\ActivityFeed\ActivityLogger withProperties(array $properties)
 * @method static \Jimoh\ActivityFeed\ActivityLogger withProperty(string $key, mixed $value)
 * @method static \Jimoh\ActivityFeed\ActivityLogger withTag(string $tag)
 * @method static \Jimoh\ActivityFeed\ActivityLogger inLog(string $logName)
 * @method static \Jimoh\ActivityFeed\ActivityLogger event(string $event)
 * @method static void log(string $description)
 *
 * @see \Jimoh\ActivityFeed\ActivityLogger
 */
class Activity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Jimoh\ActivityFeed\ActivityLogger::class;
    }
}
