<?php

namespace Jimoh\ActivityFeed;

use Jimoh\ActivityFeed\Cachers\ActivityCacher;
use Jimoh\ActivityFeed\Console\Commands\PruneActivityCommand;
use Illuminate\Support\ServiceProvider;

class ActivityFeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/activity-feed.php',
            'activity-feed'
        );

        // ActivityLogger is transient — a fresh instance per resolve so fluent
        // chaining does not bleed state across separate ->log() calls.
        $this->app->bind(ActivityLogger::class, fn () => new ActivityLogger());

        $this->app->singleton(ActivityCacher::class, fn () => new ActivityCacher());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/activity-feed.php' => config_path('activity-feed.php'),
            ], 'activity-feed-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'activity-feed-migrations');

            $this->commands([
                PruneActivityCommand::class,
            ]);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
