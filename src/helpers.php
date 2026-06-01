<?php

use Jimoh\ActivityFeed\ActivityLogger;

if (! function_exists('activity')) {
    function activity(): ActivityLogger
    {
        return app(ActivityLogger::class);
    }
}
