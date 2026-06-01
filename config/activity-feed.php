<?php

return [

    'default_log_name' => 'default',

    'model' => \Jimoh\ActivityFeed\Models\Activity::class,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | When enabled, activity writes are dispatched as jobs instead of being
    | written inline. Recommended for high-traffic applications.
    */
    'queue'            => false,
    'queue_connection' => env('ACTIVITY_QUEUE_CONNECTION', 'default'),
    'queue_name'       => env('ACTIVITY_QUEUE_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | When enabled, forSubject() and causedBy() feed queries are cached.
    | Disabled by default so the package works without Redis out of the box.
    */
    'cache'       => false,
    'cache_store' => env('ACTIVITY_CACHE_STORE', null),
    'cache_ttl'   => env('ACTIVITY_CACHE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    */
    'prune_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Capture options
    |--------------------------------------------------------------------------
    */
    'capture_snapshot'             => false,
    'capture_ip'                   => true,
    'capture_user_agent'           => true,
    'subject_returns_soft_deleted' => true,

];
